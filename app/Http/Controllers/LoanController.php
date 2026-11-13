<?php
namespace App\Http\Controllers;

use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Saving;
use App\Services\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function index(): View
    {
        $loans = Loan::with('member')->latest('applied_at')->latest('id')->get();
        $count = fn (string $status) => $loans->filter(fn ($l) => $l->display_status === $status)->count();
        $pipeline = [
            'Menunggu Approval' => $count('Menunggu Approval'), 'Disetujui' => $count('Disetujui'),
            'Aktif' => $count('Aktif'), 'Tunggakan' => $count('Tunggakan'), 'Lunas' => $count('Lunas'),
        ];
        return view('loans.index', compact('loans', 'pipeline'));
    }

    // Pusat Approval: pengajuan menunggu persetujuan dan pinjaman disetujui yang siap dicairkan.
    public function approvals(): View
    {
        $pending = Loan::with('member')->where('status', 'Menunggu Approval')->orderBy('applied_at')->orderBy('id')->get();
        $approved = Loan::with('member')->where('status', 'Disetujui')->orderBy('applied_at')->orderBy('id')->get();
        return view('approval.index', compact('pending', 'approved'));
    }

    public function create(): View
    {
        $members = Member::where('status', 'Aktif')->orderBy('name')->get();
        return view('loans.form', compact('members'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['principal' => preg_replace('/\D/', '', (string) $request->input('principal'))]);
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'product' => ['required', Rule::in(array_keys(Loan::PRODUCTS))],
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
        ], ['member_id' => 'anggota', 'product' => 'produk', 'principal' => 'plafon', 'term_months' => 'tenor']);

        $loan = DB::transaction(function () use ($data) {
            $member = Member::lockForUpdate()->findOrFail($data['member_id']);
            $fail = fn (string $msg) => throw ValidationException::withMessages(['member_id' => $msg]);

            // Aturan umum: hanya anggota aktif, tanpa tunggakan, dan satu pengajuan yang menunggu pada satu waktu.
            if ($member->status !== 'Aktif') {
                $fail('Hanya anggota aktif yang boleh mengajukan pinjaman.');
            }
            $open = Loan::where('member_id', $member->id)->get();
            if ($open->contains(fn ($l) => $l->daysOverdue() > 0)) {
                $fail('Anggota ini punya pinjaman yang menunggak. Selesaikan tunggakan dulu.');
            }
            if ($open->contains(fn ($l) => in_array($l->status, ['Menunggu Approval', 'Disetujui'], true))) {
                $fail('Anggota ini masih punya pengajuan pinjaman yang belum selesai diproses.');
            }

            $rate = Loan::PRODUCTS[$data['product']];

            return Loan::create([
                'member_id' => $member->id, 'loan_no' => $this->nextLoanNo(), 'product' => $data['product'],
                'principal' => $data['principal'], 'interest_rate' => $rate, 'term_months' => $data['term_months'],
                'installment' => Loan::annuity($data['principal'], $rate, $data['term_months']),
                'outstanding' => 0, 'status' => 'Menunggu Approval', 'applied_at' => today(),
            ]);
        });

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
        $request->merge(collect(['principal', 'outstanding', 'installment'])->mapWithKeys(fn ($k) => [$k => preg_replace('/\D/', '', (string) $request->input($k))])->all());
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'loan_no' => ['nullable', 'string', 'max:30', 'unique:loans,loan_no'],
            'product' => ['required', Rule::in(array_keys(Loan::PRODUCTS))],
            'principal' => ['required', 'numeric', 'min:1', 'max:100000000000'],
            'term_months' => ['required', 'integer', 'between:1,120'],
            'outstanding' => ['required', 'numeric', 'min:1', 'lte:principal'],
            'installment' => ['nullable', 'numeric', 'min:1'],
            'disbursed_at' => ['required', 'date', 'before_or_equal:today'],
            'next_due_at' => ['required', 'date', 'after_or_equal:disbursed_at'],
            'tanggal' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:disbursed_at'],
        ], [
            'required' => ':Attribute wajib diisi.', 'exists' => ':Attribute tidak ditemukan.', 'in' => ':Attribute tidak valid.',
            'numeric' => ':Attribute harus berupa angka.', 'integer' => ':Attribute harus berupa angka bulat.', 'unique' => 'Nomor pinjaman ini sudah dipakai.',
            'min' => ':Attribute minimal Rp 1.', 'outstanding.lte' => 'Sisa pokok tidak boleh lebih besar dari plafon.',
            'term_months.between' => 'Tenor harus antara 1 dan 120 bulan.', 'before_or_equal' => ':Attribute tidak boleh di masa depan.',
            'after_or_equal' => ':Attribute tidak boleh sebelum tanggal pencairan.', 'date' => 'Format tanggal tidak valid.',
        ], ['member_id' => 'anggota', 'loan_no' => 'nomor pinjaman', 'product' => 'produk', 'principal' => 'plafon', 'term_months' => 'tenor', 'outstanding' => 'sisa pokok',
            'installment' => 'cicilan', 'disbursed_at' => 'tanggal pencairan', 'next_due_at' => 'jatuh tempo berikutnya', 'tanggal' => 'tanggal saldo awal']);

        $loan = DB::transaction(function () use ($data) {
            $rate = Loan::PRODUCTS[$data['product']];
            $loan = Loan::create([
                'member_id' => $data['member_id'], 'loan_no' => ($data['loan_no'] ?? null) ?: $this->nextLoanNo(), 'product' => $data['product'],
                'principal' => $data['principal'], 'interest_rate' => $rate, 'term_months' => $data['term_months'],
                'installment' => ($data['installment'] ?? null) ?: Loan::annuity($data['principal'], $rate, $data['term_months']),
                'outstanding' => $data['outstanding'], 'status' => 'Aktif', 'applied_at' => $data['disbursed_at'],
                'disbursed_at' => $data['disbursed_at'], 'next_due_at' => $data['next_due_at'],
            ]);
            Ledger::post(
                ['member_id' => $loan->member_id, 'loan_id' => $loan->id, 'category' => 'Saldo Awal Pinjaman', 'direction' => 'in', 'amount' => $loan->outstanding,
                 'description' => "Saldo awal pinjaman {$loan->loan_no}", 'trx_date' => Carbon::parse($data['tanggal'])->startOfDay()],
                [['11301', 'Piutang Pinjaman', $loan->outstanding, 0], [...Saving::OPENING, 0, $loan->outstanding]]
            );
            return $loan;
        });

        return redirect()->route('loans.show', $loan)->with('success', "Saldo awal pinjaman {$loan->loan_no} tersimpan. Kas tidak berubah.");
    }

    private function nextLoanNo(): string
    {
        $year = now()->year;
        $last = Loan::where('loan_no', 'like', "PIN-$year-%")->max('loan_no');
        return sprintf('PIN-%d-%06d', $year, $last ? (int) substr($last, -6) + 1 : 1);
    }

    public function show(Loan $loan): View
    {
        $loan->load('member');
        $payments = $loan->transactions()->whereIn('category', ['Angsuran', 'Pencairan', 'Saldo Awal Pinjaman'])->orderByDesc('trx_date')->orderByDesc('id')->get();
        return view('loans.show', ['loan' => $loan, 'schedule' => in_array($loan->status, Loan::RUNNING, true) ? $loan->remainingSchedule() : $loan->schedule(), 'remaining' => in_array($loan->status, Loan::RUNNING, true), 'payments' => $payments, 'cash' => JournalLine::cashBalance()]);
    }

    public function approve(Loan $loan): RedirectResponse
    {
        $done = Loan::where('id', $loan->id)->where('status', 'Menunggu Approval')->update(['status' => 'Disetujui']);
        return back()->with($done ? 'success' : 'error', $done ? "Pinjaman {$loan->loan_no} disetujui dan siap dicairkan." : 'Pinjaman ini tidak lagi menunggu approval.');
    }

    public function reject(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:200']], ['required' => 'Alasan penolakan wajib diisi.', 'max' => 'Alasan terlalu panjang (maksimal 200 karakter).']);
        $done = Loan::where('id', $loan->id)->whereIn('status', ['Menunggu Approval', 'Disetujui'])->update(['status' => 'Ditolak', 'note' => $data['note']]);
        return back()->with($done ? 'success' : 'error', $done ? "Pinjaman {$loan->loan_no} ditolak." : 'Pinjaman ini tidak bisa ditolak lagi.');
    }

    // Pencairan: piutang pinjaman bertambah (D), kas berkurang (K). Cicilan pertama jatuh tempo sebulan kemudian.
    public function disburse(Loan $loan): RedirectResponse
    {
        $journalNo = DB::transaction(function () use ($loan) {
            $loan = Loan::with('member')->lockForUpdate()->findOrFail($loan->id);
            $fail = fn (string $msg) => throw ValidationException::withMessages(['loan' => $msg]);

            if ($loan->status !== 'Disetujui') {
                $fail('Hanya pinjaman berstatus Disetujui yang bisa dicairkan.');
            }
            if ($loan->member->status !== 'Aktif') {
                $fail('Anggota tidak aktif, pencairan ditolak.');
            }
            // shortcut: dicek terhadap saldo kas saat ini tanpa kunci; dua pencairan besar di detik yang sama bisa sama-sama lolos, upgrade bila tim kasir banyak.
            if ($loan->principal > JournalLine::cashBalance()) {
                $fail('Saldo kas tidak cukup untuk pencairan (kas Rp '.number_format(JournalLine::cashBalance(), 0, ',', '.').').');
            }

            [, $journalNo] = Ledger::post(
                ['member_id' => $loan->member_id, 'loan_id' => $loan->id, 'category' => 'Pencairan', 'direction' => 'out', 'amount' => $loan->principal, 'description' => "Pencairan pinjaman {$loan->loan_no}"],
                [['11301', 'Piutang Pinjaman', $loan->principal, 0], [...Ledger::KAS, 0, $loan->principal]]
            );
            $loan->update(['status' => 'Aktif', 'outstanding' => $loan->principal, 'disbursed_at' => today(), 'next_due_at' => today()->addMonthNoOverflow(), 'note' => null]);

            return $journalNo;
        });

        return back()->with('success', "Pinjaman {$loan->loan_no} dicairkan. Jurnal $journalNo dibuat.");
    }
}
