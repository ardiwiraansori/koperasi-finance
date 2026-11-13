@extends('layouts.app')
@section('title','Pengaturan')
@section('content')
<div class="page-heading"><div><p class="eyebrow">SISTEM</p><h1>Pengaturan</h1><p>Identitas koperasi, mode akuntansi, dan pengguna beserta perannya.</p></div></div>

<section class="card transaction-form" style="max-width:860px;margin-bottom:16px">
<div class="card-head"><div><h2>Koperasi dan Mode Akuntansi</h2><p>Menentukan apa yang tampil dan bisa dilakukan setiap peran</p></div></div>
<form method="post" action="{{ route('settings.update') }}" novalidate>
@csrf @method('PUT')
<label>Nama koperasi<input name="koperasi_name" maxlength="80" value="{{ old('koperasi_name', \App\Models\Setting::cooperativeName()) }}" required>@error('koperasi_name')<small class="negative">{{ $message }}</small>@enderror</label>
@php($mode = old('has_accountant', \App\Models\Setting::hasAccountant() ? '1' : '0'))
<label style="display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid var(--line);border-radius:10px;margin-top:12px"><input type="radio" name="has_accountant" value="0" @checked($mode === '0') style="width:auto;height:auto;margin:3px 0 0"><span style="font-size:13px;font-weight:400;color:#334155"><b style="font-size:14px">Tanpa akuntan (bawaan)</b><br>Kasir dan pengurus cukup menginput. Kasir memilih <b>jenis transaksi</b> (misalnya "Gaji dan honor") dan jurnal dibuat otomatis. <b>Jurnal Manual disembunyikan.</b> Pengurus tetap bisa melihat Jurnal Umum, Buku Besar, Neraca Saldo, serta Neraca dan Laba Rugi, dan boleh menambah jenis transaksi tanpa memilih akun (otomatis masuk "lain-lain").</span></label>
<label style="display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid var(--line);border-radius:10px"><input type="radio" name="has_accountant" value="1" @checked($mode === '1') style="width:auto;height:auto;margin:3px 0 0"><span style="font-size:13px;font-weight:400;color:#334155"><b style="font-size:14px">Dengan akuntan</b><br>Peran <b>Akuntan</b> tersedia. Akuntan mengelola pemetaan jenis transaksi ke akun, membuat Jurnal Manual (penyesuaian dan koreksi), dan mengisi saldo awal. Kasir tetap hanya menginput.</span></label>
@error('has_accountant')<small class="negative">{{ $message }}</small>@enderror
<button class="btn btn-primary full" type="submit">Simpan Pengaturan</button>
</form>
</section>

<section class="card" style="margin-bottom:16px">
<div class="card-head"><div><h2>Pengguna</h2><p>{{ $users->count() }} akun</p></div></div>
<div class="form-note" style="margin:0 16px 12px">ⓘ <b>Admin / Pengurus:</b> semua fitur, termasuk persetujuan pinjaman dan pengaturan. <b>Kasir:</b> menginput anggota, simpanan, pinjaman, dan transaksi kas saja. <b>Akuntan</b> (bila ada): akuntansi, laporan, SHU, dan saldo awal.</div>
<div class="table-wrap"><table class="data-table" style="min-width:820px"><thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Sandi baru <small class="block">kosongkan bila tidak diganti</small></th><th></th></tr></thead><tbody>
@foreach($users as $u)
<tr>
<td><form id="u{{ $u->id }}" method="post" action="{{ route('settings.users.update', $u) }}">@csrf @method('PUT')</form><input form="u{{ $u->id }}" aria-label="Nama pengguna" name="name" value="{{ $u->name }}" maxlength="80" required style="width:100%;height:36px;border:1px solid var(--line);border-radius:8px;padding:0 8px;font-size:14px"></td>
<td><input form="u{{ $u->id }}" aria-label="Email {{ $u->name }}" name="email" type="email" value="{{ $u->email }}" required style="width:100%;height:36px;border:1px solid var(--line);border-radius:8px;padding:0 8px;font-size:14px"></td>
<td><select form="u{{ $u->id }}" aria-label="Peran {{ $u->name }}" name="role" style="height:36px;border:1px solid var(--line);border-radius:8px;font-size:14px" @disabled($u->id === auth()->id())>@foreach(\App\Models\User::ROLES as $key => $label)@if(isset($roles[$key]) || $u->role === $key)<option value="{{ $key }}" @selected($u->role === $key)>{{ $label }}</option>@endif @endforeach</select>@if($u->id === auth()->id())<input form="u{{ $u->id }}" type="hidden" name="role" value="{{ $u->role }}"><small class="block">akun Anda</small>@endif</td>
<td><input form="u{{ $u->id }}" aria-label="Sandi baru untuk {{ $u->name }}" name="password" type="password" autocomplete="new-password" minlength="8" placeholder="••••••••" style="width:100%;height:36px;border:1px solid var(--line);border-radius:8px;padding:0 8px;font-size:14px"></td>
<td class="right" style="white-space:nowrap"><button form="u{{ $u->id }}" class="btn btn-primary small" type="submit">Simpan</button>
@if($u->id !== auth()->id())
<form id="x{{ $u->id }}" method="post" action="{{ route('settings.users.destroy', $u) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus pengguna '.$u->name.'? Akunnya tidak bisa masuk lagi.') }})" style="display:inline">@csrf @method('DELETE')<button class="btn btn-primary small" type="submit" title="Hapus pengguna">Hapus</button></form>
@endif</td>
</tr>
@endforeach
</tbody></table></div>
@if($errors->any() && ! $errors->has('koperasi_name') && ! $errors->has('has_accountant') && old('_form') !== 'new')<small class="negative" style="display:block;padding:10px 16px">{{ $errors->first() }}</small>@endif
</section>

<section class="card transaction-form" style="max-width:680px">
<div class="card-head"><div><h2>Tambah Pengguna</h2><p>Berikan sandi awal, lalu minta pengguna mengingatnya</p></div></div>
<form method="post" action="{{ route('settings.users.store') }}" novalidate>
@csrf <input type="hidden" name="_form" value="new">
<div class="form-row">
<label>Nama<input name="name" maxlength="80" value="{{ old('_form') === 'new' ? old('name') : '' }}" required></label>
<label>Email<input name="email" type="email" value="{{ old('_form') === 'new' ? old('email') : '' }}" required></label>
</div>
<div class="form-row">
<label>Sandi awal<input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
<label>Peran<select name="role" required>@foreach($roles as $key => $label)<option value="{{ $key }}" @selected(old('role', 'kasir') === $key)>{{ $label }}</option>@endforeach</select></label>
</div>
@if(old('_form') === 'new' && $errors->any())<small class="negative">{{ $errors->first() }}</small>@endif
<button class="btn btn-primary full" type="submit">Tambah Pengguna</button>
</form>
</section>
@endsection
