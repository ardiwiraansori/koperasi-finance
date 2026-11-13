<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    @include('partials.pwa')
</head>
<body>
<main style="min-height:100vh;display:grid;place-items:center;padding:18px">
    <div class="card transaction-form" style="width:100%;max-width:380px;padding:8px 0">
        <div class="card-head"><div><h2>Masuk ke {{ config('app.name') }}</h2><p>Pengurus: email. Anggota: nomor anggota (contoh AG000128).</p></div></div>
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            @if($errors->any())<div class="alert danger">{{ $errors->first() }}</div>@endif
            <label>Email atau Nomor Anggota<input type="text" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
            <label>Kata sandi<input type="password" name="password" required autocomplete="current-password"></label>
            <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="remember" value="1" style="width:auto;height:auto;margin:0"> Ingat saya</label>
            <button class="btn btn-primary full" type="submit">Masuk</button>
        </form>
    </div>
</main>
</body>
</html>
