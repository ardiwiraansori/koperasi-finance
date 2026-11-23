<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Saving;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Pencarian global (ikon ⌕): anggota, rekening simpanan, pinjaman, dan transaksi.
class SearchController extends Controller
{
    private const LIMIT = 8;

    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:60']]);
        $q = trim((string) $request->input('q'));
        $results = null;

        if (mb_strlen($q) >= 2) {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $results = [
                'members' => Member::where(fn($w) => $w->where('name', 'like', $like)->orWhere('member_no', 'like', $like)->orWhere('nik', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like))
                    ->orderBy('name')->limit(self::LIMIT)->get(),
                'savings' => Saving::with('member')->where(fn($w) => $w->where('account_no', 'like', $like)->orWhereHas('member', fn($m) => $m->where('name', 'like', $like)))
                    ->orderBy('account_no')->limit(self::LIMIT)->get(),
                'loans' => Loan::with('member')->where(fn($w) => $w->where('loan_no', 'like', $like)->orWhereHas('member', fn($m) => $m->where('name', 'like', $like)))
                    ->latest('applied_at')->limit(self::LIMIT)->get(),
                'transactions' => Transaction::cashOnly()->with('member')->where(fn($w) => $w->where('trx_no', 'like', $like)->orWhere('description', 'like', $like))
                    ->latest('trx_date')->limit(self::LIMIT)->get(),
            ];
        }

        return view('search.index', ['q' => $q, 'results' => $results, 'total' => $results ? collect($results)->sum(fn($r) => $r->count()) : 0]);
    }
}
