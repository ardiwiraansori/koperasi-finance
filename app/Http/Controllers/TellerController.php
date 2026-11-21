<?php
namespace App\Http\Controllers;

use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Services\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TellerController extends Controller
{
    public function index(): View
    {
        $members = Member::where('status', 'Aktif')->orderBy('name')->get();
        $loans = Loan::with('member')->whereIn('status', Loan::RUNNING)->orderBy('loan_no')->get();
        $transactions = Transaction::cashOnly()->with('member')->latest('trx_date')->latest('id')->limit(10)->get();
        $cash = JournalLine::cashBalance();
        $types = TransactionType::active()->orderBy('category')->orderBy('sort')->orderBy('name')->get();
        return view('teller.index', compact('members', 'loans', 'transactions', 'cash', 'types'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Nominal dikirim berformat rupiah ("1.500.000"): ambil angkanya saja.
        $request->merge(['amount' => preg_replace('/\D/', '', (string) $request->input('amount'))]);
        $data = $request->validate([
            'member_id' => ['required_if:category,Angsuran', 'nullable', 'exists:members,id'],
            'category' => ['required', 'in:Angsuran,Penerimaan,Pengeluaran,Saldo Awal'],
            // Kasir memilih jenis transaksi (bahasa sehari-hari); akunnya ditentukan pemetaan di menu Akuntansi.
            'jenis_id' => [Rule::requiredIf(in_array($request->input('category'), TransactionType::CATEGORIES, true)), 'nullable',
                Rule::exists('transaction_types', 'id')->where('active', true)->where('category', $request->input('category'))],
            'loan_id' => ['required_if:category,Angsuran', 'nullable', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'description' => ['required', 'string', 'max:255'],
        ], [
            'required' => ':Attribute wajib diisi.',
            'required_if' => ':Attribute wajib diisi untuk angsuran.',
            'in' => ':Attribute tidak valid.',
            'exists' => ':Attribute tidak ditemukan.',
            'numeric' => ':Attribute harus berupa angka.',
            'min' => 'Nominal minimal Rp 1.',
            'max' => 'Nominal terlalu besar (maksimal Rp 1.000.000.000 per transaksi).',
            'description.max' => 'Keterangan terlalu panjang (maksimal 255 karakter).',
        ], ['category' => 'kategori', 'jenis_id' => 'jenis transaksi', 'loan_id' => 'pinjaman', 'amount' => 'nominal', 'description' => 'keterangan', 'member_id' => 'anggota'])
            + ['jenis_id' => null];

        if ($data['category'] === 'Saldo Awal' && ! $request->user()->allows('opening')) {
            throw ValidationException::withMessages(['category' => 'Saldo awal kas hanya bisa dicatat oleh admin atau akuntan.']);
        }
        if ($data['category'] === 'Angsuran') {
            return $this->payInstallment($data);
        }
        $type = $data['jenis_id'] ? TransactionType::findOrFail($data['jenis_id']) : null;
        // Saldo Awal selalu ke Ekuitas Saldo Awal; penerimaan/pengeluaran ke akun yang dipetakan pada jenisnya.
        return $this->postCash($data, $type ? [$type->account_code, $type->accountName()] : Saving::OPENING, $type?->id);
    }

    // Penerimaan: Kas (D) / Pendapatan (K). Pengeluaran: Beban (D) / Kas (K). Arah kas ditentukan kategori.
    private function postCash(array $data, array $account, ?int $typeId): RedirectResponse
    {
        $out = $data['category'] === 'Pengeluaran';
        [, $accountName] = $account;
        $journalNo = DB::transaction(function () use ($data, $account, $typeId, $out) {
            $cash = $out ? Ledger::lockCash() : 0; // harus pernyataan pertama: lihat catatan di Ledger::lockCash
            if ($data['category'] === 'Saldo Awal' && Transaction::where('category', 'Saldo Awal')->exists()) {
                throw ValidationException::withMessages(['category' => 'Saldo awal kas hanya dicatat satu kali. Selisih berikutnya catat sebagai Penerimaan atau Pengeluaran.']);
            }
            if ($out && $data['amount'] > $cash) {
                throw ValidationException::withMessages(['amount' => 'Saldo kas tidak cukup (kas Rp '.number_format($cash, 0, ',', '.').'). Catat saldo awal kas dulu bila belum.']);
            }
            $kas = Ledger::KAS;
            $lawan = $account;
            $amount = $data['amount'];
            return Ledger::post(
                ['member_id' => $data['member_id'] ?? null, 'transaction_type_id' => $typeId, 'category' => $data['category'], 'direction' => $out ? 'out' : 'in', 'amount' => $amount, 'description' => $data['description']],
                $out ? [[...$lawan, $amount, 0], [...$kas, 0, $amount]] : [[...$kas, $amount, 0], [...$lawan, 0, $amount]]
            )[1];
        });

        return back()->with('success', "{$data['category']} Rp ".number_format($data['amount'], 0, ',', '.')." ($accountName) tersimpan. Jurnal $journalNo dibuat.");
    }

    // Aturan umum angsuran (anuitas): bunga = sisa pokok x bunga tahunan / 12; sisanya mengurangi pokok.
    private function payInstallment(array $data): RedirectResponse
    {
        $result = DB::transaction(function () use ($data) {
            $loan = Loan::with('member')->lockForUpdate()->findOrFail($data['loan_id']);
            $fail = fn (string $msg) => throw ValidationException::withMessages(['loan_id' => $msg]);

            if (! empty($data['member_id']) && (int) $data['member_id'] !== $loan->member_id) {
                $fail('Pinjaman ini bukan milik anggota yang dipilih.');
            }
            if (! in_array($loan->status, Loan::RUNNING, true)) {
                $fail("Pinjaman {$loan->loan_no} berstatus {$loan->status}, tidak bisa menerima angsuran.");
            }
            $amount = (float) $data['amount'];
            $interest = $loan->interestDue();
            $fmt = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
            if ($amount < $interest) {
                $fail('Nominal minimal sebesar bunga bulan ini, '.$fmt($interest).'.');
            }
            if ($amount > $loan->outstanding + $interest) {
                $fail('Nominal melebihi sisa kewajiban, maksimal '.$fmt($loan->outstanding + $interest).' (sisa pokok + bunga bulan ini).');
            }

            $principal = $amount - $interest;
            $loan->outstanding = $loan->outstanding - $principal;
            if ($loan->outstanding <= 0) {
                $loan->status = 'Lunas';
                $loan->next_due_at = null;
            } elseif ($amount >= $loan->installment) {
                // Angsuran penuh: jatuh tempo maju satu bulan; tunggakan pulih bila sudah tidak lewat tempo.
                $loan->next_due_at = $loan->next_due_at?->addMonthNoOverflow(); // addMonth() meloncat: 31 Jan + 1 bulan = 3 Mar
                $loan->status = $loan->next_due_at?->lt(today()) ? 'Tunggakan' : 'Aktif';
            }
            $loan->save();

            // Jurnal: Kas (D) / Piutang Pinjaman (K, porsi pokok) / Pendapatan Bunga (K, porsi bunga).
            [, $journalNo] = Ledger::post(
                ['member_id' => $loan->member_id, 'loan_id' => $loan->id, 'category' => 'Angsuran', 'direction' => 'in', 'amount' => $amount, 'description' => $data['description']],
                [[...Ledger::KAS, $amount, 0], ['11301', 'Piutang Pinjaman', 0, $principal], ['41101', 'Pendapatan Bunga', 0, $interest]]
            );

            return [$loan, $principal, $interest, $journalNo];
        });

        [$loan, $principal, $interest, $journalNo] = $result;
        $fmt = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
        $msg = "Angsuran {$loan->loan_no} diterima: pokok {$fmt($principal)}, bunga {$fmt($interest)}. Sisa pokok {$fmt($loan->outstanding)}.";
        return back()->with('success', $msg.($loan->status === 'Lunas' ? ' Pinjaman LUNAS.' : '')." Jurnal $journalNo dibuat.");
    }
}
