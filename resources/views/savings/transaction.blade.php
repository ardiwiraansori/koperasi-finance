@extends('layouts.app')
@php($title = $withdraw ? 'Penarikan Simpanan' : 'Setoran Simpanan')
@section('title', $title)
@section('content')
    <a class="back-link" href="{{ route('savings.index') }}">
        ← Kembali ke Simpanan</a>
    <div class="page-heading">
        <div>
            <p class="eyebrow">SIMPANAN</p>
            <h1>{{ $title }}</h1>
            <p>Saldo rekening {{ $withdraw ? 'berkurang' : 'bertambah' }} dan jurnal dibuat otomatis.</p>
        </div>
    </div>
    <section class="card transaction-form" style="max-width:680px">
        <form method="post" action="{{ route($withdraw ? 'savings.withdraw.store' : 'savings.deposit.store') }}" novalidate>
            @csrf
            <label>Rekening <span>ketik nomor rekening, nama anggota, atau jenis simpanan</span>
                <input list="dl-rekening" data-combobox="saving_id" autocomplete="off"
                    placeholder="Contoh: Budi, SAV-0128, Wajib" autofocus>
                <datalist id="dl-rekening">
                    @foreach ($savings as $s)
                        <option
                            value="{{ $s->account_no }} — {{ $s->member->name }} · Simpanan {{ $s->type }} (saldo Rp {{ number_format($s->balance, 0, ',', '.') }})"
                            data-id="{{ $s->id }}"></option>
                    @endforeach
                </datalist>
                <input type="hidden" name="saving_id" value="{{ old('saving_id', $selected ?: '') }}">
                @error('saving_id')
                    <small class="negative">{{ $message }}</small>
                @enderror
            </label>
            <label>Nominal<div class="money-input"><span>Rp</span><input name="amount" data-rupiah inputmode="numeric"
                        autocomplete="off" value="{{ old('amount') }}" placeholder="0" required></div>
                @error('amount')
                    <small class="negative">{{ $message }}</small>
                @enderror
            </label>
            <label>Keterangan <span>opsional</span><input name="description" maxlength="200"
                    value="{{ old('description') }}" placeholder="Kosongkan untuk keterangan otomatis">
                @error('description')
                    <small class="negative">{{ $message }}</small>
                @enderror
            </label>
            <div class="form-note">ⓘ <b>Aturan:</b>
                @if ($withdraw)
                    Simpanan Sukarela boleh ditarik kapan saja sampai saldo habis. Simpanan Pokok dan Wajib hanya bisa
                    ditarik setelah anggota keluar (status Nonaktif). Penarikan tidak boleh melebihi saldo, dan anggota yang
                    masih punya pinjaman berjalan tidak bisa menarik simpanan.
                @else
                    hanya anggota aktif. Simpanan Pokok dibayar satu kali. Simpanan Wajib dibayar rutin tiap bulan. Simpanan
                    Sukarela boleh kapan saja dan berapa saja.
                @endif
            </div>
            <button class="btn btn-primary full"
                type="submit">{{ $withdraw ? 'Simpan Penarikan' : 'Simpan Setoran' }}</button>
        </form>
    </section>
@endsection
