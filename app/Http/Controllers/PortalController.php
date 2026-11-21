<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

// Portal anggota: hanya data milik anggota yang sedang login. Tidak menerima id dari URL, jadi tidak bisa membuka data orang lain.
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
        ]);
    }
}
