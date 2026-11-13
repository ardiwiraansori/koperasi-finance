@extends('layouts.app')
@section('title','Kasir / Teller')
@section('content')
<div class="page-heading"><div><p class="eyebrow">KASIR / TELLER</p><h1>Transaksi Hari Ini</h1><p>Input penerimaan dan pengeluaran operasional koperasi.</p></div><div class="teller-balance"><span>Saldo Kas Teller</span><strong>Rp {{ number_format($cash,0,',','.') }}</strong><small>Dihitung dari jurnal kas · {{ now()->format('d M H:i') }}</small></div></div>
<div class="teller-grid">
<section class="card transaction-form"><div class="card-head"><div><h2>Transaksi Baru</h2><p>Angsuran pinjaman, penerimaan, dan pengeluaran kas</p></div></div>
<form method="post" action="{{ route('teller.store') }}" data-kasir-form>@csrf
<label>Anggota <span>opsional</span><select name="member_id"><option value="">Transaksi Umum</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected(old('member_id')==$member->id)>{{ $member->member_no }} — {{ $member->name }}</option>@endforeach</select></label>
<label>Kategori<select name="category" required>@foreach(['Angsuran' => 'Angsuran Pinjaman (kas masuk)', 'Penerimaan' => 'Penerimaan Lain (kas masuk)', 'Pengeluaran' => 'Pengeluaran (kas keluar)'] + (auth()->user()->allows('opening') ? ['Saldo Awal' => 'Saldo Awal Kas (sekali di awal)'] : []) as $c => $label)<option value="{{ $c }}" @selected(old('category')===$c)>{{ $label }}</option>@endforeach</select></label>
<label data-loan-field>Pinjaman yang diangsur<select name="loan_id"><option value="">Pilih pinjaman…</option>@foreach($loans as $loan)<option value="{{ $loan->id }}" data-member="{{ $loan->member_id }}" @selected(old('loan_id')==$loan->id)>{{ $loan->loan_no }} — {{ $loan->member->name }} (sisa pokok Rp {{ number_format($loan->outstanding,0,',','.') }})</option>@endforeach</select></label>
<div class="form-note" data-loan-empty hidden>ⓘ Anggota ini tidak memiliki pinjaman berjalan, jadi tidak ada angsuran yang bisa dicatat.</div>
<label data-type-field>Jenis<select name="jenis_id"><option value="">Pilih jenis…</option>@foreach($types as $type)<option value="{{ $type->id }}" data-category="{{ $type->category }}" @selected((int) old('jenis_id') === $type->id)>{{ $type->name }}</option>@endforeach</select>@error('jenis_id')<small class="negative">{{ $message }}</small>@enderror</label>
<label>Nominal<div class="money-input"><span>Rp</span><input name="amount" data-rupiah inputmode="numeric" autocomplete="off" value="{{ old('amount') }}" placeholder="0" required></div></label>
<label>Keterangan<input name="description" value="{{ old('description') }}" placeholder="Contoh: Setoran simpanan wajib" required></label>
<div class="form-note">ⓘ <b>Angsuran:</b> pilih anggota lalu pinjamannya; bunga dihitung otomatis dari sisa pokok, sisanya mengurangi pokok. <b>Penerimaan/Pengeluaran:</b> pilih jenisnya (akun jurnal terisi otomatis); pengeluaran tidak boleh melebihi saldo kas. <b>Saldo Awal Kas:</b> isi sekali saat mulai memakai aplikasi, sesuai uang kas yang ada. Jurnal selalu dibuat otomatis. Setoran/penarikan simpanan dilakukan di menu Simpanan.</div>
<button class="btn btn-primary full" type="submit">Simpan Transaksi</button>
</form></section>
<section class="card"><div class="card-head"><div><h2>Aktivitas Kasir</h2><p>10 transaksi terbaru</p></div></div><div class="teller-list">@foreach($transactions as $trx)<div class="teller-row"><span class="tiny-icon {{ $trx->direction==='in'?'green':'red' }}">{{ $trx->direction==='in'?'↓':'↑' }}</span><div class="grow"><strong>{{ $trx->description }}</strong><small>{{ $trx->trx_no }} · {{ $trx->member?->name ?? 'Umum' }}</small></div><div class="right"><strong class="amount {{ $trx->direction==='in'?'in':'out' }}">{{ $trx->direction==='in'?'+':'-' }} Rp {{ number_format($trx->amount,0,',','.') }}</strong><small>{{ $trx->trx_date->format('d M H:i') }}</small></div></div>@endforeach</div></section>
</div>
@endsection
