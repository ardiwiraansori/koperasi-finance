<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function index(): View
    {
        $loans = Loan::with('member')->latest('applied_at')->get();
        return view('loans.index', compact('loans'));
    }
}
