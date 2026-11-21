{{-- Formulir ganti kata sandi, dipakai halaman petugas dan portal anggota. --}}
<form method="post" action="{{ route('password.update') }}" class="transaction-form" novalidate>
    @csrf @method('PUT')
    <label>Kata sandi lama<input name="current_password" type="password" autocomplete="current-password" required></label>
    <label>Kata sandi baru<input name="password" type="password" autocomplete="new-password" minlength="8"
            required><small>Minimal 8 karakter dan berbeda dari yang lama.</small></label>
    <label>Ulangi kata sandi baru<input name="password_confirmation" type="password" autocomplete="new-password"
            minlength="8" required></label>
    @if ($errors->any())
        <small class="negative" role="alert">{{ $errors->first() }}</small>
    @endif
    <button class="btn btn-primary full" type="submit">Simpan Kata Sandi</button>
</form>
