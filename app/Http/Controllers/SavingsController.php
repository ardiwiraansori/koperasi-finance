<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Saving;
use App\Models\Transaction;
use App\Services\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SavingsController extends Controller
{
    public function index(): View
    {
        $savings = Saving::with('member')->orderByDesc('balance')->get();
        $summary = $savings->groupBy('type')->map(fn($rows) => $rows->sum('balance'));
        return view('savings.index', compact('savings', 'summary'));
    }

    // $mode datang dari routes/web.php: 'setoran' atau 'penarikan'.
    public function create(Request $request, string $mode): View
    {
        $withdraw = $mode === 'penarikan';
        $indebted = Loan::whereIn('status', Loan::RUNNING)->pluck('member_id')->flip(); // anggota dengan pinjaman berjalan
        $savings = Saving::with('member')->orderBy('account_no')->get()->filter(
            fn(Saving $s) => $withdraw ? $s->balance > 0 && $this->canWithdraw($s) && ! $indebted->has($s->member_id) : $s->member->status === 'Aktif'
        );
        return view('savings.transaction', ['savings' => $savings, 'withdraw' => $withdraw, 'selected' => $request->integer('rekening')]);
    }

    public function store(Request $request, string $mode): RedirectResponse
    {
        $withdraw = $mode === 'penarikan';
        // Nominal dikirim berformat rupiah ("1.500.000"): ambil angkanya saja.
        $request->merge(['amount' => preg_replace('/\D/', '', (string) $request->input('amount'))]);
        $data = $request->validate([
            'saving_id' => ['required', 'exists:savings,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'description' => ['nullable', 'string', 'max:200'],
        ], [
            'required' => ':Attribute wajib diisi.',
            'numeric' => ':Attribute harus berupa angka.',
            'min' => 'Nominal minimal Rp 1.',
            'max' => 'Nominal terlalu besar (maksimal Rp 1.000.000.000 per transaksi).',
            'saving_id.exists' => 'Rekening tidak ditemukan.',
        ], ['saving_id' => 'rekening', 'amount' => 'nominal']);

        $result = DB::transaction(function () use ($data, $withdraw) {
            $cash = $withdraw ? Ledger::lockCash() : 0; // harus pernyataan pertama: lihat catatan di Ledger::lockCash
            $saving = Saving::with('member')->lockForUpdate()->findOrFail($data['saving_id']);
            $fail = fn(string $msg) => throw ValidationException::withMessages(['saving_id' => $msg]);

            if ($withdraw) {
                // Aturan umum: Pokok dan Wajib hanya bisa ditarik setelah keluar dari keanggotaan; Sukarela bebas.
                if (! $this->canWithdraw($saving)) {
                    $fail("Simpanan {$saving->type} tidak bisa ditarik selama masih menjadi anggota aktif.");
                }
                // Aturan umum: anggota yang masih punya pinjaman berjalan tidak boleh menarik simpanannya (jaminan pelunasan).
                $running = Loan::where('member_id', $saving->member_id)->whereIn('status', Loan::RUNNING)->value('loan_no');
                if ($running) {
                    $fail("Anggota masih punya pinjaman berjalan ($running). Lunasi dulu sebelum menarik simpanan.");
                }
                if ($data['amount'] > $saving->balance) {
                    $fail('Saldo tidak cukup. Saldo rekening Rp ' . number_format($saving->balance, 0, ',', '.') . '.');
                }
                if ($data['amount'] > $cash) {
                    $fail('Saldo kas tidak cukup untuk penarikan ini (kas Rp ' . number_format($cash, 0, ',', '.') . ').');
                }
                $saving->decrement('balance', $data['amount']);
            } else {
                // Aturan umum: setoran hanya untuk anggota aktif; Simpanan Pokok dibayar sekali saja.
                if ($saving->member->status !== 'Aktif') {
                    $fail('Anggota ini tidak aktif, setoran ditolak.');
                }
                if ($saving->type === 'Pokok' && $saving->balance > 0) {
                    $fail('Simpanan Pokok hanya dibayar satu kali dan rekening ini sudah terisi.');
                }
                $saving->increment('balance', $data['amount']);
            }

            $label = $withdraw ? 'Penarikan' : 'Setoran';
            $description = ($data['description'] ?? null) ?: "$label simpanan {$saving->type} {$saving->member->member_no}";
            $kas = Ledger::KAS;
            $sim = Saving::ACCOUNTS[$saving->type];
            $amount = $data['amount'];

            // Jurnal otomatis. Setoran: Kas (D) / Simpanan (K). Penarikan: kebalikannya.
            [, $journalNo] = Ledger::post(
                ['member_id' => $saving->member_id, 'saving_id' => $saving->id, 'category' => 'Simpanan', 'direction' => $withdraw ? 'out' : 'in', 'amount' => $amount, 'description' => $description],
                $withdraw ? [[...$sim, $amount, 0], [...$kas, 0, $amount]] : [[...$kas, $amount, 0], [...$sim, 0, $amount]]
            );

            return [$label, $saving->account_no, $journalNo];
        });

        return redirect()->route('savings.index')->with('success', "{$result[0]} Rp " . number_format($data['amount'], 0, ',', '.') . " rekening {$result[1]} berhasil. Jurnal {$result[2]} dibuat.");
    }

    // Rekening koran: mutasi satu rekening dengan saldo berjalan. Saldo awal = saldo sekarang - semua mutasi tercatat.
    public function statement(Request $request): View
    {
        $request->validate([
            'rekening' => ['nullable', 'exists:savings,id'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ], ['sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.', 'date' => 'Format tanggal tidak valid.'], ['rekening' => 'rekening']);

        $accounts = Saving::with('member')->orderBy('account_no')->get();
        $saving = $request->filled('rekening') ? $accounts->firstWhere('id', $request->integer('rekening')) : null;
        $statement = null;

        if ($saving) {
            $from = $request->date('dari')?->startOfDay();
            $to = $request->date('sampai')?->endOfDay();
            $all = $saving->transactions()->orderBy('trx_date')->orderBy('id')->get();
            $signed = fn(Transaction $t) => $t->direction === 'in' ? (float) $t->amount : -(float) $t->amount;

            $running = (float) $saving->balance - $all->sum($signed);
            $opening = $running;
            $lines = $all->map(function (Transaction $t) use (&$running, $signed) {
                $running += $signed($t);
                return ['trx' => $t, 'saldo' => $running];
            });

            // Saldo awal simpanan (cut-over) adalah mutasi pertama rekening dan dilipat ke baris "Saldo awal", bukan dihitung sebagai setoran.
            $isOpening = fn($l) => $l['trx']->category === 'Saldo Awal Simpanan';
            $inPeriod = fn($l) => (! $from || $l['trx']->trx_date->gte($from)) && (! $to || $l['trx']->trx_date->lte($to));
            $periodOpening = ($from ? ($lines->filter(fn($l) => $l['trx']->trx_date->lt($from))->last()['saldo'] ?? $opening) : $opening)
                + $lines->filter(fn($l) => $isOpening($l) && $inPeriod($l))->sum(fn($l) => (float) $l['trx']->amount);
            $rows = $lines->filter(fn($l) => $inPeriod($l) && ! $isOpening($l))->values();
            $statement = [
                'opening' => $periodOpening,
                'rows' => $rows,
                'in' => $rows->where('trx.direction', 'in')->sum(fn($l) => (float) $l['trx']->amount),
                'out' => $rows->where('trx.direction', 'out')->sum(fn($l) => (float) $l['trx']->amount),
                'closing' => $rows->last()['saldo'] ?? $periodOpening,
            ];
        }

        return view('savings.statement', [
            'accounts' => $accounts,
            'saving' => $saving,
            'statement' => $statement,
            'dari' => $request->input('dari'),
            'sampai' => $request->input('sampai')
        ]);
    }

    // Saldo awal simpanan untuk anggota lama saat mulai memakai aplikasi: saldo rekening terisi, kas TIDAK berubah.
    // Jurnal: Ekuitas Saldo Awal (D) / Simpanan anggota (K). Hanya untuk rekening yang belum punya saldo atau transaksi.
    public function openingForm(Request $request): View
    {
        $members = Member::orderBy('member_no')->get();
        $member = $request->filled('anggota') ? Member::with('savings')->find($request->integer('anggota')) : null;
        $locked = $member ? $member->savings->mapWithKeys(fn(Saving $s) => [$s->type => $s->balance > 0 || $s->transactions()->exists()])->all() : [];
        return view('savings.opening', compact('members', 'member', 'locked'));
    }

    public function openingStore(Request $request): RedirectResponse
    {
        $types = ['Pokok', 'Wajib', 'Sukarela'];
        $saldo = collect($types)->mapWithKeys(fn($t) => [$t => preg_replace('/\D/', '', (string) $request->input("saldo.$t"))])->all();
        $request->merge(['saldo' => $saldo]);
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'saldo.*' => ['nullable', 'numeric', 'min:1', 'max:100000000000'],
        ], [
            'required' => ':Attribute wajib diisi.',
            'exists' => ':Attribute tidak ditemukan.',
            'numeric' => 'Saldo harus berupa angka.',
            'min' => 'Saldo minimal Rp 1 (kosongkan bila tidak ada).',
            'max' => 'Saldo terlalu besar.',
            'before_or_equal' => 'Tanggal saldo awal tidak boleh di masa depan.',
            'date' => 'Format tanggal tidak valid.',
        ], ['member_id' => 'anggota', 'tanggal' => 'tanggal']);

        $filled = collect($data['saldo'])->filter(fn($v) => (float) $v > 0);
        if ($filled->isEmpty()) {
            throw ValidationException::withMessages(['saldo' => 'Isi minimal satu saldo awal simpanan.']);
        }

        $count = DB::transaction(function () use ($data, $filled) {
            $member = Member::findOrFail($data['member_id']);
            foreach ($filled as $type => $amount) {
                $saving = Saving::where('member_id', $member->id)->where('type', $type)->lockForUpdate()->firstOrFail();
                if ($saving->balance > 0 || $saving->transactions()->exists()) {
                    throw ValidationException::withMessages(["saldo.$type" => "Simpanan $type sudah punya saldo atau transaksi. Saldo awal hanya bisa diisi sebelum rekening dipakai."]);
                }
                $amount = (float) $amount;
                $saving->update(['balance' => $amount]);
                Ledger::post(
                    [
                        'member_id' => $member->id,
                        'saving_id' => $saving->id,
                        'category' => 'Saldo Awal Simpanan',
                        'direction' => 'in',
                        'amount' => $amount,
                        'description' => "Saldo awal simpanan $type {$member->member_no}",
                        'trx_date' => Carbon::parse($data['tanggal'])->startOfDay()
                    ],
                    [[...Saving::OPENING, $amount, 0], [...Saving::ACCOUNTS[$type], 0, $amount]]
                );
            }
            return $filled->count();
        });

        return redirect()->route('savings.statement', ['rekening' => Saving::where('member_id', $data['member_id'])->where('type', $filled->keys()->first())->value('id')])
            ->with('success', "Saldo awal $count rekening simpanan tersimpan. Kas tidak berubah.");
    }

    private function canWithdraw(Saving $saving): bool
    {
        return $saving->type === 'Sukarela' || $saving->member->status !== 'Aktif';
    }
}
