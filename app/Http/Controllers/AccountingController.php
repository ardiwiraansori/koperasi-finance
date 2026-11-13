<?php
namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Setting;
use App\Models\TransactionType;
use App\Services\Reports;
use App\Support\Coa;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingController extends Controller
{
    // ---- Jurnal Umum ------------------------------------------------------------------

    public function index(Request $request): View
    {
        $this->validateFilters($request);
        $base = $this->filtered($request);

        $totals = JournalLine::whereIn('journal_entry_id', (clone $base)->select('id'))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')->first();

        return view('accounting.index', [
            'journals' => (clone $base)->with('lines')->orderByDesc('journal_date')->orderByDesc('id')->simplePaginate(25)->withQueryString(),
            'count' => (clone $base)->count(),
            'posted' => (clone $base)->where('status', 'Posted')->count(),
            'debit' => (float) $totals->debit,
            'credit' => (float) $totals->credit,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->validateFilters($request);
        $base = $this->filtered($request);

        $rows = (function () use ($base) {
            foreach ($base->with('lines')->orderBy('journal_date')->orderBy('id')->lazy(200) as $j) {
                foreach ($j->lines as $l) {
                    yield [$j->journal_date->format('Y-m-d'), $j->journal_no, $j->reference, $j->description, $j->source, $j->status, $l->account_code, $l->account_name, (float) $l->debit, (float) $l->credit];
                }
            }
        })();

        return Csv::download('jurnal-'.now()->format('Ymd').'.csv',
            ['Tanggal', 'No Jurnal', 'Referensi', 'Keterangan', 'Sumber', 'Status', 'Kode Akun', 'Nama Akun', 'Debit', 'Kredit'], $rows);
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'q' => ['nullable', 'string', 'max:100'],
            'sumber' => ['nullable', Rule::in(['AUTO JOURNAL', 'MANUAL'])],
        ], ['sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.', 'date' => 'Format tanggal tidak valid.']);
    }

    private function filtered(Request $request): Builder
    {
        return JournalEntry::query()
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('journal_date', '>=', $request->input('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('journal_date', '<=', $request->input('sampai')))
            ->when($request->filled('sumber'), fn ($q) => $q->where('source', $request->input('sumber')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->input('q').'%';
                $q->where(fn ($w) => $w->where('journal_no', 'like', $like)->orWhere('description', 'like', $like)->orWhere('reference', 'like', $like));
            });
    }

    // ---- Buku Besar -------------------------------------------------------------------

    public function ledger(Request $request): View
    {
        $request->validate([
            'akun' => ['nullable', 'string', 'max:30'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ], ['sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.', 'date' => 'Format tanggal tidak valid.']);

        // Daftar akun: bagan akun resmi ditambah kode lain yang kebetulan sudah ada di jurnal.
        $accounts = collect(Coa::ACCOUNTS)->map(fn ($a) => $a[0]);
        DB::table('journal_lines')->distinct()->pluck('account_name', 'account_code')->each(fn ($name, $code) => $accounts->has($code) ?: $accounts->put($code, $name));
        $accounts = $accounts->sortKeys();

        $code = $request->input('akun');
        $ledger = null;
        if ($code && $accounts->has($code)) {
            $sign = Coa::sign($code);
            $lines = JournalLine::query()
                ->join('journal_entries as e', 'e.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_lines.account_code', $code)->where('e.status', 'Posted')
                ->orderBy('e.journal_date')->orderBy('e.id')->orderBy('journal_lines.id')
                ->get(['journal_lines.*', 'e.journal_date as j_date', 'e.journal_no as j_no', 'e.description as j_desc']);
            $from = $request->input('dari');
            $to = $request->input('sampai');
            $date = fn ($l) => substr((string) $l->j_date, 0, 10);

            $running = $lines->filter(fn ($l) => $from && $date($l) < $from)->sum(fn ($l) => $sign * ((float) $l->debit - (float) $l->credit));
            $opening = $running;
            $rows = $lines->filter(fn ($l) => (! $from || $date($l) >= $from) && (! $to || $date($l) <= $to))->map(function ($l) use (&$running, $sign) {
                $running += $sign * ((float) $l->debit - (float) $l->credit);
                return ['line' => $l, 'saldo' => $running];
            })->values();
            $ledger = ['opening' => $opening, 'rows' => $rows, 'debit' => $rows->sum(fn ($r) => (float) $r['line']->debit), 'credit' => $rows->sum(fn ($r) => (float) $r['line']->credit), 'closing' => $rows->last()['saldo'] ?? $opening];
        }

        return view('accounting.ledger', ['accounts' => $accounts, 'code' => $code, 'ledger' => $ledger, 'dari' => $request->input('dari'), 'sampai' => $request->input('sampai')]);
    }

    // ---- Neraca Saldo -----------------------------------------------------------------

    public function trialBalance(Request $request): View|StreamedResponse
    {
        $request->validate(['sampai' => ['nullable', 'date']], ['date' => 'Format tanggal tidak valid.']);
        $until = $request->input('sampai', now()->toDateString());

        // Sumber angka yang sama dengan Neraca dan Laba Rugi (AppServicesReports), supaya aturan jurnal Posted hanya ada di satu tempat.
        $rows = Reports::totals(null, $until)->values()->map(fn ($r) => [
            'code' => $r['code'], 'name' => $r['name'], 'type' => Coa::type($r['code']),
            'debit' => $r['debit'], 'credit' => $r['credit'],
            'balD' => max(0, $r['debit'] - $r['credit']), 'balK' => max(0, $r['credit'] - $r['debit']),
        ]);

        if ($request->boolean('export')) {
            return Csv::download('neraca-saldo-'.$until.'.csv', ['Kode', 'Akun', 'Golongan', 'Total Debit', 'Total Kredit', 'Saldo Debit', 'Saldo Kredit'],
                $rows->map(fn ($r) => [$r['code'], $r['name'], $r['type'], $r['debit'], $r['credit'], $r['balD'], $r['balK']]));
        }

        return view('accounting.trial-balance', ['rows' => $rows, 'until' => $until,
            'totals' => ['debit' => $rows->sum('debit'), 'credit' => $rows->sum('credit'), 'balD' => $rows->sum('balD'), 'balK' => $rows->sum('balK')]]);
    }

    // ---- Jenis Transaksi Kasir (pemetaan jenis -> akun) --------------------------------

    public function types(): View
    {
        $types = TransactionType::orderBy('category')->orderBy('sort')->orderBy('name')->withCount('transactions')->get();
        $eligible = collect(TransactionType::CATEGORIES)->mapWithKeys(fn ($c) => [$c => TransactionType::eligibleAccounts($c)])->all();
        return view('accounting.types', compact('types', 'eligible'));
    }

    public function storeType(Request $request): RedirectResponse
    {
        // Tanpa akuntan: akun tidak dipilih manusia, otomatis "lain-lain" (penerimaan 49101, pengeluaran 59101).
        if (! Setting::hasAccountant()) {
            $request->merge(['account_code' => ['Penerimaan' => '49101', 'Pengeluaran' => '59101'][$request->input('category')] ?? null]);
        }
        $data = $request->validate([
            'category' => ['required', Rule::in(TransactionType::CATEGORIES)],
            'name' => ['required', 'string', 'max:60', Rule::unique('transaction_types', 'name')->where('category', $request->input('category'))],
            'account_code' => ['required', Rule::in(array_keys(TransactionType::eligibleAccounts((string) $request->input('category'))))],
        ], $this->typeMessages(), ['category' => 'kategori', 'name' => 'nama jenis', 'account_code' => 'akun']);

        TransactionType::create($data + ['sort' => (int) TransactionType::where('category', $data['category'])->max('sort') + 1]);
        return back()->with('success', "Jenis transaksi \"{$data['name']}\" ditambahkan.");
    }

    // Perubahan akun hanya berlaku untuk transaksi berikutnya; jurnal yang sudah dibuat tidak berubah.
    public function updateType(Request $request, TransactionType $type): RedirectResponse
    {
        if (! Setting::hasAccountant()) {
            $request->merge(['account_code' => $type->account_code]); // tanpa akuntan: pemetaan akun tidak diubah dari sini
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('transaction_types', 'name')->where('category', $type->category)->ignore($type->id)],
            'account_code' => ['required', Rule::in(array_keys(TransactionType::eligibleAccounts($type->category)))],
        ], $this->typeMessages(), ['name' => 'nama jenis', 'account_code' => 'akun']);

        $type->update($data + ['active' => $request->boolean('active')]);
        return back()->with('success', "Jenis transaksi \"{$type->name}\" diperbarui.");
    }

    private function typeMessages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.', 'in' => ':Attribute tidak valid untuk kategori ini (penerimaan memakai akun pendapatan, pengeluaran memakai akun beban).',
            'max' => ':Attribute terlalu panjang (maksimal :max karakter).', 'unique' => 'Jenis dengan nama itu sudah ada pada kategori ini.',
        ];
    }

    // ---- Jurnal Manual ----------------------------------------------------------------

    public function createManual(): View
    {
        return view('accounting.manual', ['accounts' => Coa::manual()]);
    }

    public function storeManual(Request $request): RedirectResponse
    {
        // Nominal berformat rupiah ("1.500.000"): ambil angkanya; baris yang sama sekali kosong dibuang.
        $lines = collect($request->input('lines', []))
            ->map(fn ($l) => ['account' => $l['account'] ?? null, 'debit' => preg_replace('/\D/', '', (string) ($l['debit'] ?? '')), 'credit' => preg_replace('/\D/', '', (string) ($l['credit'] ?? ''))])
            ->filter(fn ($l) => $l['account'] || $l['debit'] !== '' || $l['credit'] !== '')->values()->all();
        $request->merge(['lines' => $lines]);

        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'keterangan' => ['required', 'string', 'max:200'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account' => ['required', Rule::in(array_keys(Coa::manual()))],
            'lines.*.debit' => ['nullable', 'numeric', 'min:1', 'max:100000000000'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:1', 'max:100000000000'],
        ], [
            'required' => ':Attribute wajib diisi.',
            'lines.min' => 'Jurnal minimal terdiri dari dua baris.',
            'lines.required' => 'Jurnal minimal terdiri dari dua baris.',
            'lines.*.account.in' => 'Akun tidak bisa dijurnal manual (kas, piutang pinjaman, dan simpanan anggota hanya lewat menu Kasir, Pinjaman, dan Simpanan).',
            'numeric' => 'Nominal harus berupa angka.',
            'min' => 'Nominal minimal Rp 1.',
            'max' => 'Nominal terlalu besar.',
            'before_or_equal' => 'Tanggal jurnal tidak boleh di masa depan.',
            'date' => 'Format tanggal tidak valid.',
        ], ['tanggal' => 'tanggal', 'keterangan' => 'keterangan', 'lines.*.account' => 'akun']);

        $lines = collect($data['lines']);
        $both = $lines->first(fn ($l) => ($l['debit'] ?? '') !== '' && ($l['credit'] ?? '') !== '');
        $none = $lines->first(fn ($l) => ($l['debit'] ?? '') === '' && ($l['credit'] ?? '') === '');
        if ($both || $none) {
            throw ValidationException::withMessages(['lines' => 'Setiap baris harus berisi debit atau kredit, salah satu saja.']);
        }
        $debit = $lines->sum(fn ($l) => (float) ($l['debit'] ?? 0));
        $credit = $lines->sum(fn ($l) => (float) ($l['credit'] ?? 0));
        if (round($debit, 2) !== round($credit, 2)) {
            throw ValidationException::withMessages(['lines' => 'Jurnal tidak seimbang: debit Rp '.number_format($debit, 0, ',', '.').', kredit Rp '.number_format($credit, 0, ',', '.').'.']);
        }

        $journal = DB::transaction(function () use ($data, $lines) {
            $journal = JournalEntry::create([
                'journal_no' => 'JM-'.now()->format('ymdHis').'-'.Str::upper(Str::random(3)), 'journal_date' => $data['tanggal'],
                'reference' => null, 'description' => $data['keterangan'], 'source' => 'MANUAL', 'status' => 'Posted',
            ]);
            foreach ($lines as $l) {
                $journal->lines()->create(['account_code' => $l['account'], 'account_name' => Coa::name($l['account']), 'debit' => (float) ($l['debit'] ?? 0), 'credit' => (float) ($l['credit'] ?? 0)]);
            }
            return $journal;
        });

        return redirect()->route('accounting.index')->with('success', "Jurnal manual {$journal->journal_no} tersimpan.");
    }
}
