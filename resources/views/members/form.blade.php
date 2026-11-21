@extends('layouts.app')
@section('title', $member->exists ? 'Ubah Anggota' : 'Tambah Anggota')
@section('content')
    @php($M = \App\Models\Member::class)
    @php($v = fn(string $k) => old($k, $member->$k instanceof \DateTimeInterface ? $member->$k->toDateString() : $member->$k))
    @php($err = fn(string $k) => $errors->has($k) ? 'aria-invalid="true" aria-describedby="e-' . $k . '"' : '')
    <a class="back-link" href="{{ $member->exists ? route('members.show', $member) : route('members.index') }}">
        ← Kembali</a>
    <div class="page-heading">
        <div>
            <p class="eyebrow">KEANGGOTAAN</p>
            <h1>{{ $member->exists ? 'Ubah Anggota' : 'Tambah Anggota' }}</h1>
            <p>{{ $member->exists ? $member->member_no . ' · lengkapi data agar formulir keanggotaan bisa dicetak' : 'Hanya yang bertanda * wajib. Data lain boleh dilengkapi nanti.' }}
            </p>
        </div>
    </div>

    <form method="post" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}" novalidate
        class="member-form">
        @csrf
        @if ($member->exists)
            @method('PUT')
        @endif

        <fieldset class="card form-section transaction-form">
            <legend>Data Pribadi</legend>
            <label>Nama lengkap *<input name="name" value="{{ $v('name') }}" maxlength="100" required
                    autocomplete="name" {!! $err('name') !!}>
                @error('name')
                    <small class="negative" id="e-name">{{ $message }}</small>
                @enderror
            </label>
            <label>NIK (sesuai KTP) <span>opsional, 16 angka</span><input name="nik" value="{{ $v('nik') }}"
                    inputmode="numeric" maxlength="19" autocomplete="off" placeholder="3201234567890001"
                    {!! $err('nik') !!}>
                @error('nik')
                    <small class="negative" id="e-nik">{{ $message }}</small>
                @enderror
            </label>
            <div class="choice-group" role="radiogroup" aria-labelledby="g-gender">
                <span id="g-gender" class="choice-label">Jenis kelamin <span>opsional</span></span>
                <div class="choices">
                    @foreach ($M::GENDERS as $key => $label)
                        <label class="choice"><input type="radio" name="gender" value="{{ $key }}"
                                @checked($v('gender') === $key)><span>{{ $label }}</span></label>
                    @endforeach
                </div>
                @error('gender')
                    <small class="negative">{{ $message }}</small>
                @enderror
            </div>
            <div class="form-row">
                <label>Tempat lahir <span>opsional</span><input name="birth_place" value="{{ $v('birth_place') }}"
                        maxlength="60" autocomplete="off">
                    @error('birth_place')
                        <small class="negative">{{ $message }}</small>
                    @enderror
                </label>
                <label>Tanggal lahir <span>opsional</span><input name="birth_date" type="date"
                        value="{{ $v('birth_date') }}" max="{{ now()->subYears(17)->toDateString() }}"
                        {!! $err('birth_date') !!}>
                    @error('birth_date')
                        <small class="negative" id="e-birth_date">{{ $message }}</small>
                    @enderror
                </label>
            </div>
            <div class="form-row">
                <label>Status perkawinan <span>opsional</span><select name="marital_status">
                        <option value="">Pilih…</option>
                        @foreach ($M::MARITAL as $m)
                            <option @selected($v('marital_status') === $m)>{{ $m }}</option>
                        @endforeach
                    </select>
                    @error('marital_status')
                        <small class="negative">{{ $message }}</small>
                    @enderror
                </label>
                <label>Pekerjaan <span>opsional</span><input name="occupation" value="{{ $v('occupation') }}"
                        maxlength="80" autocomplete="off">
                    @error('occupation')
                        <small class="negative">{{ $message }}</small>
                    @enderror
                </label>
            </div>
        </fieldset>

        <fieldset class="card form-section transaction-form">
            <legend>Kontak dan Alamat</legend>
            <div class="form-row">
                <label>Nomor HP <span>opsional</span><input name="phone" type="tel" inputmode="tel"
                        value="{{ $v('phone') }}" placeholder="081234567890" autocomplete="tel" {!! $err('phone') !!}>
                    @error('phone')
                        <small class="negative" id="e-phone">{{ $message }}</small>
                    @enderror
                </label>
                <label>Email <span>opsional</span><input name="email" type="email" value="{{ $v('email') }}"
                        autocomplete="email" {!! $err('email') !!}>
                    @error('email')
                        <small class="negative" id="e-email">{{ $message }}</small>
                    @enderror
                </label>
            </div>
            <label>Alamat <span>opsional</span>
                <textarea name="address" rows="3" maxlength="500" autocomplete="street-address">{{ $v('address') }}</textarea>
                @error('address')
                    <small class="negative">{{ $message }}</small>
                @enderror
            </label>
        </fieldset>

        <fieldset class="card form-section transaction-form">
            <legend>Ahli Waris <span>opsional</span></legend>
            <p class="form-hint">Orang yang berhak menerima simpanan bila anggota meninggal dunia.</p>
            <label>Nama ahli waris<input name="heir_name" value="{{ $v('heir_name') }}" maxlength="100" autocomplete="off">
                @error('heir_name')
                    <small class="negative">{{ $message }}</small>
                @enderror
            </label>
            <div class="form-row">
                <label>Hubungan<select name="heir_relation" {!! $err('heir_relation') !!}>
                        <option value="">Pilih…</option>
                        @foreach ($M::HEIR_RELATIONS as $r)
                            <option @selected($v('heir_relation') === $r)>{{ $r }}</option>
                        @endforeach
                    </select>
                    @error('heir_relation')
                        <small class="negative" id="e-heir_relation">{{ $message }}</small>
                    @enderror
                </label>
                <label>Nomor HP ahli waris<input name="heir_phone" type="tel" inputmode="tel"
                        value="{{ $v('heir_phone') }}" placeholder="081234567890" {!! $err('heir_phone') !!}>
                    @error('heir_phone')
                        <small class="negative" id="e-heir_phone">{{ $message }}</small>
                    @enderror
                </label>
            </div>
        </fieldset>

        <fieldset class="card form-section transaction-form">
            <legend>Keanggotaan</legend>
            <div class="form-row">
                <label>Cabang *<select name="branch" required>
                        @foreach (\App\Http\Controllers\MemberController::BRANCHES as $b)
                            <option @selected($v('branch') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                    @error('branch')
                        <small class="negative">{{ $message }}</small>
                    @enderror
                </label>
                <label>Status *<select name="status" required>
                        @foreach (\App\Http\Controllers\MemberController::STATUSES as $s)
                            <option @selected($v('status') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <small class="negative">{{ $message }}</small>
                    @enderror
                </label>
            </div>
            <label>Tanggal bergabung *<input name="joined_at" type="date" max="{{ now()->toDateString() }}"
                    value="{{ $v('joined_at') }}" required {!! $err('joined_at') !!}>
                @error('joined_at')
                    <small class="negative" id="e-joined_at">{{ $message }}</small>
                @enderror
            </label>
            @unless ($member->exists)
                <label>Simpanan Pokok (setoran awal) <span>opsional</span>
                    <div class="money-input"><span>Rp</span><input name="simpanan_pokok" data-rupiah inputmode="numeric"
                            autocomplete="off" value="{{ old('simpanan_pokok') }}" placeholder="0"></div>
                    @error('simpanan_pokok')
                        <small class="negative">{{ $message }}</small>
                    @enderror
                </label>
                <p class="form-hint">Dibayar satu kali saat masuk dan masuk ke kas. Kosongkan bila dibayar nanti lewat menu
                    Simpanan → Setoran.</p>
            @endunless
        </fieldset>

        <div class="form-actions">
            <a class="btn btn-light"
                href="{{ $member->exists ? route('members.show', $member) : route('members.index') }}">Batal</a>
            <button class="btn btn-primary"
                type="submit">{{ $member->exists ? 'Simpan Perubahan' : 'Simpan Anggota' }}</button>
        </div>
    </form>
@endsection
