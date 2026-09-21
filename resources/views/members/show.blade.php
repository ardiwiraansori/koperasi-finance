@extends('layouts.app')
@section('title',$member->name)
@section('content')
<a class="back-link" href="{{ route('members.index') }}">← Kembali ke Anggota</a>
<div class="profile-head card">
    <div class="profile-main"><span class="avatar xl">{{ strtoupper(substr($member->name,0,1)) }}</span><div><div class="title-row"><h1>{{ $member->name }}</h1><span class="badge success">● {{ $member->status }}</span></div><p>{{ $member->member_no }} · {{ $member->branch }} · Anggota sejak {{ $member->joined_at->format('d M Y') }}</p><div class="contact-row"><span>☎ {{ $member->phone }}</span><span>✉ {{ $member->email }}</span></div></div></div>
    <div class="heading-actions"><button class="btn btn-light">Cetak Profil</button><button class="btn btn-primary">Edit Anggota</button></div>
</div>
<div class="member-kpis">
    <div class="card"><span>Total Simpanan</span><strong>Rp {{ number_format($member->savings->sum('balance'),0,',','.') }}</strong><small>3 rekening aktif</small></div>
    <div class="card"><span>Sisa Pinjaman</span><strong>Rp {{ number_format($member->loans->whereIn('status',['Aktif','Tunggakan'])->sum('outstanding'),0,',','.') }}</strong><small>{{ $member->loans->whereIn('status',['Aktif','Tunggakan'])->count() }} pinjaman berjalan</small></div>
    <div class="card"><span>Angsuran Berikutnya</span><strong>Rp {{ number_format($member->loans->where('status','Aktif')->first()?->installment ?? 0,0,',','.') }}</strong><small>{{ $member->loans->where('status','Aktif')->first()?->next_due_at?->format('d M Y') ?? '-' }}</small></div>
    <div class="card"><span>Status Risiko</span><strong class="text-green">Rendah</strong><small>Skor demo 86 / 100</small></div>
</div>
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
