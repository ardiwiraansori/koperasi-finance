<?php
namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public function index(): View
    {
        $journals = JournalEntry::with('lines')->latest('journal_date')->get();
        return view('accounting.index', compact('journals'));
    }
}
