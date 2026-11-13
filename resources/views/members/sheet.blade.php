@extends('layouts.app')
@section('title', 'Formulir '.$member->member_no)
@section('content')
@php($blank = fn ($x) => filled($x) ? e($x) : '<span class="blank"></span>')
@php($d = fn ($date) => $date ? $date->translatedFormat('d F Y') : null)
<div class="no-print sheet-bar">
<a class="back-link" href="{{ route('members.show', $member) }}">← Kembali ke profil</a>
<button class="btn btn-primary" type="button" onclick="window.print()">Cetak Formulir</button>
</div>
<article class="paper" aria-label="Formulir keanggotaan {{ $member->name }}">
<header class="paper-head">
<strong>{{ \App\Models\Setting::cooperativeName() }}</strong>
<h1>FORMULIR KEANGGOTAAN</h1>
<p>Nomor Anggota: <b>{{ $member->member_no }}</b></p>
</header>

<section><h2>A. Data Pribadi</h2><dl class="sheet-list">
<div><dt>Nama lengkap</dt><dd>{!! $blank($member->name) !!}</dd></div>
<div><dt>NIK</dt><dd>{!! $blank($member->nik) !!}</dd></div>
<div><dt>Jenis kelamin</dt><dd>{!! $blank(\App\Models\Member::GENDERS[$member->gender] ?? null) !!}</dd></div>
<div><dt>Tempat, tanggal lahir</dt><dd>{!! $blank(trim(($member->birth_place ?? '').($member->birth_date ? ', '.$d($member->birth_date) : ''), ' ,')) !!}</dd></div>
<div><dt>Status perkawinan</dt><dd>{!! $blank($member->marital_status) !!}</dd></div>
<div><dt>Pekerjaan</dt><dd>{!! $blank($member->occupation) !!}</dd></div>
</dl></section>

<section><h2>B. Kontak dan Alamat</h2><dl class="sheet-list">
<div><dt>Alamat</dt><dd>{!! $blank($member->address) !!}</dd></div>
<div><dt>Nomor HP</dt><dd>{!! $blank($member->phone) !!}</dd></div>
<div><dt>Email</dt><dd>{!! $blank($member->email) !!}</dd></div>
</dl></section>

<section><h2>C. Ahli Waris</h2><dl class="sheet-list">
<div><dt>Nama</dt><dd>{!! $blank($member->heir_name) !!}</dd></div>
<div><dt>Hubungan</dt><dd>{!! $blank($member->heir_relation) !!}</dd></div>
<div><dt>Nomor HP</dt><dd>{!! $blank($member->heir_phone) !!}</dd></div>
</dl></section>

<section><h2>D. Keanggotaan</h2><dl class="sheet-list">
<div><dt>Cabang</dt><dd>{!! $blank($member->branch) !!}</dd></div>
<div><dt>Tanggal bergabung</dt><dd>{!! $blank($d($member->joined_at)) !!}</dd></div>
<div><dt>Status</dt><dd>{!! $blank($member->status) !!}</dd></div>
<div><dt>Saldo Simpanan Pokok</dt><dd>{{ $pokok > 0 ? 'Rp '.number_format($pokok, 0, ',', '.') : '' }}@if($pokok <= 0)<span class="blank"></span>@endif</dd></div>
</dl></section>

<p class="statement">Dengan menandatangani formulir ini, saya menyatakan bahwa data di atas benar, bersedia menjadi anggota
{{ \App\Models\Setting::cooperativeName() }}, serta mematuhi Anggaran Dasar/Anggaran Rumah Tangga dan keputusan Rapat Anggota.</p>

<div class="signatures">
<div><p>Anggota,</p><span class="sign-space"></span><p class="sign-name">{{ $member->name }}</p></div>
<div><p>Pengurus / Petugas,</p><span class="sign-space"></span><p class="sign-name">( ........................................ )</p></div>
</div>
<p class="paper-foot">Dicetak {{ now()->translatedFormat('d F Y, H:i') }} · {{ $member->member_no }}</p>
</article>
@endsection
