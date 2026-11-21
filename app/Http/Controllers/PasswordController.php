<?php
namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// Ganti kata sandi sendiri, untuk petugas dan anggota. Wajib mengisi sandi lama, dan sesi di perangkat lain ikut berakhir.
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view($request->user()->role === 'anggota' ? 'portal.password' : 'password.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed', 'different:current_password'],
        ], [
            'required' => ':Attribute wajib diisi.',
            'current_password.current_password' => 'Kata sandi lama salah.',
            'min' => 'Kata sandi baru minimal :min karakter.',
            'max' => 'Kata sandi baru terlalu panjang.',
            'confirmed' => 'Ulangan kata sandi baru tidak sama.',
            'different' => 'Kata sandi baru harus berbeda dari yang lama.',
        ], ['current_password' => 'kata sandi lama', 'password' => 'kata sandi baru']);

        $request->user()->update(['password' => $data['password']]);
        Auth::logoutOtherDevices($data['password']); // sesi perangkat lain berakhir; sesi ini tetap masuk

        return redirect()->route('password.edit')->with('success', 'Kata sandi diganti. Perangkat lain yang sedang masuk harus masuk ulang.');
    }
}
