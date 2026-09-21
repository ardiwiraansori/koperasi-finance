<?php
namespace App\Http\Controllers;

use App\Models\Saving;
use Illuminate\View\View;

class SavingsController extends Controller
{
    public function index(): View
    {
        $savings = Saving::with('member')->orderByDesc('balance')->get();
        $summary = $savings->groupBy('type')->map(fn ($rows) => $rows->sum('balance'));
        return view('savings.index', compact('savings', 'summary'));
    }
}
