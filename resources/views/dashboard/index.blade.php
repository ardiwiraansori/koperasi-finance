@extends('layouts.app')
@section('title','Dashboard')
@section('content')
@php($rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
@php($jt = fn ($n) => 'Rp '.number_format($n / 1000000, 1, ',', '.').' Jt')
<div class="page-heading">
    <div><p class="eyebrow">OVERVIEW</p><h1>{{ $greeting }}, {{ auth()->user()->name }}</h1><p>Pantau kesehatan koperasi dan pekerjaan yang perlu ditindaklanjuti.</p></div>
    <div class="heading-actions">@if(auth()->user()->allows('cash'))<a class="btn btn-primary" href="{{ route('teller.index') }}">＋ Transaksi Baru</a>@endif</div>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-icon blue"><svg class="ic" aria-hidden="true"><use href="#i-users"/></svg></div><div><span>Anggota Aktif</span><strong>{{ number_format($stats['members']) }}</strong><small class="{{ $stats['newMembers'] ? 'positive' : 'muted' }}">{{ $stats['newMembers'] ? '↑ '.$stats['newMembers'].' anggota baru' : 'Belum ada anggota baru' }} <em>bulan ini</em></small></div></div>
    <div class="kpi-card"><div class="kpi-icon green"><svg class="ic" aria-hidden="true"><use href="#i-wallet"/></svg></div><div><span>Total Simpanan</span><strong>{{ $jt($stats['savings']) }}</strong><small class="{{ $stats['savingsNet'] > 0 ? 'positive' : ($stats['savingsNet'] < 0 ? 'negative' : 'muted') }}">{{ $stats['savingsNet'] > 0 ? '↑' : ($stats['savingsNet'] < 0 ? '↓' : '') }} {{ $rp(abs($stats['savingsNet'])) }} <em>selisih bulan ini</em></small></div></div>
    <div class="kpi-card"><div class="kpi-icon purple"><svg class="ic" aria-hidden="true"><use href="#i-trend"/></svg></div><div><span>Pinjaman Beredar</span><strong>{{ $jt($stats['loans']) }}</strong><small class="muted">{{ $stats['loanCount'] }} pinjaman berjalan</small></div></div>
    <div class="kpi-card"><div class="kpi-icon orange"><svg class="ic" aria-hidden="true"><use href="#i-alert"/></svg></div><div><span>Tunggakan</span><strong>{{ $jt($stats['arrears']) }}</strong><small class="{{ $stats['arrearsCount'] ? 'negative' : 'positive' }}">{{ $stats['arrearsCount'] ? $stats['arrearsCount'].' pinjaman lewat jatuh tempo' : 'Tidak ada tunggakan' }}</small></div></div>
</div>

<div class="dashboard-grid">
    <section class="card chart-card">
        <div class="card-head"><div><h2>Arus Kas</h2><p>Kas masuk dan keluar {{ count($chart['labels']) }} bulan terakhir</p></div></div>
        <div class="chart-legend"><span><i class="legend-dot income"></i>Kas Masuk</span><span><i class="legend-dot expense"></i>Kas Keluar</span></div>
        <div class="chart-wrap">
            <svg viewBox="0 0 760 260" class="line-chart" role="img" aria-label="Grafik arus kas">
                <g class="grid-lines"><line x1="44" y1="40" x2="740" y2="40"/><line x1="44" y1="95" x2="740" y2="95"/><line x1="44" y1="150" x2="740" y2="150"/><line x1="44" y1="205" x2="740" y2="205"/></g>
                @if($chart['hasData'])
                <path class="area income-area" d="{{ $chart['incomeArea'] }}"/>
                <path class="line income-line" d="{{ $chart['incomeLine'] }}"/>
                <path class="line expense-line" d="{{ $chart['expenseLine'] }}"/>
                @else
                <text x="392" y="130" text-anchor="middle" fill="#94a3b8" font-size="12">Belum ada transaksi pada periode ini</text>
                @endif
                <g class="axis-labels"><text x="0" y="36">{{ $jt($chart['max']) }}</text>@foreach($chart['labels'] as $i => $label)<text x="{{ $chart['x'][$i] }}" y="245" text-anchor="middle">{{ $label }}</text>@endforeach</g>
            </svg>
        </div>
        <div class="chart-stats"><div><span>Kas Masuk {{ now()->translatedFormat('F') }}</span><strong>{{ $rp($stats['income']) }}</strong></div><div><span>Posisi Kas</span><strong>{{ $rp($stats['cash']) }}</strong></div></div>
    </section>

    <section class="card approval-card">
        <div class="card-head"><div><h2>Perlu Tindakan</h2><p>Menunggu Anda</p></div>@if(auth()->user()->allows('loan.decide'))<a href="{{ route('approval.index') }}">Lihat semua</a>@endif</div>
        <div class="approval-list">
            @if(auth()->user()->allows('loan.decide'))
            <a href="{{ route('approval.index') }}" class="approval-item"><span class="approval-icon blue">↗</span><div><strong>Pengajuan Pinjaman</strong><small>{{ $queue['pending'] ? $queue['pending'].' menunggu persetujuan' : 'Tidak ada antrean' }}</small></div><b class="{{ $queue['pending'] ? '' : 'zero' }}">{{ $queue['pending'] }}</b><i>›</i></a>
            <a href="{{ route('approval.index') }}" class="approval-item"><span class="approval-icon green">₿</span><div><strong>Pencairan Dana</strong><small>{{ $queue['approved'] ? $queue['approved'].' menunggu dicairkan kasir' : 'Tidak ada antrean' }}</small></div><b class="{{ $queue['approved'] ? '' : 'zero' }}">{{ $queue['approved'] }}</b><i>›</i></a>
            @endif
            @if(auth()->user()->allows('loan.disburse'))
            <a href="{{ route('disbursement.index') }}" class="approval-item"><span class="approval-icon green">₿</span><div><strong>Pencairan Dana</strong><small>{{ $queue['approved'] ? $queue['approved'].' siap dicairkan' : 'Tidak ada antrean' }}</small></div><b class="{{ $queue['approved'] ? '' : 'zero' }}">{{ $queue['approved'] }}</b><i>›</i></a>
            @endif
            <a href="{{ route('loans.index') }}" class="approval-item"><span class="approval-icon orange">!</span><div><strong>Pinjaman Menunggak</strong><small>{{ $stats['arrearsCount'] ? 'Perlu ditagih' : 'Tidak ada tunggakan' }}</small></div><b class="{{ $stats['arrearsCount'] ? '' : 'zero' }}">{{ $stats['arrearsCount'] }}</b><i>›</i></a>
        </div>
        <div class="health-score"><div class="score-ring" style="background:conic-gradient({{ $quality['score'] >= 75 ? '#16a34a' : '#dc2626' }} {{ $quality['score'] }}%,#e5e7eb 0)"><span>{{ $quality['score'] }}</span><small>/100</small></div><div><strong>Skor Kualitas Pinjaman</strong><p>{{ $quality['verdict'] }}</p></div></div>
    </section>
</div>

<div class="dashboard-grid lower">
    <section class="card">
        <div class="card-head"><div><h2>Transaksi Terakhir</h2><p>7 transaksi terbaru</p></div>@if(auth()->user()->allows('cash'))<a href="{{ route('teller.index') }}">Buka kasir</a>@endif</div>
        <div class="table-wrap"><table class="data-table compact"><thead><tr><th>Transaksi</th><th>Anggota</th><th>Waktu</th><th class="right">Nominal</th></tr></thead><tbody>
        @forelse($transactions as $trx)
            <tr><td><div class="transaction-name"><span class="tiny-icon {{ $trx->direction === 'in' ? 'green' : 'red' }}">{{ $trx->direction === 'in' ? '↓' : '↑' }}</span><div><strong>{{ $trx->category }}</strong><small>{{ $trx->trx_no }}</small></div></div></td><td>{{ $trx->member?->name ?? 'Umum' }}</td><td>{{ $trx->trx_date->format('d M, H:i') }}</td><td class="right amount {{ $trx->direction === 'in' ? 'in' : 'out' }}">{{ $trx->direction === 'in' ? '+' : '-' }} {{ $rp($trx->amount) }}</td></tr>
        @empty
            <tr><td colspan="4" class="empty">Belum ada transaksi.</td></tr>
        @endforelse
        </tbody></table></div>
    </section>
    <section class="card">
        <div class="card-head"><div><h2>Kualitas Pinjaman</h2><p>Pinjaman berjalan menurut keterlambatan</p></div><a href="{{ route('loans.index') }}">Detail</a></div>
        <div class="loan-quality"><div class="donut" style="background:conic-gradient(#16a34a 0 {{ $quality['goodPct'] }}%,#f59e0b {{ $quality['goodPct'] }}% {{ $quality['goodPct'] + $quality['watchPct'] }}%,#ef4444 {{ $quality['goodPct'] + $quality['watchPct'] }}% 100%)"><div><strong>{{ $quality['total'] > 0 ? round($quality['goodPct']).'%' : '-' }}</strong><span>Lancar</span></div></div><div class="quality-list"><div><span><i class="qdot good"></i>Lancar</span><strong>{{ $jt($quality['good']) }}</strong></div><div><span><i class="qdot warn"></i>1–90 hari</span><strong>{{ $jt($quality['watch']) }}</strong></div><div><span><i class="qdot bad"></i>&gt;90 hari</span><strong>{{ $jt($quality['bad']) }}</strong></div></div></div>
        <div class="npl-note"><span>NPL (&gt;90 hari)</span><strong>{{ number_format($quality['npl'], 1, ',', '.') }}%</strong><small>Sisa pokok pinjaman lewat jatuh tempo lebih dari 90 hari dibanding seluruh pinjaman berjalan.</small></div>
    </section>
</div>
@endsection
