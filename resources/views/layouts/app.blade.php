<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark">K</div>
            <div><strong>Koperasi One</strong><span>Finance Suite</span></div>
        </div>
        <nav class="nav">
            <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>⌂</span> Dashboard</a>
            <div class="nav-label">Operasional</div>
            <a class="nav-item {{ request()->routeIs('members.*') ? 'active' : '' }}" href="{{ route('members.index') }}"><span>👥</span> Anggota</a>
            <a class="nav-item {{ request()->routeIs('savings.*') ? 'active' : '' }}" href="{{ route('savings.index') }}"><span>◫</span> Simpanan</a>
            <a class="nav-item {{ request()->routeIs('loans.*') ? 'active' : '' }}" href="{{ route('loans.index') }}"><span>↗</span> Pinjaman</a>
            <a class="nav-item {{ request()->routeIs('teller.*') ? 'active' : '' }}" href="{{ route('teller.index') }}"><span>▣</span> Kasir / Teller</a>
            <div class="nav-label">Keuangan</div>
            <a class="nav-item {{ request()->routeIs('accounting.*') ? 'active' : '' }}" href="{{ route('accounting.index') }}"><span>▤</span> Akuntansi</a>
            <a class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><span>▥</span> Laporan</a>
            <div class="nav-label">Manajemen</div>
            <a class="nav-item {{ request()->routeIs('approval.*') ? 'active' : '' }}" href="{{ route('approval.index') }}"><span>✓</span> Approval <b class="nav-count">4</b></a>
            <a class="nav-item {{ request()->routeIs('shu.*') ? 'active' : '' }}" href="{{ route('shu.index') }}"><span>◎</span> SHU</a>
            <div class="nav-label">Sistem</div>
            <a class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><span>⚙</span> Pengaturan</a>
        </nav>
        <div class="sidebar-foot">
            <div class="mini-avatar">AD</div>
            <div><strong>Admin Demo</strong><span>Administrator</span></div>
            <button title="Keluar demo">⋯</button>
        </div>
    </aside>
    <div class="content-shell">
        <header class="topbar">
            <button class="icon-btn mobile-menu" id="menuButton" aria-label="Buka menu">☰</button>
            <div class="branch-switch"><span class="dot"></span> Koperasi Maju Bersama <small>Cabang Pusat</small></div>
            <div class="top-actions">
                <button class="icon-btn" title="Pencarian">⌕</button>
                <button class="icon-btn notification" title="Notifikasi">♢<i></i></button>
                <div class="period-pill">September 2026 ▾</div>
            </div>
        </header>
        <main class="main-content">
            @if(session('success'))
                <div class="alert success">✓ {{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert danger"><strong>Periksa input:</strong> {{ $errors->first() }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('assets/app.js') }}"></script>
@stack('scripts')
</body>
</html>
