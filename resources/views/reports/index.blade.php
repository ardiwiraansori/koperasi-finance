@extends('layouts.app')
@section('title','Laporan')
@section('content')
<div class="page-heading"><div><p class="eyebrow">REPORTING CENTER</p><h1>Laporan</h1><p>Pilih laporan yang ingin ditampilkan, lalu atur periode dan filter pada halaman detail.</p></div><div class="period-pill large">01 Sep 2026 — 30 Sep 2026 ▾</div></div>
@php($groups=[
'Keuangan'=>[['Neraca','Posisi aset, liabilitas, dan ekuitas','▤'],['Laba Rugi','Pendapatan dan beban periode berjalan','↕'],['Arus Kas','Arus operasi, investasi, dan pendanaan','≈'],['Perubahan Ekuitas','Perubahan modal dan SHU berjalan','◎']],
'Akuntansi'=>[['Buku Besar','Mutasi dan saldo per akun','▥'],['Neraca Saldo','Saldo debit dan kredit setiap akun','▦'],['Jurnal Umum','Daftar jurnal beserta detail transaksi','▤'],['Rekonsiliasi Bank','Perbandingan catatan bank dan sistem','⌁']],
'Operasional Koperasi'=>[['Laporan Simpanan','Saldo dan mutasi simpanan anggota','◫'],['Laporan Pinjaman','Portofolio dan outstanding pinjaman','↗'],['Tunggakan','Daftar pinjaman yang jatuh tempo','!'],['Laporan SHU','Dasar dan hasil pembagian SHU','◎']]
])
@foreach($groups as $group=>$items)<section class="report-section"><div class="section-title"><h2>{{ $group }}</h2><span>{{ count($items) }} laporan</span></div><div class="report-grid">@foreach($items as $item)<a class="report-card" href="#"><span>{{ $item[2] }}</span><div><strong>{{ $item[0] }}</strong><p>{{ $item[1] }}</p></div><i>›</i></a>@endforeach</div></section>@endforeach
@endsection
