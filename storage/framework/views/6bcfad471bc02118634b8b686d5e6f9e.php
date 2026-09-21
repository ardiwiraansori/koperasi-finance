<?php $__env->startSection('title','Approval'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><p class="eyebrow">WORKFLOW</p><h1>Pusat Approval</h1><p>Satu tempat untuk seluruh transaksi yang memerlukan persetujuan berjenjang.</p></div></div>
<div class="approval-tabs"><button class="active">Semua <b>4</b></button><button>Pinjaman <b>3</b></button><button>Pencairan <b>1</b></button><button>Jurnal <b>0</b></button></div>
<div class="card"><div class="approval-request"><span class="approval-icon blue large">↗</span><div class="grow"><div class="request-top"><span>PINJAMAN · PIN-2026-000203</span><small>19 Sep 2026, 14:22</small></div><h3>Pengajuan Pinjaman Pendidikan — Rizki Maulana</h3><p>Plafon Rp 15.000.000 · Tenor 12 bulan · Bunga 9% per tahun</p><div class="approval-path"><span class="done">✓ Admin</span><i></i><span class="done">✓ Verifikasi</span><i></i><span class="current">● Manager</span><i></i><span>○ Pengurus</span></div></div><div class="request-actions"><button class="btn btn-light danger-text">Tolak</button><button class="btn btn-primary">Review</button></div></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\ASUS\Herd\koperasi-finance\resources\views/approval/index.blade.php ENDPATH**/ ?>