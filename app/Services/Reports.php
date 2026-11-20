<?php

namespace App\Services;

use App\Support\Coa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Laporan keuangan dari jurnal yang sudah Posted. Pembukuan belum "ditutup" per tahun, jadi SHU tahun-tahun
// sebelumnya tetap dihitung dari jurnal dan tampil di Ekuitas sebagai SHU yang belum dibagi.
class Reports
{
    /** @return Collection<string,array{code:string,name:string,debit:float,credit:float}> total debit/kredit per akun, kunci = kode akun */
    public static function totals(?string $from, string $to): Collection
    {
        return DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.status', 'Posted')->whereDate('e.journal_date', '<=', $to)
            ->when($from, fn ($q) => $q->whereDate('e.journal_date', '>=', $from))
            ->groupBy('l.account_code')->orderBy('l.account_code')
            ->selectRaw('l.account_code as code, MIN(l.account_name) as name, SUM(l.debit) as debit, SUM(l.credit) as credit')->get()
            ->mapWithKeys(fn ($r) => [$r->code => ['code' => $r->code, 'name' => Coa::ACCOUNTS[$r->code][0] ?? $r->name, 'debit' => (float) $r->debit, 'credit' => (float) $r->credit]]);
    }

    // Saldo sebuah golongan akun (kepala kode 1..5): aset dan beban menambah di debit, lainnya di kredit.
    private static function group(Collection $totals, string $prefix): Collection
    {
        $sign = in_array($prefix, ['1', '5'], true) ? 1 : -1;
        return $totals->filter(fn ($t) => str_starts_with($t['code'], $prefix))
            ->map(fn ($t) => ['code' => $t['code'], 'name' => $t['name'], 'amount' => $sign * ($t['debit'] - $t['credit'])])->values();
    }

    /** @return array{income:Collection,expense:Collection,totalIncome:float,totalExpense:float,shu:float} */
    public static function incomeStatement(string $from, string $to): array
    {
        $totals = self::totals($from, $to);
        $income = self::group($totals, '4')->filter(fn ($r) => abs($r['amount']) > 0.004)->values();
        $expense = self::group($totals, '5')->filter(fn ($r) => abs($r['amount']) > 0.004)->values();

        return ['income' => $income, 'expense' => $expense, 'totalIncome' => (float) $income->sum('amount'), 'totalExpense' => (float) $expense->sum('amount'),
            'shu' => (float) ($income->sum('amount') - $expense->sum('amount'))];
    }

    // Akun yang menentukan kegiatan sebuah arus kas. Selain ini = operasi (pinjaman, pendapatan, beban, utang usaha, perlengkapan).
    private const FINANCING = ['31101', '31102', '31201', '20201'];   // simpanan anggota dan cadangan
    private const INVESTING = ['12201', '12301'];                      // aset tetap

