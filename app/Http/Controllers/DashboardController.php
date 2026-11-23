<?php

namespace App\Http\Controllers;

use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Saving;
use App\Models\Transaction;
use App\Support\Alerts;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const CHART_MONTHS = 8;

    public function index(): View
    {
        $running = Loan::with('member')->whereIn('status', Loan::RUNNING)->get();
        $overdue = $running->filter(fn(Loan $l) => $l->daysOverdue() > 0);
        $month = fn($q) => $q->whereYear('trx_date', now()->year)->whereMonth('trx_date', now()->month);

        $stats = [
            'members' => Member::where('status', 'Aktif')->count(),
            'newMembers' => Member::whereYear('joined_at', now()->year)->whereMonth('joined_at', now()->month)->count(),
            'savings' => Saving::sum('balance'),
            // Selisih setoran dan penarikan simpanan bulan ini.
            'savingsNet' => $month(Transaction::where('category', 'Simpanan')->where('direction', 'in'))->sum('amount')
                - $month(Transaction::where('category', 'Simpanan')->where('direction', 'out'))->sum('amount'),
            'loans' => $running->sum('outstanding'),
            'loanCount' => $running->count(),
            'arrears' => $overdue->sum('outstanding'),
            'arrearsCount' => $overdue->count(),
            'cash' => JournalLine::cashBalance(),
            // Kas masuk bulan ini; saldo awal kas bukan pemasukan, jadi tidak dihitung.
            'income' => $month(Transaction::operating()->where('direction', 'in'))->sum('amount'),
        ];

        $queue = [
            'pending' => Alerts::loanCount('Menunggu Approval'),
            'approved' => Alerts::loanCount('Disetujui'),
        ];

        $transactions = Transaction::cashOnly()->with('member')->latest('trx_date')->latest('id')->limit(7)->get();

        return view('dashboard.index', [
            'stats' => $stats,
            'queue' => $queue,
            'transactions' => $transactions,
            'chart' => $this->cashFlowChart(),
            'quality' => Loan::quality($running),
            'greeting' => match (true) {
                now()->hour < 11 => 'Selamat pagi',
                now()->hour < 15 => 'Selamat siang',
                now()->hour < 18 => 'Selamat sore',
                default => 'Selamat malam',
            },
        ]);
    }

    // Kas masuk dan keluar per bulan (8 bulan terakhir) sebagai jalur SVG 760x260. Saldo awal kas tidak dihitung.
    private function cashFlowChart(): array
    {
        $start = now()->startOfMonth()->subMonths(self::CHART_MONTHS - 1);
        $rows = Transaction::operating()->where('trx_date', '>=', $start)->get(['trx_date', 'direction', 'amount']);

        $labels = $in = $out = [];
        for ($i = 0; $i < self::CHART_MONTHS; $i++) {
            $m = $start->copy()->addMonths($i);
            $inMonth = fn($t) => $t->trx_date->format('Y-m') === $m->format('Y-m');
            $labels[] = $m->translatedFormat('M');
            $in[] = (float) $rows->filter(fn($t) => $inMonth($t) && $t->direction === 'in')->sum('amount');
            $out[] = (float) $rows->filter(fn($t) => $inMonth($t) && $t->direction === 'out')->sum('amount');
        }

        $max = max(1, ...$in, ...$out);
        $x = fn(int $i) => round(44 + $i * (740 - 44) / (self::CHART_MONTHS - 1), 1);
        $y = fn(float $v) => round(220 - $v / $max * 180, 1);
        $line = fn(array $vals) => 'M' . implode(' L', array_map(fn($v, $i) => $x($i) . ' ' . $y($v), $vals, array_keys($vals)));

        return [
            'labels' => $labels,
            'x' => array_map($x, array_keys($labels)),
            'max' => $max,
            'incomeLine' => $line($in),
            'expenseLine' => $line($out),
            'incomeArea' => $line($in) . ' L740 220 L44 220 Z',
            'hasData' => array_sum($in) + array_sum($out) > 0,
        ];
    }

}
