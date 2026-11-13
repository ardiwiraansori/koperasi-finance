@extends('layouts.app')
@section('title','Buku Besar')
@section('content')
@php($rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
<div class="page-heading"><div><p class="eyebrow">AKUNTANSI</p><h1>Buku Besar</h1><p>Mutasi dan saldo berjalan satu akun, dari seluruh jurnal yang sudah Posted.</p></div></div>
@include('accounting._tabs')

<section class="card transaction-form no-print" style="max-width:680px;margin-bottom:16px">
<form method="get" action="{{ route('accounting.ledger') }}">
<label>Akun <span>ketik kode atau nama akun</span>
<input list="dl-akun" data-combobox="akun" autocomplete="off" placeholder="Contoh: Kas, 10101, Pendapatan Bunga" required>
<datalist id="dl-akun">@foreach($accounts as $c => $name)<option value="{{ $c }} — {{ $name }}" data-id="{{ $c }}"></option>@endforeach</datalist>
<input type="hidden" name="akun" value="{{ $code }}">@error('akun')<small class="negative">{{ $message }}</small>@enderror</label>
<div class="form-row">
<label>Dari tanggal <span>opsional</span><input type="date" name="dari" value="{{ $dari }}">@error('dari')<small class="negative">{{ $message }}</small>@enderror</label>
<label>Sampai tanggal <span>opsional</span><input type="date" name="sampai" value="{{ $sampai }}">@error('sampai')<small class="negative">{{ $message }}</small>@enderror</label>
</div>
<button class="btn btn-primary full" type="submit">Tampilkan</button>
</form>
</section>

@if($ledger)
<section class="card">
<div class="card-head"><div><h2>{{ $code }} · {{ $accounts[$code] }}</h2><p>{{ \App\Support\Coa::type($code) }} · saldo normal {{ \App\Support\Coa::sign($code) === 1 ? 'debit' : 'kredit' }} · Periode {{ $dari ? \Carbon\Carbon::parse($dari)->format('d M Y') : 'awal' }} s/d {{ $sampai ? \Carbon\Carbon::parse($sampai)->format('d M Y') : 'sekarang' }}</p></div><button class="btn btn-primary small no-print" type="button" onclick="window.print()">Cetak</button></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Tanggal</th><th>No. Jurnal</th><th>Keterangan</th><th class="right">Debit</th><th class="right">Kredit</th><th class="right">Saldo</th></tr></thead><tbody>
<tr><td colspan="5"><strong>Saldo awal</strong></td><td class="right"><strong>{{ $rp($ledger['opening']) }}</strong></td></tr>
@forelse($ledger['rows'] as $r)
@php($l = $r['line'])
<tr><td>{{ \Carbon\Carbon::parse($l->j_date)->format('d M Y') }}</td><td>{{ $l->j_no }}</td><td>{{ $l->j_desc }}</td><td class="right">{{ $l->debit > 0 ? $rp($l->debit) : '' }}</td><td class="right">{{ $l->credit > 0 ? $rp($l->credit) : '' }}</td><td class="right">{{ $rp($r['saldo']) }}</td></tr>
@empty
<tr><td colspan="6" class="empty">Belum ada mutasi pada periode ini.</td></tr>
@endforelse
</tbody><tfoot><tr><td colspan="3"><strong>Total / Saldo akhir</strong></td><td class="right"><strong>{{ $rp($ledger['debit']) }}</strong></td><td class="right"><strong>{{ $rp($ledger['credit']) }}</strong></td><td class="right"><strong>{{ $rp($ledger['closing']) }}</strong></td></tr></tfoot></table></div>
<div class="table-footer"><span>Dicetak {{ now()->format('d M Y H:i') }} oleh {{ auth()->user()->name }}.</span></div>
</section>
@endif
@endsection
