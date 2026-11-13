<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Saving;
use App\Services\Reports;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    // ---- Neraca -----------------------------------------------------------------------

    public function balanceSheet(Request $request): View|StreamedResponse
    {
        $request->validate(['tanggal' => ['nullable', 'date']], ['date' => 'Format tanggal tidak valid.']);
        $date = Carbon::parse($request->input('tanggal', now()->toDateString()))->toDateString();
        $compare = $request->boolean('bandingkan');
        $prevDate = Carbon::parse($date)->subYear()->endOfYear()->toDateString(); // akhir tahun sebelumnya

        $now = Reports::balanceSheet($date);
        $prev = $compare ? Reports::balanceSheet($prevDate) : null;

        if ($request->boolean('export')) {
            $rows = [];
            foreach (['Aset' => 'assets', 'Kewajiban' => 'liabilities', 'Ekuitas' => 'equity'] as $label => $key) {
                foreach ($this->merge($now[$key], $prev[$key] ?? collect()) as $r) {
                    $rows[] = array_merge([$label, $r['code'], $r['name'], $r['a']], $compare ? [$r['b']] : []);
                }
            }
            $rows[] = ['Ekuitas', '', 'SHU Tahun Berjalan', $now['shuCurrent']] + ($compare ? [4 => $prev['shuCurrent']] : []);
            $rows[] = ['Ekuitas', '', 'SHU Tahun Sebelumnya (belum dibagi)', $now['shuPrior']] + ($compare ? [4 => $prev['shuPrior']] : []);
            return Csv::download("neraca-$date.csv", array_merge(['Kelompok', 'Kode', 'Akun', "Per $date"], $compare ? ["Per $prevDate"] : []), $rows);
        }

        return view('reports.balance-sheet', [
            'date' => $date, 'prevDate' => $prevDate, 'compare' => $compare, 'now' => $now, 'prev' => $prev,
            'lines' => [
                'Aset' => $this->merge($now['assets'], $prev['assets'] ?? collect()),
                'Kewajiban' => $this->merge($now['liabilities'], $prev['liabilities'] ?? collect()),
                'Ekuitas' => $this->merge($now['equity'], $prev['equity'] ?? collect()),
            ],
            'reconciliation' => $date === now()->toDateString() ? $this->reconciliation($date) : null,
        ]);
    }

    // Saldo akun di jurnal harus sama dengan data rekening simpanan dan pinjaman. Selisih biasanya berarti saldo awal belum diisi.
    private function reconciliation(string $date): array
    {
        $totals = Reports::totals(null, $date);
        $journal = fn (string $code, int $sign) => $sign * ((float) ($totals[$code]['debit'] ?? 0) - (float) ($totals[$code]['credit'] ?? 0));
        $sub = fn (string $type) => (float) Saving::where('type', $type)->sum('balance');

        return collect([
            ['Simpanan Pokok', '31101', $journal('31101', -1), $sub('Pokok')],
            ['Simpanan Wajib', '31102', $journal('31102', -1), $sub('Wajib')],
            ['Simpanan Sukarela', '20201', $journal('20201', -1), $sub('Sukarela')],
            ['Piutang Pinjaman', '11301', $journal('11301', 1), (float) Loan::whereIn('status', Loan::RUNNING)->sum('outstanding')],
        ])->map(fn ($r) => ['name' => $r[0], 'code' => $r[1], 'ledger' => $r[2], 'sub' => $r[3], 'diff' => round($r[2] - $r[3], 2)])->all();
    }

    // ---- Laba Rugi --------------------------------------------------------------------

    public function incomeStatement(Request $request): View|StreamedResponse
    {
        $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ], ['sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.', 'date' => 'Format tanggal tidak valid.']);

        $from = Carbon::parse($request->input('dari', now()->startOfYear()->toDateString()))->toDateString();
        $to = Carbon::parse($request->input('sampai', now()->toDateString()))->toDateString();
        $compare = $request->boolean('bandingkan');

        // Periode pembanding = periode sebelumnya dengan lama yang sama, tepat sebelum periode ini.
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $prevTo = Carbon::parse($from)->subDay()->toDateString();
        $prevFrom = Carbon::parse($prevTo)->subDays($days - 1)->toDateString();

        $now = Reports::incomeStatement($from, $to);
        $prev = $compare ? Reports::incomeStatement($prevFrom, $prevTo) : null;

        if ($request->boolean('export')) {
            $rows = [];
            foreach (['Pendapatan' => 'income', 'Beban' => 'expense'] as $label => $key) {
                foreach ($this->merge($now[$key], $prev[$key] ?? collect()) as $r) {
                    $rows[] = array_merge([$label, $r['code'], $r['name'], $r['a']], $compare ? [$r['b']] : []);
                }
            }
            $rows[] = ['', '', 'Sisa Hasil Usaha (SHU)', $now['shu']] + ($compare ? [4 => $prev['shu']] : []);
            return Csv::download("laba-rugi-$from-$to.csv", array_merge(['Kelompok', 'Kode', 'Akun', "$from s/d $to"], $compare ? ["$prevFrom s/d $prevTo"] : []), $rows);
        }

        return view('reports.income-statement', [
            'from' => $from, 'to' => $to, 'prevFrom' => $prevFrom, 'prevTo' => $prevTo, 'compare' => $compare, 'now' => $now, 'prev' => $prev,
            'lines' => ['Pendapatan' => $this->merge($now['income'], $prev['income'] ?? collect()), 'Beban' => $this->merge($now['expense'], $prev['expense'] ?? collect())],
        ]);
    }

    // Gabungkan baris periode ini (a) dan pembanding (b) per kode akun.
    private function merge($current, $previous): array
    {
        $rows = [];
        foreach ($current as $r) {
            $rows[$r['code']] = ['code' => $r['code'], 'name' => $r['name'], 'a' => $r['amount'], 'b' => 0.0];
        }
        foreach ($previous as $r) {
            $rows[$r['code']] = ['code' => $r['code'], 'name' => $r['name'], 'a' => $rows[$r['code']]['a'] ?? 0.0, 'b' => $r['amount']];
        }
        ksort($rows);
        return array_values($rows);
    }
}
