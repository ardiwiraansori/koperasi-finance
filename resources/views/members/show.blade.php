@extends('layouts.app')
@section('title',$member->name)
@section('content')
<a class="back-link no-print" href="{{ route('members.index') }}">← Kembali ke Anggota</a>
<div class="profile-head card">
    <div class="profile-main"><span class="avatar xl">{{ strtoupper(substr($member->name,0,1)) }}</span><div><div class="title-row"><h1>{{ $member->name }}</h1><span class="badge {{ $member->status === 'Aktif' ? 'success' : 'neutral' }}">● {{ $member->status }}</span></div><p>{{ $member->member_no }} · {{ $member->branch }} · Anggota sejak {{ $member->joined_at->format('d M Y') }}</p><div class="contact-row"><span>☎ {{ $member->phone }}</span><span>✉ {{ $member->email }}</span></div></div></div>
    <div class="heading-actions no-print"><a class="btn btn-light" href="{{ route('members.sheet', $member) }}">Cetak Formulir</a><button class="btn btn-light" type="button" onclick="window.print()">Cetak Profil</button>@if(auth()->user()->allows('members.write'))<a class="btn btn-primary" href="{{ route('members.edit', $member) }}">Edit Anggota</a>@endif</div>
</div>
@if($login = session('portal_login'))
<div class="portal-login" role="status"><strong>Akun portal siap.</strong> Berikan data ini ke anggota (hanya tampil sekali):<br>Nomor anggota <code>{{ $login['no'] }}</code> · Kata sandi <code>{{ $login['password'] }}</code></div>
@endif
@if(auth()->user()->allows('members.write') && $member->status === 'Aktif')
<form class="no-print" method="POST" action="{{ route('members.account', $member) }}" style="margin-bottom:14px" @if($member->portalUser) data-dialog="Atur ulang kata sandi portal" data-dialog-text="Kata sandi lama anggota ini tidak berlaku lagi dan perangkatnya akan keluar. Kata sandi baru ditampilkan sekali." data-dialog-ok="Atur ulang" @endif>@csrf<button class="btn btn-primary" type="submit">{{ $member->portalUser ? 'Atur Ulang Kata Sandi Portal' : 'Buat Akun Portal' }}</button></form>
@endif
<div class="member-kpis">
    <div class="card"><span>Total Simpanan</span><strong>Rp {{ number_format($member->savings->sum('balance'),0,',','.') }}</strong><small>{{ $member->savings->count() }} rekening simpanan</small></div>
    <div class="card"><span>Sisa Pinjaman</span><strong>Rp {{ number_format($member->loans->whereIn('status',['Aktif','Tunggakan'])->sum('outstanding'),0,',','.') }}</strong><small>{{ $member->loans->whereIn('status',['Aktif','Tunggakan'])->count() }} pinjaman berjalan</small></div>
    <div class="card"><span>Angsuran Berikutnya</span><strong>Rp {{ number_format($member->loans->where('status','Aktif')->first()?->installment ?? 0,0,',','.') }}</strong><small>{{ $member->loans->where('status','Aktif')->first()?->next_due_at?->format('d M Y') ?? '-' }}</small></div>
    @php($late = (int) $member->loans->max(fn ($l) => $l->daysOverdue()))
    <div class="card"><span>Status Risiko</span><strong class="{{ $late > 30 ? 'negative' : ($late > 0 ? '' : 'text-green') }}" @if($late > 0 && $late <= 30) style="color:#ea580c" @endif>{{ $late > 30 ? 'Tinggi' : ($late > 0 ? 'Sedang' : 'Rendah') }}</strong><small>{{ $late > 0 ? 'Pinjaman terlambat '.$late.' hari' : 'Tidak ada pinjaman terlambat' }}</small></div>
