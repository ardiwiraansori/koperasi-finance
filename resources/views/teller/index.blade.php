@extends('layouts.app')
@section('title','Kasir / Teller')
@section('content')
<div class="page-heading"><div><p class="eyebrow">KASIR / TELLER</p><h1>Transaksi Hari Ini</h1><p>Input penerimaan dan pengeluaran operasional koperasi.</p></div><div class="teller-balance"><span>Saldo Kas Teller</span><strong>Rp 127.450.000</strong><small>Kas dibuka 08:02 WIB</small></div></div>
<div class="teller-grid">
<section class="card transaction-form"><div class="card-head"><div><h2>Transaksi Baru</h2><p>Form demo tersambung ke tabel transactions</p></div></div>
<form method="post" action="{{ route('teller.store') }}">@csrf
<label>Anggota <span>opsional</span><select name="member_id"><option value="">Transaksi Umum</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected(old('member_id')==$member->id)>{{ $member->member_no }} — {{ $member->name }}</option>@endforeach</select></label>
<div class="form-row"><label>Kategori<select name="category" required>@foreach(['Simpanan','Angsuran','Penerimaan','Pengeluaran'] as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></label><label>Arah Kas<select name="direction" required><option value="in">Kas Masuk</option><option value="out">Kas Keluar</option></select></label></div>
<label>Nominal<div class="money-input"><span>Rp</span><input name="amount" type="number" min="1" value="{{ old('amount') }}" placeholder="0" required></div></label>
<label>Keterangan<input name="description" value="{{ old('description') }}" placeholder="Contoh: Setoran simpanan wajib" required></label>
<div class="form-note">ⓘ Demo ini menyimpan transaksi. Update saldo simpanan/pinjaman dan auto-journal perlu aturan bisnis final koperasi sebelum diaktifkan.</div>
<button class="btn btn-primary full" type="submit">Simpan Transaksi</button>
</form></section>
<section class="card"><div class="card-head"><div><h2>Aktivitas Kasir</h2><p>10 transaksi terbaru</p></div><button class="btn btn-light small">Tutup Kas</button></div><div class="teller-list">@foreach($transactions as $trx)<div class="teller-row"><span class="tiny-icon {{ $trx->direction==='in'?'green':'red' }}">{{ $trx->direction==='in'?'↓':'↑' }}</span><div class="grow"><strong>{{ $trx->description }}</strong><small>{{ $trx->trx_no }} · {{ $trx->member?->name ?? 'Umum' }}</small></div><div class="right"><strong class="amount {{ $trx->direction==='in'?'in':'out' }}">{{ $trx->direction==='in'?'+':'-' }} Rp {{ number_format($trx->amount,0,',','.') }}</strong><small>{{ $trx->trx_date->format('d M H:i') }}</small></div></div>@endforeach</div></section>
</div>
@endsection
