@extends('layouts.app')
@section('title', 'Saldo Awal Simpanan')
@section('content')
    <a class="back-link" href="{{ route('savings.index') }}">
        ← Kembali ke Simpanan</a>
    <div class="page-heading">
        <div>
            <p class="eyebrow">SIMPANAN</p>
            <h1>Saldo Awal Simpanan</h1>
            <p>Untuk anggota lama yang sudah punya simpanan sebelum aplikasi dipakai. Saldo rekening terisi dan <b>kas tidak
                    berubah</b>.</p>
        </div>
    </div>

    <section class="card transaction-form" style="max-width:680px;margin-bottom:16px">
        <form method="get" action="{{ route('savings.opening') }}">
            <label>Anggota
                <input list="dl-anggota" data-combobox="anggota" autocomplete="off"
                    placeholder="Ketik nomor anggota atau nama…" required>
                <datalist id="dl-anggota">
                    @foreach ($members as $m)
                        <option value="{{ $m->member_no }} — {{ $m->name }}" data-id="{{ $m->id }}"></option>
                    @endforeach
                </datalist>
                <input type="hidden" name="anggota" value="{{ $member?->id }}"></label>
            <button class="btn btn-primary full" type="submit">Pilih Anggota</button>
        </form>
    </section>

    @if ($member)
        <section class="card transaction-form" style="max-width:680px">
            <div class="card-head">
                <div>
                    <h2>{{ $member->name }}</h2>
                    <p>{{ $member->member_no }} · {{ $member->branch }} · {{ $member->status }}</p>
                </div>
            </div>
            <form method="post" action="{{ route('savings.opening.store') }}" novalidate>
                @csrf
                <input type="hidden" name="member_id" value="{{ $member->id }}">
                @foreach (['Pokok', 'Wajib', 'Sukarela'] as $type)
                    @php($acc = $member->savings->firstWhere('type', $type))
                    <label>Simpanan {{ $type }} <span>{{ $acc?->account_no }} · saldo sekarang Rp
                            {{ number_format($acc?->balance ?? 0, 0, ',', '.') }}</span>
                        @if ($locked[$type] ?? true)
                            <div class="form-note" style="margin:5px 0 0">ⓘ Sudah punya saldo atau transaksi, saldo awal
                                tidak bisa diisi.</div>
                        @else
                            <div class="money-input"><span>Rp</span><input name="saldo[{{ $type }}]" data-rupiah
                                    inputmode="numeric" autocomplete="off" value="{{ old("saldo.$type") }}"
                                    placeholder="0 (kosongkan bila tidak ada)"></div>
                        @endif
                        @error("saldo.$type")
                            <small class="negative">{{ $message }}</small>
                        @enderror
                    </label>
                @endforeach
                @error('saldo')
                    <small class="negative">{{ $message }}</small>
                @enderror
                <label>Tanggal saldo awal <span>tanggal mulai memakai aplikasi</span><input type="date" name="tanggal"
                        max="{{ now()->toDateString() }}" value="{{ old('tanggal', now()->toDateString()) }}" required>
                    @error('tanggal')
                        <small class="negative">{{ $message }}</small>
                    @enderror
                </label>
                <div class="form-note">ⓘ Dicatat sebagai mutasi pertama rekening dan muncul di rekening koran sebagai
                    <b>Saldo awal</b>. Jurnal: Ekuitas Saldo Awal (debit) dan Simpanan anggota (kredit). Hanya bisa diisi
                    satu kali per rekening, sebelum ada transaksi.</div>
                <button class="btn btn-primary full" type="submit">Simpan Saldo Awal</button>
            </form>
        </section>
    @endif
@endsection
