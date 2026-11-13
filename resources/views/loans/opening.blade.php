@extends('layouts.app')
@section('title', 'Saldo Awal Pinjaman')
@section('content')
<a class="back-link" href="{{ route('loans.index') }}">← Kembali ke Pinjaman</a>
<div class="page-heading"><div><p class="eyebrow">PINJAMAN</p><h1>Saldo Awal Pinjaman</h1><p>Untuk pinjaman anggota lama yang sudah berjalan sebelum aplikasi dipakai. Sisa pokok tercatat sebagai piutang dan <b>kas tidak berubah</b>.</p></div></div>
<section class="card transaction-form" style="max-width:680px">
<form method="post" action="{{ route('loans.opening.store') }}" novalidate data-loan-calc>
@csrf
<label>Anggota <span>ketik nomor anggota atau nama</span>
<input list="dl-anggota" data-combobox="member_id" autocomplete="off" placeholder="Contoh: Budi, AG000128" autofocus>
<datalist id="dl-anggota">@foreach($members as $m)<option value="{{ $m->member_no }} — {{ $m->name }}" data-id="{{ $m->id }}"></option>@endforeach</datalist>
<input type="hidden" name="member_id" value="{{ old('member_id') }}">@error('member_id')<small class="negative">{{ $message }}</small>@enderror</label>
<div class="form-row">
<label>Nomor pinjaman lama <span>opsional</span><input name="loan_no" maxlength="30" value="{{ old('loan_no') }}" placeholder="Kosongkan untuk nomor otomatis">@error('loan_no')<small class="negative">{{ $message }}</small>@enderror</label>
<label>Produk<select name="product" required>@foreach(\App\Models\Loan::PRODUCTS as $name => $rate)<option value="{{ $name }}" data-rate="{{ $rate }}" @selected(old('product') === $name)>{{ $name }} — bunga {{ $rate }}%</option>@endforeach</select>@error('product')<small class="negative">{{ $message }}</small>@enderror</label>
</div>
<div class="form-row">
<label>Plafon awal<div class="money-input"><span>Rp</span><input name="principal" data-rupiah inputmode="numeric" autocomplete="off" value="{{ old('principal') }}" placeholder="0" required></div>@error('principal')<small class="negative">{{ $message }}</small>@enderror</label>
<label>Tenor (bulan)<input name="term_months" type="number" min="1" max="120" value="{{ old('term_months', 12) }}" required>@error('term_months')<small class="negative">{{ $message }}</small>@enderror</label>
</div>
<div class="form-row">
<label>Sisa pokok saat ini<div class="money-input"><span>Rp</span><input name="outstanding" data-rupiah inputmode="numeric" autocomplete="off" value="{{ old('outstanding') }}" placeholder="0" required></div>@error('outstanding')<small class="negative">{{ $message }}</small>@enderror</label>
<label>Cicilan per bulan <span>opsional</span><div class="money-input"><span>Rp</span><input name="installment" data-rupiah inputmode="numeric" autocomplete="off" value="{{ old('installment') }}" placeholder="Hitung otomatis"></div>@error('installment')<small class="negative">{{ $message }}</small>@enderror</label>
</div>
<div class="form-note" data-loan-estimate>ⓘ Isi plafon dan tenor untuk melihat perkiraan cicilan. Bila cicilan di catatan lama berbeda, isi kolom Cicilan per bulan.</div>
<div class="form-row">
<label>Tanggal pencairan asli<input type="date" name="disbursed_at" max="{{ now()->toDateString() }}" value="{{ old('disbursed_at') }}" required>@error('disbursed_at')<small class="negative">{{ $message }}</small>@enderror</label>
<label>Jatuh tempo berikutnya<input type="date" name="next_due_at" value="{{ old('next_due_at') }}" required>@error('next_due_at')<small class="negative">{{ $message }}</small>@enderror</label>
</div>
<label>Tanggal saldo awal <span>tanggal mulai memakai aplikasi</span><input type="date" name="tanggal" max="{{ now()->toDateString() }}" value="{{ old('tanggal', now()->toDateString()) }}" required>@error('tanggal')<small class="negative">{{ $message }}</small>@enderror</label>
<div class="form-note">ⓘ Jurnal: Piutang Pinjaman (debit) dan Ekuitas Saldo Awal (kredit) sebesar <b>sisa pokok</b>. Angsuran berikutnya dicatat lewat Kasir seperti biasa dan mengurangi sisa pokok ini. Jika jatuh tempo berikutnya sudah lewat, pinjaman langsung tampil menunggak.</div>
<button class="btn btn-primary full" type="submit">Simpan Saldo Awal Pinjaman</button>
</form>
</section>
@endsection
