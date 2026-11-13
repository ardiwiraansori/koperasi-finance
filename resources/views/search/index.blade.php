@extends('layouts.app')
@section('title','Pencarian')
@section('content')
@php($rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
<div class="page-heading"><div><p class="eyebrow">PENCARIAN</p><h1>Cari di seluruh data</h1><p>Anggota (nama, nomor, NIK, telepon), rekening simpanan, pinjaman, dan transaksi.</p></div></div>
<form method="get" action="{{ route('search') }}" class="card" style="margin-bottom:16px"><div class="toolbar">
<div class="search-box" style="flex:1">⌕ <input type="search" aria-label="Kata pencarian" name="q" value="{{ $q }}" placeholder="Ketik minimal 2 huruf, misalnya nama anggota atau nomor…" autofocus autocomplete="off" style="font-size:15px"></div>
<button class="btn btn-primary" type="submit">Cari</button>
</div>@error('q')<small class="negative" style="padding:0 16px 12px;display:block">{{ $message }}</small>@enderror</form>

@if($results === null)
<div class="card"><div class="empty">@if($q !== '') Ketik minimal 2 karakter. @else Mulai mengetik untuk mencari. @endif</div></div>
@elseif($total === 0)
<div class="card"><div class="empty">Tidak ada hasil untuk "{{ $q }}".</div></div>
@else
@if($results['members']->isNotEmpty())
<section class="card" style="margin-bottom:14px"><div class="card-head"><div><h2>Anggota</h2><p>{{ $results['members']->count() }} hasil</p></div></div><div class="account-list">
@foreach($results['members'] as $m)<a class="account-row" href="{{ route('members.show', $m) }}"><div><span class="avatar">{{ mb_strtoupper(mb_substr($m->name, 0, 1)) }}</span><div><strong>{{ $m->name }}</strong><small>{{ $m->member_no }} · {{ $m->phone ?: 'tanpa telepon' }} · {{ $m->branch }}</small></div></div><span class="badge {{ $m->status === 'Aktif' ? 'success' : 'neutral' }}">{{ $m->status }}</span></a>@endforeach
</div></section>
@endif
@if($results['savings']->isNotEmpty())
<section class="card" style="margin-bottom:14px"><div class="card-head"><div><h2>Rekening Simpanan</h2><p>{{ $results['savings']->count() }} hasil</p></div></div><div class="account-list">
@foreach($results['savings'] as $s)<a class="account-row" href="{{ route('savings.statement', ['rekening' => $s->id]) }}"><div><span class="account-icon">◫</span><div><strong>{{ $s->account_no }}</strong><small>{{ $s->member->name }} · Simpanan {{ $s->type }}</small></div></div><strong>{{ $rp($s->balance) }}</strong></a>@endforeach
</div></section>
@endif
@if($results['loans']->isNotEmpty())
<section class="card" style="margin-bottom:14px"><div class="card-head"><div><h2>Pinjaman</h2><p>{{ $results['loans']->count() }} hasil</p></div></div><div class="account-list">
@foreach($results['loans'] as $l)<a class="account-row" href="{{ route('loans.show', $l) }}"><div><span class="account-icon">↗</span><div><strong>{{ $l->loan_no }}</strong><small>{{ $l->member->name }} · {{ $l->product }}</small></div></div><div class="right"><strong>{{ $rp($l->outstanding) }}</strong><small class="block">{{ $l->display_status }}</small></div></a>@endforeach
</div></section>
@endif
@if($results['transactions']->isNotEmpty())
<section class="card" style="margin-bottom:14px"><div class="card-head"><div><h2>Transaksi</h2><p>{{ $results['transactions']->count() }} hasil terbaru</p></div></div><div class="account-list">
@foreach($results['transactions'] as $t)<div class="account-row"><div><span class="tiny-icon {{ $t->direction === 'in' ? 'green' : 'red' }}">{{ $t->direction === 'in' ? '↓' : '↑' }}</span><div><strong>{{ $t->description }}</strong><small>{{ $t->trx_no }} · {{ $t->member?->name ?? 'Umum' }} · {{ $t->trx_date->format('d M Y') }}</small></div></div><strong class="amount {{ $t->direction === 'in' ? 'in' : 'out' }}">{{ $t->direction === 'in' ? '+' : '-' }} {{ $rp($t->amount) }}</strong></div>@endforeach
</div></section>
@endif
@endif
@endsection
