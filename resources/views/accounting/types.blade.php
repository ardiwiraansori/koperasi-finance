@extends('layouts.app')
@section('title', 'Jenis Transaksi')
@section('content')
    @php($accountant = \App\Models\Setting::hasAccountant())
    <div class="page-heading">
        <div>
            <p class="eyebrow">AKUNTANSI</p>
            <h1>Jenis Transaksi Kasir</h1>
            <p>Kasir memilih jenis transaksi dengan bahasa sehari-hari. Di sini ditentukan akun jurnal untuk tiap jenis.</p>
        </div>
    </div>
    @include('accounting._tabs')

    <div class="form-note" style="margin-bottom:14px">ⓘ Penerimaan memakai akun <b>pendapatan</b>, pengeluaran memakai akun
        <b>beban</b>. Mengubah akun hanya berlaku untuk transaksi berikutnya; jurnal yang sudah dibuat tidak berubah. Jenis
        yang sudah dipakai tidak dihapus, tetapi bisa dinonaktifkan agar tidak muncul di Kasir.</div>

    <section class="card" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <h2>Daftar Jenis</h2>
                <p>{{ $types->where('active', true)->count() }} aktif dari {{ $types->count() }} jenis</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table" style="min-width:760px">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Nama jenis (dilihat kasir)</th>
                        <th>Akun jurnal</th>
                        <th>Dipakai</th>
                        <th>Aktif</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($types as $type)
                        <tr>
                            <td>
                                <form id="t{{ $type->id }}" method="post"
                                    action="{{ route('accounting.types.update', $type) }}">@csrf @method('PUT')</form>
                                <span
                                    class="badge {{ $type->category === 'Penerimaan' ? 'success' : 'warning' }}">{{ $type->category }}</span>
                            </td>
                            <td><input form="t{{ $type->id }}" aria-label="Nama jenis {{ $type->name }}"
                                    name="name" value="{{ $type->name }}" maxlength="60" required
                                    style="width:100%;min-width:180px;height:36px;border:1px solid var(--line);border-radius:8px;padding:0 8px;font-size:14px">
                            </td>
                            @if ($accountant)
                                <td><select form="t{{ $type->id }}" aria-label="Akun jurnal untuk {{ $type->name }}"
                                        name="account_code"
                                        style="width:100%;height:36px;border:1px solid var(--line);border-radius:8px;font-size:14px">
                                        @foreach ($eligible[$type->category] as $code => $name)
                                            <option value="{{ $code }}" @selected($type->account_code === (string) $code)>
                                                {{ $code }} — {{ $name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            @else
                                <td>{{ $type->account_code }} — {{ $type->accountName() }} <small
                                        class="block">otomatis</small></td>
                            @endif
                            <td>{{ $type->transactions_count }}×</td>
                            <td><input form="t{{ $type->id }}" aria-label="Aktifkan {{ $type->name }}"
                                    type="checkbox" name="active" value="1" @checked($type->active)
                                    style="width:18px;height:18px"></td>
                            <td class="right"><button form="t{{ $type->id }}" class="btn btn-primary small"
                                    type="submit">Simpan</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($errors->any())
            <small class="negative" style="display:block;padding:10px 16px">{{ $errors->first() }}</small>
        @endif
    </section>

    <section class="card transaction-form" style="max-width:680px">
        <div class="card-head">
            <div>
                <h2>Tambah Jenis Baru</h2>
                <p>Contoh: "Sewa kantor" (pengeluaran) atau "Jasa titipan" (penerimaan)</p>
            </div>
        </div>
        <form method="post" action="{{ route('accounting.types.store') }}" novalidate>
            @csrf
            <div class="form-row">
                <label>Kategori<select name="category" required data-type-category>
                        @foreach (\App\Models\TransactionType::CATEGORIES as $c)
                            <option @selected(old('category') === $c)>{{ $c }}</option>
                        @endforeach
                    </select></label>
                <label>Nama jenis<input name="name" maxlength="60" value="{{ old('name') }}"
                        placeholder="Nama yang dilihat kasir" required></label>
            </div>
            @if ($accountant)
                <label>Akun jurnal<select name="account_code" required>
                        <option value="">Pilih akun…</option>
                        @foreach ($eligible as $category => $accounts)
                            @foreach ($accounts as $code => $name)
                                <option value="{{ $code }}" data-category="{{ $category }}"
                                    @selected(old('account_code') === (string) $code)>{{ $code }} — {{ $name }}</option>
                            @endforeach
                        @endforeach
                    </select></label>
            @else
                <div class="form-note">ⓘ Akun jurnal diisi otomatis (penerimaan masuk ke "Penerimaan lain-lain", pengeluaran
                    ke "Pengeluaran lain-lain"). Cukup beri nama yang dipahami kasir.</div>
            @endif
            <button class="btn btn-primary full" type="submit">Tambah Jenis</button>
        </form>
    </section>
@endsection
