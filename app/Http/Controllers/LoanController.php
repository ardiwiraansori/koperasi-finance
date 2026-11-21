<?php

namespace App\Http\Controllers;

use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Saving;
use App\Services\Ledger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function index(): View
    {
        $loans = Loan::with('member')->latest('applied_at')->latest('id')->get();
        $count = fn(string $status) => $loans->filter(fn($l) => $l->display_status === $status)->count();
        $pipeline = [
            'Menunggu Approval' => $count('Menunggu Approval'),
            'Disetujui' => $count('Disetujui'),
            'Aktif' => $count('Aktif'),
            'Tunggakan' => $count('Tunggakan'),
            'Lunas' => $count('Lunas'),
        ];
        return view('loans.index', compact('loans', 'pipeline'));
    }

    // Pencairan (kasir): pinjaman yang sudah disetujui admin dan menunggu uangnya diserahkan, dengan rincian pelunasan top-up dan saldo kas.
    public function disbursements(): View
    {
        $loans = Loan::with('member', 'appliedBy', 'approvedBy')->where('status', 'Disetujui')->orderBy('applied_at')->orderBy('id')->get();
        $payoffs = $loans->mapWithKeys(fn($l) => [$l->id => Loan::payoffLines($l->member_id)]);
        return view('disbursement.index', ['loans' => $loans, 'payoffs' => $payoffs, 'cash' => JournalLine::cashBalance()]);
    }

    // Pusat Approval (admin): pengajuan menunggu persetujuan; pinjaman disetujui tampil untuk dipantau dan masih bisa ditolak.
    public function approvals(): View
    {
        $pending = Loan::with('member')->where('status', 'Menunggu Approval')->orderBy('applied_at')->orderBy('id')->get();
        $approved = Loan::with('member')->where('status', 'Disetujui')->orderBy('applied_at')->orderBy('id')->get();
        $payoffs = $pending->concat($approved)->mapWithKeys(fn($l) => [$l->id => Loan::payoffLines($l->member_id)]);
        return view('approval.index', compact('pending', 'approved', 'payoffs'));
    }

    public function create(): View
    {
        $members = Member::where('status', 'Aktif')->orderBy('name')->get();
        // Rincian pelunasan per anggota yang masih punya pinjaman berjalan, untuk panel top-up di formulir (tanpa permintaan tambahan).
        $topups = collect(Loan::payoffOf(Loan::whereIn('status', Loan::RUNNING)->orderBy('id')->get()))->groupBy(fn($l) => $l['loan']->member_id)->map(fn($lines) => [
            'lines' => $lines->map(fn($l) => ['no' => $l['loan']->loan_no, 'outstanding' => $l['outstanding'], 'interest' => $l['interest'], 'months' => $l['months'], 'days' => $l['days']])->values(),
            'total' => $lines->sum('total'),
        ]);
        return view('loans.form', compact('members', 'topups'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['principal' => preg_replace('/\D/', '', (string) $request->input('principal'))]);
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'principal' => ['required', 'numeric', 'min:500000', 'max:1000000000'],
            'term_months' => ['required', 'integer', 'between:3,60'],
        ], [
            'required' => ':Attribute wajib diisi.',
            'exists' => ':Attribute tidak ditemukan.',
            'in' => ':Attribute tidak valid.',
            'numeric' => ':Attribute harus berupa angka.',
            'integer' => ':Attribute harus berupa angka bulat.',
            'principal.min' => 'Plafon minimal Rp 500.000.',
            'principal.max' => 'Plafon maksimal Rp 1.000.000.000.',
            'term_months.between' => 'Tenor harus antara 3 dan 60 bulan.',
        ], ['member_id' => 'anggota', 'principal' => 'plafon', 'term_months' => 'tenor']);

        $loan = $this->retryOnDuplicateNo(fn() => DB::transaction(function () use ($data) {
            $member = Member::lockForUpdate()->findOrFail($data['member_id']);
            $fail = fn(string $msg) => throw ValidationException::withMessages(['member_id' => $msg]);

            // Aturan koperasi: hanya anggota aktif, dan satu pengajuan yang menunggu pada satu waktu. Pinjaman yang masih berjalan
            // (lancar maupun menunggak) tidak menghalangi: pinjaman lama dilunasi dari pencairan pinjaman baru (top-up).
            if ($member->status !== 'Aktif') {
                $fail('Hanya anggota aktif yang boleh mengajukan pinjaman.');
            }
            if (Loan::where('member_id', $member->id)->whereIn('status', ['Menunggu Approval', 'Disetujui'])->exists()) {
                $fail('Anggota ini masih punya pengajuan pinjaman yang belum selesai diproses.');
            }
            // Pengecekan awal untuk kasir; keputusan akhir saat pencairan, ketika kewajiban lama dihitung ulang.
            $payoff = array_sum(array_column(Loan::payoffLines($member->id), 'total'));
            if ($payoff > 0 && $data['principal'] <= $payoff) {
                throw ValidationException::withMessages(['principal' => 'Plafon harus lebih besar dari pelunasan pinjaman lama (Rp ' . number_format($payoff, 0, ',', '.') . ').']);
            }

            return Loan::create([
                'member_id' => $member->id,
                'loan_no' => $this->nextLoanNo(),
                'product' => Loan::PRODUCT,
                'principal' => $data['principal'],
                'interest_rate' => Loan::RATE,
                'term_months' => $data['term_months'],
                'installment' => Loan::annuity($data['principal'], Loan::RATE, $data['term_months']),
                'outstanding' => 0,
                'status' => 'Menunggu Approval',
                'applied_at' => today(),
                'applied_by' => auth()->id(),
            ]);
        }));

        return redirect()->route('loans.show', $loan)->with('success', "Pengajuan {$loan->loan_no} tersimpan dan menunggu approval.");
    }

    // ---- Saldo awal pinjaman (go-live) -----------------------------------------------------------
    // Untuk pinjaman anggota lama yang sudah berjalan sebelum aplikasi dipakai: sisa pokok dicatat sebagai piutang
    // (D Piutang Pinjaman / K Ekuitas Saldo Awal). Kas TIDAK berubah karena uangnya sudah keluar sebelumnya.

    public function openingForm(): View
    {
        $members = Member::orderBy('member_no')->get();
        return view('loans.opening', compact('members'));
    }

    public function openingStore(Request $request): RedirectResponse
    {
        $request->merge(collect(['principal', 'outstanding', 'installment'])->mapWithKeys(fn($k) => [$k => preg_replace('/\D/', '', (string) $request->input($k))])->all());
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'loan_no' => ['nullable', 'string', 'max:30', 'unique:loans,loan_no'],
            'principal' => ['required', 'numeric', 'min:1', 'max:100000000000'],
            'term_months' => ['required', 'integer', 'between:1,120'],
            'outstanding' => ['required', 'numeric', 'min:1', 'lte:principal'],
            'installment' => ['nullable', 'numeric', 'min:1'],
            'disbursed_at' => ['required', 'date', 'before_or_equal:today'],
            'next_due_at' => ['required', 'date', 'after_or_equal:disbursed_at'],
            'tanggal' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:disbursed_at'],
        ], [
            'required' => ':Attribute wajib diisi.',
            'exists' => ':Attribute tidak ditemukan.',
            'in' => ':Attribute tidak valid.',
            'numeric' => ':Attribute harus berupa angka.',
            'integer' => ':Attribute harus berupa angka bulat.',
            'unique' => 'Nomor pinjaman ini sudah dipakai.',
            'min' => ':Attribute minimal Rp 1.',
            'outstanding.lte' => 'Sisa pokok tidak boleh lebih besar dari plafon.',
            'term_months.between' => 'Tenor harus antara 1 dan 120 bulan.',
            'before_or_equal' => ':Attribute tidak boleh di masa depan.',
            'after_or_equal' => ':Attribute tidak boleh sebelum tanggal pencairan.',
            'date' => 'Format tanggal tidak valid.',
        ], [
            'member_id' => 'anggota',
            'loan_no' => 'nomor pinjaman',
            'principal' => 'plafon',
            'term_months' => 'tenor',
            'outstanding' => 'sisa pokok',
            'installment' => 'cicilan',
            'disbursed_at' => 'tanggal pencairan',
            'next_due_at' => 'jatuh tempo berikutnya',
            'tanggal' => 'tanggal saldo awal'
        ]);

        $loan = $this->retryOnDuplicateNo(fn() => DB::transaction(function () use ($data) {
            $loan = Loan::create([
                'member_id' => $data['member_id'],
                'loan_no' => ($data['loan_no'] ?? null) ?: $this->nextLoanNo(),
                'product' => Loan::PRODUCT,
                'principal' => $data['principal'],
                'interest_rate' => Loan::RATE,
                'term_months' => $data['term_months'],
                'installment' => ($data['installment'] ?? null) ?: Loan::annuity($data['principal'], Loan::RATE, $data['term_months']),
                'outstanding' => $data['outstanding'],
                'status' => 'Aktif',
                'applied_at' => $data['disbursed_at'],
                'disbursed_at' => $data['disbursed_at'],
                'next_due_at' => $data['next_due_at'],
                'applied_by' => auth()->id(),
            ]);
            Ledger::post(
                [
                    'member_id' => $loan->member_id,
                    'loan_id' => $loan->id,
                    'category' => 'Saldo Awal Pinjaman',
                    'direction' => 'in',
                    'amount' => $loan->outstanding,
                    'description' => "Saldo awal pinjaman {$loan->loan_no}",
                    'trx_date' => Carbon::parse($data['tanggal'])->startOfDay()
                ],
                [['11301', 'Piutang Pinjaman', $loan->outstanding, 0], [...Saving::OPENING, 0, $loan->outstanding]]
            );
            return $loan;
        }));

        return redirect()->route('loans.show', $loan)->with('success', "Saldo awal pinjaman {$loan->loan_no} tersimpan. Kas tidak berubah.");
    }

    // Dua pengajuan pada detik yang sama bisa menghitung nomor yang sama. Kolom nomor itu unik, jadi yang kedua ditolak database
    // dan seluruh transaksinya diulang dengan nomor berikutnya. Dipanggil di luar DB::transaction (PostgreSQL membatalkan transaksi yang galat).
    private function retryOnDuplicateNo(callable $create): Loan
    {
        for ($try = 1;; $try++) {
            try {
                return $create();
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= 3) {
                    throw $e;
                }
            }
        }
    }

    private function nextLoanNo(): string
    {
        $year = now()->year;
        $last = Loan::where('loan_no', 'like', "PIN-$year-%")->max('loan_no');
        return sprintf('PIN-%d-%06d', $year, $last ? (int) substr($last, -6) + 1 : 1);
    }

    public function show(Loan $loan): View
    {
        $loan->load('member', 'appliedBy', 'approvedBy', 'disbursedBy', 'settledBy', 'settledLoans');
        $payoff = in_array($loan->status, ['Menunggu Approval', 'Disetujui'], true) ? Loan::payoffLines($loan->member_id) : [];
        $payments = $loan->transactions()->whereIn('category', ['Angsuran', 'Pencairan', 'Saldo Awal Pinjaman'])->orderByDesc('trx_date')->orderByDesc('id')->get();
        return view('loans.show', ['loan' => $loan, 'schedule' => in_array($loan->status, Loan::RUNNING, true) ? $loan->remainingSchedule() : $loan->schedule(), 'remaining' => in_array($loan->status, Loan::RUNNING, true), 'payments' => $payments, 'payoff' => $payoff, 'cash' => JournalLine::cashBalance()]);
    }

    public function approve(Loan $loan): RedirectResponse
    {
        $done = Loan::where('id', $loan->id)->where('status', 'Menunggu Approval')->update(['status' => 'Disetujui', 'approved_by' => auth()->id()]);
        return back()->with($done ? 'success' : 'error', $done ? "Pinjaman {$loan->loan_no} disetujui dan siap dicairkan." : 'Pinjaman ini tidak lagi menunggu approval.');
    }

    public function reject(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:200']], ['required' => 'Alasan penolakan wajib diisi.', 'max' => 'Alasan terlalu panjang (maksimal 200 karakter).']);
        $done = Loan::where('id', $loan->id)->whereIn('status', ['Menunggu Approval', 'Disetujui'])->update(['status' => 'Ditolak', 'note' => $data['note']]);
        return back()->with($done ? 'success' : 'error', $done ? "Pinjaman {$loan->loan_no} ditolak." : 'Pinjaman ini tidak bisa ditolak lagi.');
    }

    // Pencairan: piutang pinjaman bertambah (D), kas berkurang (K). Cicilan pertama jatuh tempo sebulan kemudian.
    // Top-up: bila anggota masih punya pinjaman berjalan, semuanya dilunasi dulu dari pencairan ini (jurnal angsuran per pinjaman lama),
    // sehingga kas hanya keluar sebesar plafon dikurangi pelunasan. Satu transaksi: gagal di mana pun berarti tidak ada yang tersimpan.
    public function disburse(Loan $loan): RedirectResponse
    {
        [$journalNo, $settled, $net] = DB::transaction(function () use ($loan) {
            $cash = Ledger::lockCash(); // harus pernyataan pertama: lihat catatan di Ledger::lockCash
            $member = Member::lockForUpdate()->findOrFail($loan->member_id); // urutan kunci tetap: kas, anggota, pinjaman baru, pinjaman lama
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);
            $fail = fn(string $msg) => throw ValidationException::withMessages(['loan' => $msg]);

            if ($loan->status !== 'Disetujui') {
                $fail('Hanya pinjaman berstatus Disetujui yang bisa dicairkan.');
            }
            if ($member->status !== 'Aktif') { // dari baris anggota yang terkunci, bukan dari gambaran data yang sudah dibekukan
                $fail('Anggota tidak aktif, pencairan ditolak.');
            }

            // Kewajiban lama dihitung ulang di sini, dari baris yang terkunci: angsuran bisa masuk sejak pengajuan dibuat.
            $lines = Loan::payoffLines($loan->member_id, lock: true);
            $payoff = array_sum(array_column($lines, 'total'));
            if ($lines && $loan->principal <= $payoff) {
                $fail('Plafon harus lebih besar dari pelunasan pinjaman lama (Rp ' . number_format($payoff, 0, ',', '.') . '). Tolak pengajuan ini dan ajukan ulang dengan plafon yang cukup.');
            }
            $net = $loan->principal - $payoff;
            if ($net > $cash) {
                $fail('Saldo kas tidak cukup untuk pencairan (uang keluar Rp ' . number_format($net, 0, ',', '.') . ', kas Rp ' . number_format($cash, 0, ',', '.') . ').');
            }

            foreach ($lines as $line) {
                $old = $line['loan'];
                Ledger::post(
                    ['member_id' => $old->member_id, 'loan_id' => $old->id, 'category' => 'Angsuran', 'direction' => 'in', 'amount' => $line['total'], 'description' => "Pelunasan {$old->loan_no} lewat top-up {$loan->loan_no}"],
                    [[...Ledger::KAS, $line['total'], 0], ['11301', 'Piutang Pinjaman', 0, $line['outstanding']], ['41101', 'Pendapatan Bunga', 0, $line['interest']]]
                );
                $old->update(['status' => 'Lunas', 'outstanding' => 0, 'next_due_at' => null, 'settled_by_loan_id' => $loan->id]);
            }

            [, $journalNo] = Ledger::post(
                ['member_id' => $loan->member_id, 'loan_id' => $loan->id, 'category' => 'Pencairan', 'direction' => 'out', 'amount' => $loan->principal, 'description' => "Pencairan pinjaman {$loan->loan_no}"],
                [['11301', 'Piutang Pinjaman', $loan->principal, 0], [...Ledger::KAS, 0, $loan->principal]]
            );
            $loan->update(['status' => 'Aktif', 'outstanding' => $loan->principal, 'disbursed_at' => today(), 'next_due_at' => today()->addMonthNoOverflow(), 'note' => null, 'disbursed_by' => auth()->id()]);

            return [$journalNo, $lines, $net];
        });

        $topup = $settled ? ' Pelunasan ' . implode(', ', array_map(fn($l) => $l['loan']->loan_no, $settled)) . ' Rp ' . number_format(array_sum(array_column($settled, 'total')), 0, ',', '.') . '; uang keluar Rp ' . number_format($net, 0, ',', '.') . '.' : '';

        return back()->with('success', "Pinjaman {$loan->loan_no} dicairkan.$topup Jurnal $journalNo dibuat.");
    }
}