    /**
     * Laporan arus kas (metode langsung) dari jurnal Posted yang menyentuh Kas Teller. Tiap akun lawan di jurnal itu
     * menjadi satu baris: bertambahnya kredit = kas masuk, bertambahnya debit = kas keluar. Jumlah semua baris selalu
     * sama dengan perubahan kas. Saldo awal pembukuan (akun 39101) dipisah karena bukan kegiatan.
     * shortcut: semua baris periode dimuat ke memori; cukup untuk koperasi kecil, ganti dengan agregasi SQL bila sudah ratusan ribu baris.
     *
     * @return array{sections:array,net:float,opening:float,closing:float,ledgerClosing:float,matches:bool}
     */
    public static function cashFlow(string $from, string $to): array
    {
        $cash = Ledger::KAS[0];
        $posted = fn () => DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->where('e.status', 'Posted');
        $cashUntil = fn (string $op, string $date) => (float) $posted()->where('l.account_code', $cash)->whereDate('e.journal_date', $op, $date)->sum(DB::raw('l.debit - l.credit'));

        $lines = $posted()->whereDate('e.journal_date', '>=', $from)->whereDate('e.journal_date', '<=', $to)->where('l.account_code', '!=', $cash)
            ->whereIn('l.journal_entry_id', fn ($q) => $q->select('journal_entry_id')->from('journal_lines')->where('account_code', $cash))
            ->get(['l.account_code as code', 'l.debit', 'l.credit']);

        $totals = [];
        foreach ($lines as $l) {
            $group = $l->code === '39101' ? 'opening' : (in_array($l->code, self::FINANCING, true) ? 'financing' : (in_array($l->code, self::INVESTING, true) ? 'investing' : 'operating'));
            $amount = (float) $l->credit - (float) $l->debit;
            $totals[$group][$l->code][$amount >= 0 ? 'in' : 'out'] = ($totals[$group][$l->code][$amount >= 0 ? 'in' : 'out'] ?? 0.0) + $amount;
        }

        $sections = [];
        foreach (['operating' => 'Aktivitas Operasional', 'investing' => 'Aktivitas Investasi', 'financing' => 'Aktivitas Pendanaan', 'opening' => 'Saldo Awal Pembukuan'] as $key => $title) {
            $rows = [];
            foreach ($totals[$key] ?? [] as $code => $dirs) {
                foreach ($dirs as $dir => $amount) {
                    if (abs($amount) > 0.004) {
                        $rows[] = ['label' => self::cashLabel($code, $dir), 'amount' => round($amount, 2), 'sort' => $code.($dir === 'in' ? '0' : '1')];
                    }
                }
            }
            usort($rows, fn ($a, $b) => $a['sort'] <=> $b['sort']);
            $sections[$key] = ['title' => $title, 'rows' => $rows, 'total' => round(array_sum(array_column($rows, 'amount')), 2)];
        }

        $net = round(array_sum(array_column($sections, 'total')), 2);
        $opening = round($cashUntil('<', $from), 2);
        $ledgerClosing = round($cashUntil('<=', $to), 2);

        return ['sections' => $sections, 'net' => $net, 'opening' => $opening, 'closing' => round($opening + $net, 2), 'ledgerClosing' => $ledgerClosing,
            'matches' => round($opening + $net, 2) === $ledgerClosing];
    }

    private static function cashLabel(string $code, string $dir): string
    {
        $name = Coa::name($code);
        return match (true) {
            $code === '11301' => $dir === 'in' ? 'Penerimaan angsuran pokok pinjaman' : 'Pencairan pinjaman kepada anggota',
            $code === '39101' => 'Saldo kas awal yang dimasukkan',
            in_array($code, self::FINANCING, true) => ($dir === 'in' ? 'Setoran ' : 'Penarikan ').$name,
            default => ($dir === 'in' ? 'Penerimaan: ' : 'Pembayaran: ').$name,
        };
    }

    /** @return array{assets:Collection,liabilities:Collection,equity:Collection,totalAssets:float,totalLiabilities:float,totalEquity:float,shuCurrent:float,shuPrior:float,balanced:bool} */
    public static function balanceSheet(string $date): array
    {
        $totals = self::totals(null, $date);
        $yearStart = substr($date, 0, 4).'-01-01';

        $assets = self::group($totals, '1')->filter(fn ($r) => abs($r['amount']) > 0.004)->values();
        $liabilities = self::group($totals, '2')->filter(fn ($r) => abs($r['amount']) > 0.004)->values();
        $equity = self::group($totals, '3')->filter(fn ($r) => abs($r['amount']) > 0.004)->values();

        // SHU sejak awal pembukuan = pendapatan - beban; dipecah: tahun berjalan dan tahun-tahun sebelumnya (belum ditutup/dibagi).
        $all = self::group($totals, '4')->sum('amount') - self::group($totals, '5')->sum('amount');
        $current = self::incomeStatement($yearStart, $date)['shu'];

        $totalAssets = (float) $assets->sum('amount');
        $totalLiabilities = (float) $liabilities->sum('amount');
        $totalEquity = (float) $equity->sum('amount') + $all;

        return ['assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'shuCurrent' => (float) $current, 'shuPrior' => (float) ($all - $current),
            'totalAssets' => $totalAssets, 'totalLiabilities' => $totalLiabilities, 'totalEquity' => $totalEquity,
            'balanced' => round($totalAssets, 2) === round($totalLiabilities + $totalEquity, 2)];
    }
}
