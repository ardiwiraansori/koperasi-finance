@extends('layouts.app')
@section('title','Neraca Saldo')
@section('content')
@php($rp = fn ($n) => $n > 0 ? 'Rp '.number_format($n, 0, ',', '.') : '-')
<div class="page-heading"><div><p class="eyebrow">AKUNTANSI</p><h1>Neraca Saldo</h1><p>Total debit dan kredit setiap akun sampai tanggal tertentu. Jumlah debit harus sama dengan kredit.</p></div><div class="heading-actions no-print"><a class="btn btn-light" href="{{ route('accounting.trial', ['sampai' => $until, 'export' => 1]) }}">↓ Export CSV</a><button class="btn btn-primary" type="button" onclick="window.print()">Cetak</button></div></div>
@include('accounting._tabs')

<form method="get" action="{{ route('accounting.trial') }}" class="card no-print" style="margin-bottom:14px"><div class="toolbar">
<label style="font-size:13px;color:#64748b">Per tanggal <input type="date" name="sampai" value="{{ $until }}" style="height:36px;border:1px solid var(--line);border-radius:9px;padding:0 10px;font-size:13px;margin-left:6px"></label>
<button class="btn btn-primary" type="submit">Tampilkan</button>
</div>@error('sampai')<small class="negative" style="padding:0 16px 12px;display:block">{{ $message }}</small>@enderror</form>

<section class="card">
<div class="card-head"><div><h2>Neraca Saldo per {{ \Carbon\Carbon::parse($until)->format('d M Y') }}</h2><p>{{ $rows->count() }} akun bersaldo atau bermutasi</p></div>
@if(round($totals['debit'], 2) === round($totals['credit'], 2))<span class="badge success">✓ Seimbang</span>@else<span class="badge danger">⚠ TIDAK seimbang</span>@endif</div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Kode</th><th>Akun</th><th>Golongan</th><th class="right">Total Debit</th><th class="right">Total Kredit</th><th class="right">Saldo Debit</th><th class="right">Saldo Kredit</th></tr></thead><tbody>
@forelse($rows as $r)
<tr><td>{{ $r['code'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['type'] }}</td><td class="right">{{ $rp($r['debit']) }}</td><td class="right">{{ $rp($r['credit']) }}</td><td class="right">{{ $rp($r['balD']) }}</td><td class="right">{{ $rp($r['balK']) }}</td></tr>
@empty
<tr><td colspan="7" class="empty">Belum ada jurnal sampai tanggal ini.</td></tr>
@endforelse
</tbody><tfoot><tr><td colspan="3"><strong>TOTAL</strong></td><td class="right"><strong>{{ $rp($totals['debit']) }}</strong></td><td class="right"><strong>{{ $rp($totals['credit']) }}</strong></td><td class="right"><strong>{{ $rp($totals['balD']) }}</strong></td><td class="right"><strong>{{ $rp($totals['balK']) }}</strong></td></tr></tfoot></table></div>
</section>
@endsection
