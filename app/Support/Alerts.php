<?php

namespace App\Support;

use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

// Pemberitahuan yang perlu ditindaklanjuti, dihitung dari data (bukan angka tetap) dan disaring menurut peran pengguna.
class Alerts
{
    // Jumlah pinjaman per status: satu query per permintaan, dipakai bersama oleh menu, lonceng, dan Dashboard.
    // Disimpan di objek request (bukan variabel statis) agar tidak basi antar permintaan.
    private static function statusCounts(): Collection
    {
        $bag = request()->attributes;
        if (! $bag->has('loan.status.counts')) {
            $bag->set('loan.status.counts', Loan::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'));
        }
        return $bag->get('loan.status.counts');
    }

    public static function loanCount(string $status): int
    {
        return (int) (self::statusCounts()[$status] ?? 0);
    }

    // Pengajuan yang menunggu persetujuan admin (angka di menu Approval).
    public static function waitingApprovals(): int
    {
        return self::loanCount('Menunggu Approval');
    }

    // Pinjaman disetujui yang menunggu kasir menyerahkan uangnya (angka di menu Pencairan).
    public static function waitingDisbursements(): int
    {
        return self::loanCount('Disetujui');
    }

    /** @return array<int,array{text:string,url:string,level:string}> level: danger | warning | info */
    public static function for(User $user): array
    {
        $items = [];
        // function + use (&$items), bukan fn: fungsi panah menangkap variabel menurut nilai sehingga daftar tidak pernah terisi.
        $add = function (string $level, string $text, string $url) use (&$items) {
            $items[] = ['level' => $level, 'text' => $text, 'url' => $url];
        };

        if ($user->allows('loan.decide')) {
            $pending = self::loanCount('Menunggu Approval');
            $approved = self::loanCount('Disetujui');
            $pending && $add('info', "$pending pengajuan pinjaman menunggu persetujuan", route('approval.index'));
            $approved && $add('info', "$approved pinjaman disetujui, siap dicairkan", route('approval.index'));
        }
        if ($user->allows('loan.disburse') && ($approved = self::loanCount('Disetujui'))) {
            $add('info', "$approved pinjaman disetujui, siap dicairkan", route('disbursement.index'));
        }

        // Pinjaman berjalan hanya dimuat bila memang ada (jumlahnya sudah diketahui dari hitungan status).
        if (self::loanCount('Aktif') + self::loanCount('Tunggakan') > 0) {
            $running = Loan::whereIn('status', Loan::RUNNING)->get(['id', 'status', 'next_due_at']);
            $overdue = $running->filter(fn (Loan $l) => $l->daysOverdue() > 0);
            $overdue->isNotEmpty() && $add('danger', $overdue->count().' pinjaman menunggak (terlama '.$overdue->max(fn (Loan $l) => $l->daysOverdue()).' hari)', route('loans.index'));

            $soon = $running->filter(fn (Loan $l) => $l->next_due_at && $l->next_due_at->betweenIncluded(today(), today()->addDays(7)));
            $soon->isNotEmpty() && $add('warning', $soon->count().' angsuran jatuh tempo dalam 7 hari', route('loans.index'));
        }

        // Petunjuk awal untuk pengelola: kas belum punya saldo awal.
        if ($user->allows('cash') && $user->allows('opening') && ! Transaction::where('category', 'Saldo Awal')->exists() && JournalLine::cashBalance() == 0.0) {
            $add('info', 'Saldo awal kas belum dicatat (Kasir → Saldo Awal Kas)', route('teller.index'));
        }

        return $items;
    }
}
