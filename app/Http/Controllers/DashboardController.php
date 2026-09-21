<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Saving;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'members' => Member::where('status', 'Aktif')->count(),
            'savings' => Saving::sum('balance'),
            'loans' => Loan::whereIn('status', ['Aktif','Tunggakan'])->sum('outstanding'),
            'cash' => 845000000,
            'income' => Transaction::where('direction', 'in')->whereMonth('trx_date', now()->month)->sum('amount'),
            'arrears' => Loan::where('status', 'Tunggakan')->sum('outstanding'),
        ];
        $transactions = Transaction::with('member')->latest('trx_date')->limit(7)->get();
        $loans = Loan::with('member')->orderByRaw("FIELD(status, 'Tunggakan', 'Menunggu Approval', 'Aktif', 'Lunas')")->limit(5)->get();
        return view('dashboard.index', compact('stats', 'transactions', 'loans'));
    }
}
