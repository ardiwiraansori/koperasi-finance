<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Saving;
use Illuminate\View\View;

// Portal anggota: data pribadi hanya milik anggota yang sedang login (tidak menerima id dari URL). Kartu "Kondisi Koperasi" hanya angka
// gabungan seluruh koperasi: jumlah dan total, tanpa nama atau data anggota lain.
class PortalController extends Controller
{
    public function index(): View
    {
        $member = auth()->user()->member->load(['savings', 'loans' => fn($q) => $q->latest('applied_at')]);
        $transactions = $member->transactions()->cashOnly()->latest('trx_date')->limit(10)->get();
        $running = $member->loans->filter(fn($l) => in_array($l->status, ['Aktif', 'Tunggakan'], true));

        return view('portal.index', [
            'member' => $member,
            'transactions' => $transactions,
            'running' => $running,
            'next' => $running->whereNotNull('next_due_at')->sortBy('next_due_at')->first(),
            'coop' => $this->cooperativeSummary(),
        ]);
    }

    // Hanya kolom yang dipakai untuk menjumlah dimuat (tanpa anggota), dan hanya angka gabungan yang diteruskan ke tampilan.
    private function cooperativeSummary(): array
    {
        $running = Loan::whereIn('status', Loan::RUNNING)->get(['id', 'status', 'outstanding', 'next_due_at']);
        $overdue = $running->filter(fn(Loan $l) => $l->daysOverdue() > 0);
        $outstanding = (float) $running->sum('outstanding');
        $arrears = (float) $overdue->sum('outstanding');

        // Angka kualitas sama dengan Dashboard petugas, tetapi kalimat penilaiannya untuk anggota: netral, bukan perintah kepada petugas.
        $quality = Loan::quality($running);
        $quality['verdict'] = match (true) {
            $quality['score'] >= 90 => 'Pinjaman koperasi berjalan lancar.',
            $quality['score'] >= 75 => 'Sebagian kecil pinjaman terlambat dibayar.',
            default => 'Cukup banyak pinjaman terlambat dibayar.',
        };

        return [
            'savings' => (float) Saving::sum('balance'),
            'loans' => $outstanding,
            'loanCount' => $running->count(),
            'arrears' => $arrears,
            'arrearsCount' => $overdue->count(),
            'arrearsPct' => $outstanding > 0 ? round($arrears / $outstanding * 100, 1) : 0,
            'quality' => $quality,
        ];
    }
}
