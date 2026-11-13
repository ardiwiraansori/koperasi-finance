<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// ->middleware('area:staff') untuk halaman petugas, 'area:portal' untuk portal anggota.
// Akun anggota tidak pernah boleh menyentuh halaman petugas: semuanya dialihkan ke portal.
class EnsureArea
{
    public function handle(Request $request, Closure $next, string $area)
    {
        $user = $request->user();

        if ($area === 'staff') {
            return $user->role === 'anggota' ? redirect()->route('portal.home') : $next($request);
        }

        abort_unless($user->role === 'anggota' && $user->member_id, 403, 'Halaman ini khusus anggota.');
        if ($user->member?->status !== 'Aktif') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email' => 'Akun anggota tidak aktif. Hubungi pengurus koperasi.']);
        }
        return $next($request);
    }
}
