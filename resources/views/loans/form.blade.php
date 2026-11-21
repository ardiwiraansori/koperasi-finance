@extends('layouts.app')
@section('title', 'Pengajuan Pinjaman')
@section('content')
<a class="back-link" href="{{ route('loans.index') }}">← Kembali ke Pinjaman</a>
<div class="page-heading"><div><p class="eyebrow">PINJAMAN</p><h1>Pengajuan Pinjaman</h1><p>Pengajuan masuk ke antrean approval. Bunga tetap, cicilan dihitung otomatis (anuitas).</p></div></div>
<section class="card transaction-form" style="max-width:680px">
<form method="post" action="{{ route('loans.store') }}" novalidate data-loan-calc data-rate="{{ \App\Models\Loan::RATE }}" data-topups="{{ json_encode($topups) }}">
@csrf
<label>Anggota <span>ketik nomor anggota atau nama</span>
<input list="dl-anggota" data-combobox="member_id" autocomplete="off" placeholder="Contoh: Budi, AG000128" autofocus>
<datalist id="dl-anggota">@foreach($members as $m)<option value="{{ $m->member_no }} — {{ $m->name }}" data-id="{{ $m->id }}"></option>@endforeach</datalist>
<input type="hidden" name="member_id" value="{{ old('member_id') }}">@error('member_id')<small class="negative">{{ $message }}</small>@enderror</label>
<div class="form-note" data-topup-panel style="white-space:pre-line" hidden></div>
<div class="form-note">{{ \App\Models\Loan::PRODUCT }} · bunga {{ number_format(\App\Models\Loan::RATE, 1, ',', '.') }}% per tahun</div>
<div class="form-row">
<label>Plafon<div class="money-input"><span>Rp</span><input name="principal" data-rupiah inputmode="numeric" autocomplete="off" value="{{ old('principal') }}" placeholder="0" required></div>@error('principal')<small class="negative">{{ $message }}</small>@enderror</label>
<label>Tenor (bulan)<input name="term_months" type="number" min="3" max="60" value="{{ old('term_months', 12) }}" required>@error('term_months')<small class="negative">{{ $message }}</small>@enderror</label>
</div>
<div class="form-note" data-loan-estimate>ⓘ Isi plafon dan tenor untuk melihat perkiraan cicilan per bulan.</div>
<button class="btn btn-primary full" type="submit">Ajukan Pinjaman</button>
</form>
</section>
@endsection
