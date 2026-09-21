@extends('layouts.app')
@section('title','Dashboard')
@section('content')
<div class="page-heading">
    <div><p class="eyebrow">OVERVIEW</p><h1>Selamat siang, Admin 👋</h1><p>Pantau kesehatan koperasi dan pekerjaan yang perlu ditindaklanjuti.</p></div>
    <div class="heading-actions"><button class="btn btn-light">↓ Export</button><button class="btn btn-primary">＋ Transaksi Baru</button></div>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-icon blue">👥</div><div><span>Anggota Aktif</span><strong>{{ number_format($stats['members']) }}</strong><small class="positive">↑ 4,8% <em>dari bulan lalu</em></small></div></div>
    <div class="kpi-card"><div class="kpi-icon green">◫</div><div><span>Total Simpanan</span><strong>Rp {{ number_format($stats['savings']/1000000,1,',','.') }} Jt</strong><small class="positive">↑ 6,2% <em>dari bulan lalu</em></small></div></div>
    <div class="kpi-card"><div class="kpi-icon purple">↗</div><div><span>Pinjaman Beredar</span><strong>Rp {{ number_format($stats['loans']/1000000,1,',','.') }} Jt</strong><small class="muted">3 pinjaman aktif</small></div></div>
    <div class="kpi-card"><div class="kpi-icon orange">!</div><div><span>Tunggakan</span><strong>Rp {{ number_format($stats['arrears']/1000000,1,',','.') }} Jt</strong><small class="negative">Perlu perhatian</small></div></div>
</div>

<div class="dashboard-grid">
    <section class="card chart-card">
        <div class="card-head"><div><h2>Arus Kas</h2><p>Pergerakan kas masuk dan keluar 8 bulan terakhir</p></div><select><option>8 Bulan</option><option>12 Bulan</option></select></div>
        <div class="chart-legend"><span><i class="legend-dot income"></i>Kas Masuk</span><span><i class="legend-dot expense"></i>Kas Keluar</span></div>
        <div class="chart-wrap">
            <svg viewBox="0 0 760 260" class="line-chart" role="img" aria-label="Grafik arus kas demo">
                <g class="grid-lines"><line x1="44" y1="40" x2="740" y2="40"/><line x1="44" y1="95" x2="740" y2="95"/><line x1="44" y1="150" x2="740" y2="150"/><line x1="44" y1="205" x2="740" y2="205"/></g>
                <path class="area income-area" d="M44 178 C95 170 105 135 145 143 S210 94 245 116 S315 87 345 101 S420 58 445 73 S520 98 545 82 S610 50 645 64 S705 39 740 48 L740 220 L44 220 Z"/>
                <path class="line income-line" d="M44 178 C95 170 105 135 145 143 S210 94 245 116 S315 87 345 101 S420 58 445 73 S520 98 545 82 S610 50 645 64 S705 39 740 48"/>
                <path class="line expense-line" d="M44 190 C95 177 110 188 145 170 S205 155 245 166 S310 139 345 148 S405 131 445 142 S505 117 545 130 S600 120 645 124 S700 95 740 112"/>
                <g class="axis-labels"><text x="44" y="245">Feb</text><text x="142" y="245">Mar</text><text x="240" y="245">Apr</text><text x="338" y="245">Mei</text><text x="436" y="245">Jun</text><text x="534" y="245">Jul</text><text x="632" y="245">Agu</text><text x="716" y="245">Sep</text></g>
            </svg>
        </div>
        <div class="chart-stats"><div><span>Kas Masuk September</span><strong>Rp {{ number_format($stats['income'],0,',','.') }}</strong></div><div><span>Posisi Kas & Bank</span><strong>Rp {{ number_format($stats['cash'],0,',','.') }}</strong></div></div>
    </section>

    <section class="card approval-card">
        <div class="card-head"><div><h2>Perlu Approval</h2><p>Menunggu tindakan Anda</p></div><a href="{{ route('approval.index') }}">Lihat semua</a></div>
        <div class="approval-list">
            <a href="{{ route('loans.index') }}" class="approval-item"><span class="approval-icon blue">↗</span><div><strong>Pengajuan Pinjaman</strong><small>3 pengajuan baru</small></div><b>3</b><i>›</i></a>
            <a href="#" class="approval-item"><span class="approval-icon green">₿</span><div><strong>Pencairan Dana</strong><small>1 menunggu pencairan</small></div><b>1</b><i>›</i></a>
            <a href="#" class="approval-item"><span class="approval-icon orange">▤</span><div><strong>Jurnal Koreksi</strong><small>Tidak ada antrean</small></div><b class="zero">0</b><i>›</i></a>
            <a href="#" class="approval-item"><span class="approval-icon purple">◎</span><div><strong>Anggota Baru</strong><small>Verifikasi data anggota</small></div><b>2</b><i>›</i></a>
        </div>
        <div class="health-score"><div class="score-ring"><span>92</span><small>/100</small></div><div><strong>Kesehatan Koperasi</strong><p>Likuiditas dan kualitas pinjaman dalam kondisi baik.</p></div></div>
    </section>
</div>

<div class="dashboard-grid lower">
    <section class="card">
        <div class="card-head"><div><h2>Transaksi Terakhir</h2><p>Aktivitas kasir hari ini</p></div><a href="{{ route('teller.index') }}">Buka kasir</a></div>
        <div class="table-wrap"><table class="data-table compact"><thead><tr><th>Transaksi</th><th>Anggota</th><th>Waktu</th><th class="right">Nominal</th></tr></thead><tbody>
        @foreach($transactions as $trx)
            <tr><td><div class="transaction-name"><span class="tiny-icon {{ $trx->direction === 'in' ? 'green' : 'red' }}">{{ $trx->direction === 'in' ? '↓' : '↑' }}</span><div><strong>{{ $trx->category }}</strong><small>{{ $trx->trx_no }}</small></div></div></td><td>{{ $trx->member?->name ?? 'Umum' }}</td><td>{{ $trx->trx_date->format('d M, H:i') }}</td><td class="right amount {{ $trx->direction === 'in' ? 'in' : 'out' }}">{{ $trx->direction === 'in' ? '+' : '-' }} Rp {{ number_format($trx->amount,0,',','.') }}</td></tr>
        @endforeach
        </tbody></table></div>
    </section>
    <section class="card">
        <div class="card-head"><div><h2>Kualitas Pinjaman</h2><p>Portofolio pinjaman berjalan</p></div><a href="{{ route('loans.index') }}">Detail</a></div>
        <div class="loan-quality"><div class="donut"><div><strong>78%</strong><span>Lancar</span></div></div><div class="quality-list"><div><span><i class="qdot good"></i>Lancar</span><strong>Rp 44,2 Jt</strong></div><div><span><i class="qdot warn"></i>1–30 hari</span><strong>Rp 8,1 Jt</strong></div><div><span><i class="qdot bad"></i>&gt;30 hari</span><strong>Rp 25 Jt</strong></div></div></div>
        <div class="npl-note"><span>NPL Demo</span><strong>32,3%</strong><small>Angka sengaja dibuat terlihat untuk contoh alert UI.</small></div>
    </section>
</div>
@endsection
