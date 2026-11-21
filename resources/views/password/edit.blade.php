@extends('layouts.app')
@section('title', 'Ganti Kata Sandi')
@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">AKUN SAYA</p>
            <h1>Ganti Kata Sandi</h1>
            <p>Masuk sebagai {{ auth()->user()->name }}. Setelah diganti, perangkat lain yang sedang masuk dengan akun ini
                keluar otomatis.</p>
        </div>
    </div>
    <section class="card" style="max-width:520px">
        <div class="card-head">
            <div>
                <h2>Kata Sandi Baru</h2>
                <p>Isi kata sandi lama untuk memastikan ini benar Anda</p>
            </div>
        </div>
        @include('partials.password-form')
    </section>
@endsection
