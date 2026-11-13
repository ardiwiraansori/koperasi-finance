@extends('layouts.app')
@section('title','Jurnal Manual')
@section('content')
<div class="page-heading"><div><p class="eyebrow">AKUNTANSI</p><h1>Jurnal Manual</h1><p>Jurnal penyesuaian atau koreksi. Setiap jurnal harus seimbang (total debit = total kredit).</p></div></div>
@include('accounting._tabs')

<section class="card transaction-form" style="max-width:860px">
<form method="post" action="{{ route('accounting.manual.store') }}" novalidate data-journal-form>
@csrf
<div class="form-row">
<label>Tanggal<input type="date" name="tanggal" max="{{ now()->toDateString() }}" value="{{ old('tanggal', now()->toDateString()) }}" required>@error('tanggal')<small class="negative">{{ $message }}</small>@enderror</label>
<label>Keterangan<input name="keterangan" maxlength="200" value="{{ old('keterangan') }}" placeholder="Contoh: Penyusutan inventaris bulan November" required>@error('keterangan')<small class="negative">{{ $message }}</small>@enderror</label>
</div>

@php($old = old('lines', [['account' => '', 'debit' => '', 'credit' => ''], ['account' => '', 'debit' => '', 'credit' => '']]))
<div class="table-wrap"><table class="journal-table" style="min-width:640px"><thead><tr><th style="width:44%">Akun</th><th class="right">Debit (Rp)</th><th class="right">Kredit (Rp)</th><th></th></tr></thead>
<tbody data-journal-lines>
@foreach($old as $i => $line)
<tr data-journal-row>
<td><select name="lines[{{ $i }}][account]" style="width:100%;height:36px"><option value="">Pilih akun…</option>@foreach($accounts as $code => $name)<option value="{{ $code }}" @selected(($line['account'] ?? '') === (string) $code)>{{ $code }} — {{ $name }}</option>@endforeach</select></td>
<td><input name="lines[{{ $i }}][debit]" data-rupiah data-side="debit" inputmode="numeric" autocomplete="off" value="{{ $line['debit'] ?? '' }}" placeholder="0" style="width:100%;height:36px;text-align:right"></td>
<td><input name="lines[{{ $i }}][credit]" data-rupiah data-side="credit" inputmode="numeric" autocomplete="off" value="{{ $line['credit'] ?? '' }}" placeholder="0" style="width:100%;height:36px;text-align:right"></td>
<td><button type="button" class="btn btn-primary small" data-journal-remove title="Hapus baris">✕</button></td>
</tr>
@endforeach
</tbody>
<tfoot><tr><td><button type="button" class="btn btn-primary small" data-journal-add>＋ Tambah baris</button></td><td class="right" data-journal-debit>Rp 0</td><td class="right" data-journal-credit>Rp 0</td><td></td></tr></tfoot></table></div>
<template data-journal-template><tr data-journal-row>
<td><select name="lines[__i__][account]" style="width:100%;height:36px"><option value="">Pilih akun…</option>@foreach($accounts as $code => $name)<option value="{{ $code }}">{{ $code }} — {{ $name }}</option>@endforeach</select></td>
<td><input name="lines[__i__][debit]" data-rupiah data-side="debit" inputmode="numeric" autocomplete="off" placeholder="0" style="width:100%;height:36px;text-align:right"></td>
<td><input name="lines[__i__][credit]" data-rupiah data-side="credit" inputmode="numeric" autocomplete="off" placeholder="0" style="width:100%;height:36px;text-align:right"></td>
<td><button type="button" class="btn btn-primary small" data-journal-remove title="Hapus baris">✕</button></td>
</tr></template>
@error('lines')<small class="negative">{{ $message }}</small>@enderror
@foreach($errors->get('lines.*') as $messages)<small class="negative">{{ $messages[0] }}</small>@endforeach
<div class="form-note" data-journal-balance>ⓘ Isi debit atau kredit pada tiap baris (salah satu saja).</div>
<div class="form-note">ⓘ Akun <b>Kas, Piutang Pinjaman, dan Simpanan anggota</b> tidak tersedia di sini karena saldonya harus sama dengan data transaksi. Gunakan menu Kasir, Pinjaman, atau Simpanan untuk itu.</div>
<button class="btn btn-primary full" type="submit">Simpan Jurnal</button>
</form>
</section>
@endsection
