@extends('layouts.app')
@section('title','Simpanan')
@section('content')
<div class="page-heading"><div><p class="eyebrow">SIMPANAN</p><h1>Rekening Simpanan</h1><p>Pantau saldo dan rekening simpanan seluruh anggota.</p></div><div class="heading-actions"><button class="btn btn-light">Rekening Koran</button><button class="btn btn-primary">＋ Setoran</button></div></div>
<div class="kpi-grid three">
@foreach(['Pokok'=>'blue','Wajib'=>'green','Sukarela'=>'purple'] as $type=>$color)
<div class="kpi-card"><div class="kpi-icon {{ $color }}">◫</div><div><span>Simpanan {{ $type }}</span><strong>Rp {{ number_format(($summary[$type] ?? 0)/1000000,1,',','.') }} Jt</strong><small class="muted">{{ $savings->where('type',$type)->count() }} rekening</small></div></div>
@endforeach
</div>
<div class="card"><div class="toolbar"><div class="search-box">⌕ <input data-table-search="#savingsTable" placeholder="Cari rekening atau anggota..."></div><select><option>Semua Jenis</option><option>Pokok</option><option>Wajib</option><option>Sukarela</option></select></div><div class="table-wrap"><table class="data-table" id="savingsTable"><thead><tr><th>Rekening</th><th>Anggota</th><th>Jenis</th><th>Cabang</th><th class="right">Saldo</th><th>Status</th></tr></thead><tbody>@foreach($savings as $saving)<tr><td><strong>{{ $saving->account_no }}</strong></td><td><a class="table-link" href="{{ route('members.show',$saving->member) }}">{{ $saving->member->name }}</a><small class="block">{{ $saving->member->member_no }}</small></td><td>Simpanan {{ $saving->type }}</td><td>{{ $saving->member->branch }}</td><td class="right"><strong>Rp {{ number_format($saving->balance,0,',','.') }}</strong></td><td><span class="badge success">Aktif</span></td></tr>@endforeach</tbody></table></div></div>
@endsection
