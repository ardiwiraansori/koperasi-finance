@extends('layouts.app')
@section('title','Pengaturan')
@section('content')
<div class="page-heading"><div><p class="eyebrow">SYSTEM</p><h1>Pengaturan</h1><p>Konfigurasi master, akuntansi, akses pengguna, dan parameter aplikasi.</p></div></div>
<div class="settings-grid">
@foreach([['Master Produk','Produk simpanan, pinjaman, bunga, tenor, dan denda','◫'],['Chart of Accounts','Struktur akun dan mapping jurnal otomatis','▤'],['Cabang & Unit','Cabang, kas teller, rekening bank, dan cost center','⌂'],['User & Role','Hak akses berdasarkan fungsi dan jabatan','👥'],['Approval Matrix','Batas nominal dan tingkat persetujuan','✓'],['Periode Akuntansi','Buka/tutup periode dan lock transaksi','▦'],['Nomor Dokumen','Format nomor transaksi dan jurnal','№'],['Audit Trail','Jejak aktivitas user dan perubahan data','⌁']] as $item)<a href="#" class="setting-card"><span>{{ $item[2] }}</span><div><strong>{{ $item[0] }}</strong><p>{{ $item[1] }}</p></div><i>›</i></a>@endforeach
</div>
<div class="card demo-note"><strong>Demo scope</strong><p>Menu pengaturan ini masih berupa rancangan UI. Untuk implementasi production, parameter jurnal otomatis dan approval matrix sebaiknya disepakati lebih dulu karena akan memengaruhi struktur transaksi inti.</p></div>
@endsection
