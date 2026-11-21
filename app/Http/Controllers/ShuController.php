<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Saving;
use App\Services\Reports;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Simulasi Sisa Hasil Usaha (SHU) dari jurnal. Belum dijurnal: rumus final mengikuti AD/ART dan keputusan RAT koperasi.
class ShuController extends Controller
{
    // Alokasi SHU menurut keputusan koperasi (jumlahnya 100%); persentase boleh desimal dan bisa diubah di halaman SHU.
    // Dana anggota penyimpan dibagi sesuai simpanan (jasa modal), dana anggota berjasa sesuai bunga yang dibayar (jasa usaha).
    // Pos lainnya hanya ditampilkan sebagai angka, belum dibagi ke individu.
    public const DEFAULTS = ['cadangan' => 25, 'jasa_modal' => 15, 'jasa_usaha' => 15, 'pengurus' => 20, 'pengelola' => 10, 'pendidikan' => 5, 'sarana' => 7.5, 'sosial' => 2.5];
    public const LABELS = [
        'cadangan' => 'Dana Cadangan',
        'jasa_modal' => 'Dana Anggota: Penyimpan',
        'jasa_usaha' => 'Dana Anggota: Berjasa',
        'pengurus' => 'Pengurus & Pengawas',
        'pengelola' => 'Pengelola Koperasi',
        'pendidikan' => 'Pendidikan',
        'sarana' => 'Sarana dan Prasarana',
        'sosial' => 'Dana Sosial',
    ];

    public function index(Request $request): View|StreamedResponse
    {
        $rules = ['tahun' => ['nullable', 'integer', 'between:2000,2100']];
        foreach (self::DEFAULTS as $key => $default) {
            $rules[$key] = ['nullable', 'numeric', 'between:0,100'];
        }
        $request->validate($rules, ['integer' => ':Attribute harus berupa angka bulat.', 'numeric' => ':Attribute harus berupa angka.', 'between' => ':Attribute harus antara :min dan :max.'], ['tahun' => 'tahun']);

        $year = (int) ($request->input('tahun') ?: now()->year);
        $pct = collect(self::DEFAULTS)->map(fn($d, $k) => round((float) ($request->input($k) ?? $d), 2));
        if (abs($pct->sum() - 100) > 0.001) {
            throw ValidationException::withMessages(['persen' => 'Jumlah persentase pembagian harus 100% (sekarang ' . round($pct->sum(), 2) . '%).']);
        }

        $result = Reports::incomeStatement("$year-01-01", "$year-12-31");
        [$income, $expense] = [$result['totalIncome'], $result['totalExpense']];
        $shu = $income - $expense;
        $pool = $pct->map(fn($p) => $shu > 0 ? round($shu * $p / 100) : 0);

        // Jasa modal memakai saldo simpanan pada akhir tahun buku; tahun berjalan memakai saldo hari ini.
        $asOf = min("$year-12-31", today()->toDateString());
        $savings = $this->savingsBalanceByMember($asOf);
        $interest = $this->interestPaidByMember($year);
        $totalSavings = (float) $savings->sum();
        $totalInterest = (float) $interest->sum();

        $members = Member::orderBy('member_no')->get()
            ->filter(fn($m) => ($savings[$m->id] ?? 0) > 0 || ($interest[$m->id] ?? 0) > 0)
            ->map(function ($m) use ($savings, $interest, $pool, $totalSavings, $totalInterest) {
                $s = (float) ($savings[$m->id] ?? 0);
                $i = (float) ($interest[$m->id] ?? 0);
                $capital = $totalSavings > 0 ? round($pool['jasa_modal'] * $s / $totalSavings) : 0;
                $business = $totalInterest > 0 ? round($pool['jasa_usaha'] * $i / $totalInterest) : 0;
                return ['member' => $m, 'savings' => $s, 'interest' => $i, 'capital' => $capital, 'business' => $business, 'total' => $capital + $business];
            })->sortByDesc('total')->values();

        if ($request->boolean('export')) {
            return Csv::download(
                "shu-$year.csv",
                ['No Anggota', 'Nama', 'Simpanan', 'Bunga Dibayar', 'SHU Jasa Modal', 'SHU Jasa Usaha', 'Total SHU'],
                $members->map(fn($r) => [$r['member']->member_no, $r['member']->name, $r['savings'], $r['interest'], $r['capital'], $r['business'], $r['total']])
            );
        }

        $years = DB::table('journal_entries')->pluck('journal_date')->map(fn($d) => (int) substr((string) $d, 0, 4))->push(now()->year, $year)->unique()->sortDesc()->values();

        return view('shu.index', [
            'year' => $year,
            'asOf' => $asOf,
            'years' => $years,
            'pct' => $pct,
            'pool' => $pool,
            'income' => $income,
            'expense' => $expense,
            'shu' => $shu,
            'members' => $members,
            'totalSavings' => $totalSavings,
            'totalInterest' => $totalInterest,
            'distributed' => ['capital' => $members->sum('capital'), 'business' => $members->sum('business')],
        ]);
    }

    // Saldo simpanan (Pokok, Wajib, Sukarela) tiap anggota sampai tanggal itu, dari jurnal: kredit dikurangi debit akun simpanannya.
    // Dari jurnal, bukan dari saldo rekening, supaya saldo akhir tahun lalu tetap benar dan sama dengan Neraca.
    private function savingsBalanceByMember(string $asOf)
    {
        return DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('transactions as t', 't.trx_no', '=', 'e.reference')
            ->where('e.status', 'Posted')->whereIn('l.account_code', array_column(Saving::ACCOUNTS, 0))->whereNotNull('t.member_id')
            ->where('e.journal_date', '<=', "$asOf 23:59:59") // jam akhir hari: jurnal bertanggal tepat $asOf ikut, baik tersimpan sebagai tanggal maupun waktu
            ->groupBy('t.member_id')->selectRaw('t.member_id as member_id, SUM(l.credit - l.debit) as total')->pluck('total', 'member_id');
    }

    // Bunga pinjaman yang dibayar tiap anggota pada tahun itu: kredit Pendapatan Bunga (41101) dari jurnal angsuran miliknya.
    private function interestPaidByMember(int $year)
    {
        return DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('transactions as t', 't.trx_no', '=', 'e.reference')
            ->where('e.status', 'Posted')->where('l.account_code', '41101')->whereNotNull('t.member_id')
            ->whereBetween('e.journal_date', ["$year-01-01", "$year-12-31"])
            ->groupBy('t.member_id')->selectRaw('t.member_id as member_id, SUM(l.credit - l.debit) as interest')->pluck('interest', 'member_id');
    }
}
