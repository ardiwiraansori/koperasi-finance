@extends('layouts.app')
@section('title','SHU')
@section('content')
@php($rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
<div class="page-heading"><div><p class="eyebrow">SISA HASIL USAHA</p><h1>Simulasi SHU {{ $year }}</h1><p>Dihitung dari jurnal: pendapatan dikurangi beban. Rumus final mengikuti AD/ART dan keputusan RAT koperasi.</p></div>
<div class="heading-actions no-print"><a class="btn btn-light" href="{{ route('shu.index', array_merge(request()->query(), ['export' => 1])) }}">↓ Export CSV</a><button class="btn btn-primary" type="submit" form="shuForm">Hitung Simulasi</button></div></div>

<div class="shu-hero"><div><span>SHU Tahun {{ $year }} (sebelum pajak)</span><strong>{{ $shu > 0 ? $rp($shu) : 'Rp 0' }}</strong><p>Pendapatan {{ $rp($income) }} − Beban {{ $rp($expense) }}{{ $shu <= 0 && $income + $expense > 0 ? ' · tidak ada SHU untuk dibagi' : '' }}</p></div>
<div class="shu-ring"><div><strong>{{ $income > 0 ? round(max(0, $shu) / $income * 100).'%' : '-' }}</strong><span>Margin SHU</span></div></div></div>

<div class="detail-grid">
<section class="card"><div class="card-head"><div><h2>Parameter Pembagian</h2><p>Ubah persentase lalu klik Hitung Simulasi. Total harus 100%.</p></div></div>
<form method="get" action="{{ route('shu.index') }}" id="shuForm" class="transaction-form"><div class="parameter-list">
<div style="grid-template-columns:140px 1fr 70px"><span>Tahun buku</span><span></span><select name="tahun" aria-label="Tahun buku" style="height:32px;border:1px solid var(--line);border-radius:8px;font-size:13px">@foreach($years as $y)<option @selected($y === $year)>{{ $y }}</option>@endforeach</select></div>
@foreach(\App\Http\Controllers\ShuController::LABELS as $key => $label)
<div style="grid-template-columns:190px 1fr 76px"><span>{{ $label }}</span><div class="parameter-bar"><i style="width:{{ $pct[$key] }}%"></i></div><input type="number" inputmode="decimal" step="0.1" aria-label="{{ $label }} (persen)" name="{{ $key }}" min="0" max="100" value="{{ $pct[$key] }}" style="height:32px;width:70px;border:1px solid var(--line);border-radius:8px;text-align:right;font-size:13px"></div>
@endforeach
<div style="grid-template-columns:190px 1fr 76px"><span><strong>Total</strong></span><span></span><strong class="{{ abs($pct->sum() - 100) < 0.001 ? '' : 'negative' }}" style="text-align:right">{{ round($pct->sum(), 2) }}%</strong></div>
</div>@error('persen')<small class="negative" style="padding:0 18px 12px;display:block">{{ $message }}</small>@enderror
@if($errors->any() && ! $errors->has('persen'))<small class="negative" style="padding:0 18px 12px;display:block">{{ $errors->first() }}</small>@endif
</form></section>

<section class="card"><div class="card-head"><div><h2>Pratinjau Distribusi</h2><p>Hasil perhitungan dengan persentase di samping</p></div></div><div class="distribution">
@foreach(\App\Http\Controllers\ShuController::LABELS as $key => $label)<div><span>{{ $label }} ({{ $pct[$key] }}%)</span><strong>{{ $rp($pool[$key]) }}</strong></div>@endforeach
</div></section>
</div>

<section class="card"><div class="card-head"><div><h2>SHU per Anggota</h2><p>Jasa modal sebanding simpanan per {{ \Carbon\Carbon::parse($asOf)->translatedFormat('d F Y') }}, jasa usaha sebanding bunga pinjaman yang dibayar pada {{ $year }}</p></div></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Anggota</th><th class="right">Simpanan per {{ \Carbon\Carbon::parse($asOf)->format('d/m/Y') }}</th><th class="right">Bunga Dibayar</th><th class="right">SHU Jasa Modal</th><th class="right">SHU Jasa Usaha</th><th class="right">Total SHU</th></tr></thead><tbody>
@forelse($members as $r)
<tr><td><a class="table-link" href="{{ route('members.show', $r['member']) }}">{{ $r['member']->name }}</a><small class="block">{{ $r['member']->member_no }}</small></td><td class="right">{{ $rp($r['savings']) }}</td><td class="right">{{ $rp($r['interest']) }}</td><td class="right">{{ $rp($r['capital']) }}</td><td class="right">{{ $rp($r['business']) }}</td><td class="right"><strong>{{ $rp($r['total']) }}</strong></td></tr>
@empty
<tr><td colspan="6" class="empty">Belum ada anggota dengan simpanan atau bunga pinjaman.</td></tr>
@endforelse
</tbody>@if($members->isNotEmpty())<tfoot><tr><td><strong>Total dibagikan</strong></td><td class="right"><strong>{{ $rp($totalSavings) }}</strong></td><td class="right"><strong>{{ $rp($totalInterest) }}</strong></td><td class="right"><strong>{{ $rp($distributed['capital']) }}</strong></td><td class="right"><strong>{{ $rp($distributed['business']) }}</strong></td><td class="right"><strong>{{ $rp($distributed['capital'] + $distributed['business']) }}</strong></td></tr></tfoot>@endif</table></div>
<div class="table-footer"><span>Catatan: ini simulasi dan belum dijurnal. Jasa modal memakai saldo simpanan (Pokok, Wajib, Sukarela) pada akhir tahun buku, atau hari ini untuk tahun berjalan, bukan rata-rata sepanjang tahun. Selisih kecil dari pembulatan rupiah wajar.@if($shu > 0 && $totalInterest == 0) Tidak ada bunga pinjaman terbayar pada {{ $year }}, jadi porsi Jasa Usaha belum dapat dibagikan.@endif</span></div>
</section>
@endsection
