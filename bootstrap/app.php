<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sesi yang dibuka dengan sandi lama berakhir begitu sandi akun itu diganti (oleh pemiliknya atau admin).
        $middleware->web(append: [\Illuminate\Session\Middleware\AuthenticateSession::class]);
        $middleware->alias(['ability' => \App\Http\Middleware\EnsureAbility::class, 'area' => \App\Http\Middleware\EnsureArea::class]);
        // Percayai header proxy HANYA bila diisi di .env (mis. TRUSTED_PROXIES=* di Render/Cloudflare).
        // Tanpa ini, penyerang bisa memalsukan X-Forwarded-For dan lolos dari batas percobaan login.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Produksi selalu HTTPS (Render, Rumahweb). Tanpa ini, di belakang proxy semua alamat (CSS, form login, PWA) dibuat dengan http://
// dan diblokir browser. Lokal (APP_ENV=local) tidak terpengaruh.
$app->booted(fn() => $app->isProduction() && \Illuminate\Support\Facades\URL::forceScheme('https'));

return $app;
