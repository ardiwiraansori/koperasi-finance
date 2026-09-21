<?php
namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(): View
    {
        $members = Member::withSum('savings', 'balance')->withSum('loans', 'outstanding')->orderBy('member_no')->get();
        return view('members.index', compact('members'));
    }

    public function show(Member $member): View
    {
        $member->load(['savings', 'loans' => fn ($q) => $q->latest('applied_at'), 'transactions' => fn ($q) => $q->latest('trx_date')->limit(10)]);
        return view('members.show', compact('member'));
    }
}
