@extends('layouts.app')
@section('title','Anggota')
@section('content')
<div class="page-heading"><div><p class="eyebrow">KEANGGOTAAN</p><h1>Data Anggota</h1><p>Kelola profil, simpanan, pinjaman, dan aktivitas anggota.</p></div><div class="heading-actions"><button class="btn btn-light">↓ Export Excel</button><button class="btn btn-primary">＋ Tambah Anggota</button></div></div>
<div class="summary-strip"><div><span>Total Anggota</span><strong>{{ $members->count() }}</strong></div><div><span>Anggota Aktif</span><strong>{{ $members->where('status','Aktif')->count() }}</strong></div><div><span>Total Simpanan</span><strong>Rp {{ number_format($members->sum('savings_sum_balance'),0,',','.') }}</strong></div><div><span>Outstanding Pinjaman</span><strong>Rp {{ number_format($members->sum('loans_sum_outstanding'),0,',','.') }}</strong></div></div>
<div class="card">
    <div class="toolbar"><div class="search-box">⌕ <input data-table-search="#memberTable" placeholder="Cari nama, nomor anggota, cabang..."></div><select><option>Semua Cabang</option><option>Pusat</option><option>Cabang A</option><option>Cabang B</option></select><select><option>Status: Semua</option><option>Aktif</option></select></div>
    <div class="table-wrap"><table class="data-table" id="memberTable"><thead><tr><th>Anggota</th><th>Cabang</th><th>Bergabung</th><th class="right">Simpanan</th><th class="right">Sisa Pinjaman</th><th>Status</th><th></th></tr></thead><tbody>
    @foreach($members as $member)
        <tr><td><a class="member-cell" href="{{ route('members.show',$member) }}"><span class="avatar">{{ strtoupper(substr($member->name,0,1)) }}</span><div><strong>{{ $member->name }}</strong><small>{{ $member->member_no }}</small></div></a></td><td>{{ $member->branch }}</td><td>{{ $member->joined_at->format('d M Y') }}</td><td class="right">Rp {{ number_format($member->savings_sum_balance ?? 0,0,',','.') }}</td><td class="right">Rp {{ number_format($member->loans_sum_outstanding ?? 0,0,',','.') }}</td><td><span class="badge success">● {{ $member->status }}</span></td><td class="right"><a class="row-action" href="{{ route('members.show',$member) }}">›</a></td></tr>
    @endforeach
    </tbody></table></div>
    <div class="table-footer"><span>Menampilkan {{ $members->count() }} data demo</span><div><button disabled>‹</button><button class="active">1</button><button disabled>›</button></div></div>
</div>
@endsection
