<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Dashboard'); ?> · <?php echo e(config('app.name')); ?></title>
    <link rel="stylesheet" href="<?php echo e(asset('assets/app.css')); ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark">K</div>
            <div><strong>Koperasi One</strong><span>Finance Suite</span></div>
        </div>
        <nav class="nav">
            <a class="nav-item <?php echo e(request()->routeIs('dashboard') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard')); ?>"><span>⌂</span> Dashboard</a>
            <div class="nav-label">Operasional</div>
            <a class="nav-item <?php echo e(request()->routeIs('members.*') ? 'active' : ''); ?>" href="<?php echo e(route('members.index')); ?>"><span>👥</span> Anggota</a>
            <a class="nav-item <?php echo e(request()->routeIs('savings.*') ? 'active' : ''); ?>" href="<?php echo e(route('savings.index')); ?>"><span>◫</span> Simpanan</a>
            <a class="nav-item <?php echo e(request()->routeIs('loans.*') ? 'active' : ''); ?>" href="<?php echo e(route('loans.index')); ?>"><span>↗</span> Pinjaman</a>
            <a class="nav-item <?php echo e(request()->routeIs('teller.*') ? 'active' : ''); ?>" href="<?php echo e(route('teller.index')); ?>"><span>▣</span> Kasir / Teller</a>
            <div class="nav-label">Keuangan</div>
            <a class="nav-item <?php echo e(request()->routeIs('accounting.*') ? 'active' : ''); ?>" href="<?php echo e(route('accounting.index')); ?>"><span>▤</span> Akuntansi</a>
            <a class="nav-item <?php echo e(request()->routeIs('reports.*') ? 'active' : ''); ?>" href="<?php echo e(route('reports.index')); ?>"><span>▥</span> Laporan</a>
            <div class="nav-label">Manajemen</div>
            <a class="nav-item <?php echo e(request()->routeIs('approval.*') ? 'active' : ''); ?>" href="<?php echo e(route('approval.index')); ?>"><span>✓</span> Approval <b class="nav-count">4</b></a>
            <a class="nav-item <?php echo e(request()->routeIs('shu.*') ? 'active' : ''); ?>" href="<?php echo e(route('shu.index')); ?>"><span>◎</span> SHU</a>
            <div class="nav-label">Sistem</div>
            <a class="nav-item <?php echo e(request()->routeIs('settings.*') ? 'active' : ''); ?>" href="<?php echo e(route('settings.index')); ?>"><span>⚙</span> Pengaturan</a>
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
            <?php if(session('success')): ?>
                <div class="alert success">✓ <?php echo e(session('success')); ?></div>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <div class="alert danger"><strong>Periksa input:</strong> <?php echo e($errors->first()); ?></div>
            <?php endif; ?>
            <?php echo $__env->yieldContent('content'); ?>
        </main>
    </div>
</div>
<script src="<?php echo e(asset('assets/app.js')); ?>"></script>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Users\ASUS\Herd\koperasi-finance\resources\views/layouts/app.blade.php ENDPATH**/ ?>