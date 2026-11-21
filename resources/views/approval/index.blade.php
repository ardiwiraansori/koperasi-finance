@extends('layouts.app')
@section('title','Approval')
@section('content')
@php($rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
<div class="page-heading"><div><p class="eyebrow">WORKFLOW</p><h1>Pusat Approval</h1><p>Setujui atau tolak pengajuan pinjaman. Pinjaman yang sudah disetujui dicairkan oleh kasir.</p></div></div>
<div class="approval-tabs"><button type="button" class="active" data-approval-tab="">Semua <b>{{ $pending->count() + $approved->count() }}</b></button><button type="button" data-approval-tab="Menunggu Approval">Persetujuan <b>{{ $pending->count() }}</b></button><button type="button" data-approval-tab="Disetujui">Pencairan <b>{{ $approved->count() }}</b></button></div>
<div class="journal-list" id="approvalList">
@forelse($pending->concat($approved) as $loan)
<div class="card" data-approval-row="{{ $loan->status }}"><div class="approval-request">
    <span class="approval-icon {{ $loan->status === 'Disetujui' ? 'green' : 'blue' }} large">{{ $loan->status === 'Disetujui' ? '₿' : '↗' }}</span>
    <div class="grow">
        <div class="request-top"><span>{{ $loan->status === 'Disetujui' ? 'PENCAIRAN' : 'PERSETUJUAN' }} · {{ $loan->loan_no }}</span><small>Diajukan {{ $loan->applied_at->format('d M Y') }}</small></div>
        <h3>{{ $loan->product }} — <a class="table-link" href="{{ route('members.show', $loan->member) }}">{{ $loan->member->name }}</a></h3>
        <p>Plafon {{ $rp($loan->principal) }} · Tenor {{ $loan->term_months }} bulan · Bunga {{ number_format($loan->interest_rate, 1, ',', '.') }}% per tahun · Cicilan {{ $rp($loan->installment) }}</p>
        @if($payoffs[$loan->id] ?? [])@include('partials.loan-payoff', ['lines' => $payoffs[$loan->id], 'principal' => $loan->principal])@endif
        <div class="approval-path"><span class="done">✓ Pengajuan</span><i></i><span class="{{ $loan->status === 'Disetujui' ? 'done' : 'current' }}">{{ $loan->status === 'Disetujui' ? '✓' : '●' }} Persetujuan</span><i></i><span class="{{ $loan->status === 'Disetujui' ? 'current' : '' }}">{{ $loan->status === 'Disetujui' ? '●' : '○' }} Pencairan</span></div>
    </div>
    <div class="request-actions">
        <form method="post" action="{{ route('loans.reject', $loan) }}" data-dialog="Tolak pengajuan {{ $loan->loan_no }}" data-dialog-text="Pengajuan {{ $loan->member->name }} tidak bisa diproses lagi setelah ditolak." data-dialog-ok="Tolak" data-dialog-reason="Alasan penolakan" data-dialog-danger>@csrf<input type="hidden" name="note"><button class="btn btn-primary" type="submit">Tolak</button></form>
        @if($loan->status === 'Menunggu Approval')
        <form method="post" action="{{ route('loans.approve', $loan) }}">@csrf<button class="btn btn-primary" type="submit">Setujui</button></form>
        @else
        <span class="badge warning">Menunggu dicairkan kasir</span>
        @endif
        <a class="btn btn-primary" href="{{ route('loans.show', $loan) }}">Detail</a>
    </div>
</div></div>
@empty
<div class="card"><div class="empty">Tidak ada yang menunggu tindakan. 🎉</div></div>
@endforelse
</div>
@endsection
