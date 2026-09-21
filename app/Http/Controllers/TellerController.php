<?php
namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TellerController extends Controller
{
    public function index(): View
    {
        $members = Member::where('status', 'Aktif')->orderBy('name')->get();
        $transactions = Transaction::with('member')->latest('trx_date')->limit(10)->get();
        return view('teller.index', compact('members','transactions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['nullable','exists:members,id'],
            'category' => ['required','in:Simpanan,Angsuran,Penerimaan,Pengeluaran'],
            'direction' => ['required','in:in,out'],
            'amount' => ['required','numeric','min:1'],
            'description' => ['required','string','max:255'],
        ]);
        $data['trx_no'] = 'TRX-'.now()->format('ymdHis');
        $data['trx_date'] = now();
        $data['status'] = 'Posted';
        Transaction::create($data);
        return back()->with('success', 'Transaksi demo berhasil disimpan. Untuk production, hubungkan proses ini ke jurnal otomatis dan saldo rekening.');
    }
}
