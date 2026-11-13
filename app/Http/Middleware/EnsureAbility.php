<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// Pemakaian di rute: ->middleware('ability:cash'). Pengguna tanpa hak mendapat 403.
class EnsureAbility
{
    public function handle(Request $request, Closure $next, string $ability)
    {
        abort_unless($request->user()?->allows($ability), 403, 'Peran Anda tidak memiliki akses ke halaman ini.');
        return $next($request);
    }
}