</div>
@php($dash = fn ($x) => filled($x) ? e($x) : '<span class="muted">—</span>')
<section class="card" style="margin-bottom:14px"><div class="card-head"><div><h2>Data Anggota</h2><p>Identitas, kontak, dan ahli waris</p></div>@if(auth()->user()->allows('members.write'))<a href="{{ route('members.edit', $member) }}" class="no-print">Lengkapi</a>@endif</div>
<dl class="data-list">
<div><dt>NIK</dt><dd>{!! $dash($member->nik) !!}</dd></div>
<div><dt>Jenis kelamin</dt><dd>{!! $dash(\App\Models\Member::GENDERS[$member->gender] ?? null) !!}</dd></div>
<div><dt>Tempat, tanggal lahir</dt><dd>{!! $dash(trim(($member->birth_place ?? '').($member->birth_date ? ', '.$member->birth_date->translatedFormat('d F Y') : ''), ' ,')) !!}</dd></div>
<div><dt>Status perkawinan</dt><dd>{!! $dash($member->marital_status) !!}</dd></div>
<div><dt>Pekerjaan</dt><dd>{!! $dash($member->occupation) !!}</dd></div>
<div><dt>Nomor HP</dt><dd>{!! $dash($member->phone) !!}</dd></div>
<div><dt>Email</dt><dd>{!! $dash($member->email) !!}</dd></div>
<div class="wide"><dt>Alamat</dt><dd>{!! $dash($member->address) !!}</dd></div>
<div class="wide"><dt>Ahli waris</dt><dd>{!! filled($member->heir_name) ? e($member->heir_name).' <span class="muted">('.e($member->heir_relation).(filled($member->heir_phone) ? ', '.e($member->heir_phone) : '').')</span>' : '<span class="muted">—</span>' !!}</dd></div>
</dl></section>
<div class="detail-grid">
    <section class="card"><div class="card-head"><div><h2>Simpanan</h2><p>Saldo per jenis simpanan</p></div><a href="{{ route('savings.index') }}">Detail</a></div>
        <div class="account-list">@foreach($member->savings as $saving)<div class="account-row"><div><span class="account-icon">◫</span><div><strong>Simpanan {{ $saving->type }}</strong><small>{{ $saving->account_no }}</small></div></div><strong>Rp {{ number_format($saving->balance,0,',','.') }}</strong></div>@endforeach</div>
    </section>
    <section class="card"><div class="card-head"><div><h2>Pinjaman</h2><p>Riwayat fasilitas anggota</p></div><a href="{{ route('loans.index') }}">Detail</a></div>
        @forelse($member->loans as $loan)<div class="loan-mini"><div><strong>{{ $loan->loan_no }}</strong><small>{{ $loan->product }} · {{ $loan->term_months }} bulan</small></div><div class="right"><span class="badge {{ $loan->status==='Lunas' ? 'neutral' : ($loan->status==='Tunggakan' ? 'danger' : 'success') }}">{{ $loan->status }}</span><strong>Rp {{ number_format($loan->outstanding,0,',','.') }}</strong></div></div>@empty<p class="empty">Belum ada pinjaman.</p>@endforelse
    </section>
</div>
<div class="card"><div class="card-head"><div><h2>Aktivitas Terakhir</h2><p>Transaksi terbaru anggota</p></div></div><div class="table-wrap"><table class="data-table compact"><thead><tr><th>Nomor</th><th>Tanggal</th><th>Keterangan</th><th>Kategori</th><th class="right">Nominal</th></tr></thead><tbody>@forelse($member->transactions as $trx)<tr><td><strong>{{ $trx->trx_no }}</strong></td><td>{{ $trx->trx_date->format('d M Y H:i') }}</td><td>{{ $trx->description }}</td><td>{{ $trx->category }}</td><td class="right amount {{ $trx->direction==='in'?'in':'out' }}">{{ $trx->direction==='in'?'+':'-' }} Rp {{ number_format($trx->amount,0,',','.') }}</td></tr>@empty<tr><td colspan="5" class="empty">Belum ada transaksi demo.</td></tr>@endforelse</tbody></table></div></div>
@endsection
