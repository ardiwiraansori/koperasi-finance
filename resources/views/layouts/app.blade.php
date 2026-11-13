<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    @include('partials.pwa')
</head>
<body>
@include('partials.icons')
<a class="skip-link" href="#main">Lewati ke konten</a>
<div class="scrim" data-nav-close aria-hidden="true"></div>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-close" type="button" data-nav-close aria-label="Tutup menu"><svg class="ic" aria-hidden="true"><use href="#i-x"/></svg></button>
        <div class="brand">
            <div class="brand-mark">K</div>
            <div><strong>Koperasi One</strong><span>Finance Suite</span></div>
        </div>
        @php($u = auth()->user())
        @php($nav = fn (string $pattern) => request()->routeIs($pattern) ? 'active' : '')
        <nav class="nav" aria-label="Menu utama">
            <a class="nav-item {{ $nav('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif href="{{ route('dashboard') }}"><span><svg class="ic" aria-hidden="true"><use href="#i-home"/></svg></span> Dashboard</a>
            <div class="nav-label">Operasional</div>
            <a class="nav-item {{ $nav('members.*') }}" @if(request()->routeIs('members.*')) aria-current="page" @endif href="{{ route('members.index') }}"><span><svg class="ic" aria-hidden="true"><use href="#i-users"/></svg></span> Anggota</a>
            <a class="nav-item {{ $nav('savings.*') }}" @if(request()->routeIs('savings.*')) aria-current="page" @endif href="{{ route('savings.index') }}"><span><svg class="ic" aria-hidden="true"><use href="#i-wallet"/></svg></span> Simpanan</a>
            <a class="nav-item {{ $nav('loans.*') }}" @if(request()->routeIs('loans.*')) aria-current="page" @endif href="{{ route('loans.index') }}"><span><svg class="ic" aria-hidden="true"><use href="#i-trend"/></svg></span> Pinjaman</a>
            @if($u->allows('cash'))<a class="nav-item {{ $nav('teller.*') }}" @if(request()->routeIs('teller.*')) aria-current="page" @endif href="{{ route('teller.index') }}"><span><svg class="ic" aria-hidden="true"><use href="#i-cash"/></svg></span> Kasir / Teller</a>@endif
            @if($u->allows('accounting'))
            <div class="nav-label">Keuangan</div>
            <a class="nav-item {{ $nav('accounting.*') }}" @if(request()->routeIs('accounting.*')) aria-current="page" @endif href="{{ route('accounting.index') }}"><span><svg class="ic" aria-hidden="true"><use href="#i-book"/></svg></span> Akuntansi</a>
            <a class="nav-item {{ $nav('reports.*') }}" @if(request()->routeIs('reports.*')) aria-current="page" @endif href="{{ route('reports.index') }}"><span><svg class="ic" aria-hidden="true"><use href="#i-chart"/></svg></span> Laporan</a>
            @endif
            @if($u->allows('loan.decide') || $u->allows('accounting'))
            <div class="nav-label">Manajemen</div>
            @if($u->allows('loan.decide'))<a class="nav-item {{ $nav('approval.*') }}" @if(request()->routeIs('approval.*')) aria-current="page" @endif href="{{ route('approval.index') }}"><span><svg class="ic" aria-hidden="true"><use href="#i-check"/></svg></span> Approval @if($waiting = \App\Support\Alerts::waitingApprovals())<b class="nav-count" aria-label="{{ $waiting }} menunggu">{{ $waiting }}</b>@endif</a>@endif
            @if($u->allows('accounting'))<a class="nav-item {{ $nav('shu.*') }}" @if(request()->routeIs('shu.*')) aria-current="page" @endif href="{{ route('shu.index') }}"><span><svg class="ic" aria-hidden="true"><use href="#i-pie"/></svg></span> SHU</a>@endif
            @endif
            @if($u->allows('settings'))
            <div class="nav-label">Sistem</div>
            <a class="nav-item {{ $nav('settings.*') }}" @if(request()->routeIs('settings.*')) aria-current="page" @endif href="{{ route('settings.index') }}"><span><svg class="ic" aria-hidden="true"><use href="#i-sliders"/></svg></span> Pengaturan</a>
            @endif
        </nav>
        <div class="sidebar-foot">
            <div class="mini-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</div>
            <div><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->roleLabel() }}</span></div>
            <form method="POST" action="{{ route('logout') }}" style="margin-left:auto">@csrf<button title="Keluar" aria-label="Keluar dari akun"><svg class="ic" aria-hidden="true"><use href="#i-logout"/></svg></button></form>
        </div>
    </aside>
    <div class="content-shell">
        <header class="topbar">
            <button class="icon-btn mobile-menu" id="menuButton" type="button" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false"><svg class="ic" aria-hidden="true"><use href="#i-menu"/></svg></button>
            <div class="branch-switch"><span class="dot" aria-hidden="true"></span> {{ \App\Models\Setting::cooperativeName() }}</div>
            @php($alerts = \App\Support\Alerts::for($u))
            <div class="top-actions">
                <a class="icon-btn" href="{{ route('search') }}" title="Pencarian" aria-label="Pencarian"><svg class="ic" aria-hidden="true"><use href="#i-search"/></svg></a>
                <div class="notify" data-notify>
                    <button class="icon-btn notification" type="button" data-notify-toggle title="Pemberitahuan" aria-label="Pemberitahuan{{ count($alerts) ? ', '.count($alerts).' baru' : '' }}" aria-haspopup="true" aria-expanded="false"><svg class="ic" aria-hidden="true"><use href="#i-bell"/></svg>@if(count($alerts))<i class="notify-badge" aria-hidden="true">{{ count($alerts) }}</i>@endif</button>
                    <div class="notify-panel" data-notify-panel hidden>
                        <div class="notify-head">Pemberitahuan</div>
                        @forelse($alerts as $alert)<a class="notify-item {{ $alert['level'] }}" href="{{ $alert['url'] }}"><span aria-hidden="true">●</span><div>{{ $alert['text'] }}</div></a>@empty<div class="notify-empty">Tidak ada pemberitahuan baru.</div>@endforelse
                    </div>
                </div>
                <div class="period-pill">{{ now()->translatedFormat('F Y') }}</div>
            </div>
        </header>
        <main class="main-content" id="main" tabindex="-1">
            @if(session('success'))
                <div class="alert success" role="status">✓ {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert danger" role="alert">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert danger" role="alert"><strong>Periksa input:</strong> {{ $errors->first() }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('assets/app.js') }}"></script>
@stack('scripts')
</body>
</html>
