@extends('layouts.app')
@section('title','Laporan')
@section('content')
<div class="page-heading"><div><p class="eyebrow">REPORTING CENTER</p><h1>Laporan</h1><p>Pilih laporan yang ingin ditampilkan, lalu atur periode atau tanggal pada halaman laporan.</p></div></div>
@php($groups = [
    'Keuangan' => [
        ['Neraca', 'Posisi aset, kewajiban, dan ekuitas per tanggal', '▤', 'reports.balance'],
        ['Laba Rugi', 'Pendapatan, beban, dan SHU pada satu periode', '↕', 'reports.income'],
        ['Arus Kas', 'Kas masuk dan keluar per kegiatan', '≈', 'reports.cashflow'],
        ['Perubahan Ekuitas', 'Perubahan modal dan SHU berjalan', '◎', null],
    ],
    'Akuntansi' => [
        ['Buku Besar', 'Mutasi dan saldo per akun', '▥', 'accounting.ledger'],
        ['Neraca Saldo', 'Saldo debit dan kredit setiap akun', '▦', 'accounting.trial'],
        ['Jurnal Umum', 'Daftar jurnal beserta detail transaksi', '▤', 'accounting.index'],
    ],
    'Operasional Koperasi' => [
        ['Rekening Koran Simpanan', 'Mutasi dan saldo berjalan per rekening', '◫', 'savings.statement'],
        ['Portofolio Pinjaman', 'Daftar pinjaman, status, dan sisa pokok', '↗', 'loans.index'],
        ['SHU', 'Dasar dan hasil pembagian SHU per anggota', '◎', 'shu.index'],
        ['Tunggakan dan Kolektibilitas', 'Pinjaman lewat jatuh tempo menurut umur', '!', null],
    ],
])
@foreach($groups as $group => $items)
<section class="report-section"><div class="section-title"><h2>{{ $group }}</h2><span>{{ collect($items)->whereNotNull(3)->count() }} dari {{ count($items) }} tersedia</span></div>
<div class="report-grid">
@foreach($items as [$title, $description, $icon, $route])
@if($route)
<a class="report-card" href="{{ route($route) }}"><span>{{ $icon }}</span><div><strong>{{ $title }}</strong><p>{{ $description }}</p></div><i>›</i></a>
@else
<div class="report-card" style="opacity:.55;cursor:default" title="Belum tersedia"><span>{{ $icon }}</span><div><strong>{{ $title }}</strong><p>{{ $description }} · <b>Segera</b></p></div></div>
@endif
@endforeach
</div></section>
@endforeach
@endsection
