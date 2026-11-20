@extends('layouts.app')
@section('title','Laba Rugi')
@section('content')
@php($rp = fn ($n) => ($n < -0.004 ? '(' : '').'Rp '.number_format(abs($n), 0, ',', '.').($n < -0.004 ? ')' : ''))
@php($d = fn ($s) => \Carbon\Carbon::parse($s)->format('d M Y'))
<div class="page-heading"><div><p class="eyebrow">LAPORAN KEUANGAN</p><h1>Laba Rugi</h1><p>Pendapatan dan beban pada satu periode; selisihnya adalah Sisa Hasil Usaha (SHU) sebelum pajak.</p></div>
<div class="heading-actions no-print"><a class="btn btn-light" href="{{ route('reports.income', array_merge(request()->query(), ['export' => 1])) }}">↓ Export CSV</a><button class="btn btn-primary" type="button" onclick="window.print()">Cetak</button></div></div>

@include('partials.period-filter', ['routeName' => 'reports.income', 'from' => $from, 'to' => $to, 'compare' => $compare])

<section class="card">
<div class="card-head"><div><h2>Laporan Laba Rugi</h2><p>{{ config('app.name') }} · {{ $d($from) }} s/d {{ $d($to) }}</p></div>
<span class="badge {{ $now['shu'] >= 0 ? 'success' : 'danger' }}">{{ $now['shu'] >= 0 ? 'Surplus' : 'Defisit' }}</span></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Akun</th><th class="right">{{ $d($from) }} – {{ $d($to) }}</th>@if($compare)<th class="right">{{ $d($prevFrom) }} – {{ $d($prevTo) }}</th><th class="right">Perubahan</th>@endif</tr></thead><tbody>
@foreach(['Pendapatan' => ['totalIncome', 'Total Pendapatan'], 'Beban' => ['totalExpense', 'Total Beban']] as $group => [$totalKey, $totalLabel])
<tr class="stmt-head"><td colspan="{{ $compare ? 4 : 2 }}">{{ strtoupper($group) }}</td></tr>
@forelse($lines[$group] as $r)
<tr><td>{{ $r['code'] }} — {{ $r['name'] }}</td><td class="right">{{ $rp($r['a']) }}</td>@if($compare)<td class="right">{{ $rp($r['b']) }}</td><td class="right">{{ $rp($r['a'] - $r['b']) }}</td>@endif</tr>
@empty
<tr><td colspan="{{ $compare ? 4 : 2 }}" class="empty">Tidak ada {{ strtolower($group) }} pada periode ini.</td></tr>
@endforelse
<tr class="stmt-total"><td>{{ $totalLabel }}</td><td class="right">{{ $rp($now[$totalKey]) }}</td>@if($compare)<td class="right">{{ $rp($prev[$totalKey]) }}</td><td class="right">{{ $rp($now[$totalKey] - $prev[$totalKey]) }}</td>@endif</tr>
@endforeach
<tr class="stmt-total stmt-result"><td>SISA HASIL USAHA (SHU) SEBELUM PAJAK</td><td class="right">{{ $rp($now['shu']) }}</td>@if($compare)<td class="right">{{ $rp($prev['shu']) }}</td><td class="right">{{ $rp($now['shu'] - $prev['shu']) }}</td>@endif</tr>
<tr><td>Margin SHU <small class="block">SHU dibanding total pendapatan</small></td><td class="right">{{ $now['totalIncome'] > 0 ? number_format($now['shu'] / $now['totalIncome'] * 100, 1, ',', '.').'%' : '-' }}</td>@if($compare)<td class="right">{{ $prev['totalIncome'] > 0 ? number_format($prev['shu'] / $prev['totalIncome'] * 100, 1, ',', '.').'%' : '-' }}</td><td></td>@endif</tr>
</tbody></table></div>
<div class="table-footer"><span>Dicetak {{ now()->format('d M Y H:i') }} oleh {{ auth()->user()->name }}. Angka dalam kurung bernilai negatif. Pembagian SHU ke anggota ada di menu SHU.</span></div>
</section>
@endsection
