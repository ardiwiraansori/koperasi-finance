<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ganti Kata Sandi · {{ \App\Models\Setting::cooperativeName() }}</title>
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/app.css') }}">
    @include('partials.pwa')
</head>

<body class="portal-body">
    <header class="portal-top">
        <div class="branch-switch"><img src="{{ asset('icon-192.png') }}" alt="" width="36" height="36"
                style="border-radius:50%"> {{ \App\Models\Setting::cooperativeName() }}</div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-primary"
                type="submit">Keluar</button></form>
    </header>
    <main class="portal" id="main">
        <div class="page-heading">
            <div><span class="eyebrow">Akun Saya</span>
                <h1>Ganti Kata Sandi</h1>
                <p><a href="{{ route('portal.home') }}">← Kembali ke akun saya</a></p>
            </div>
        </div>
        @if (session('success'))
            <div class="alert success" role="status">✓ {{ session('success') }}</div>
        @endif
        <section class="card" style="max-width:520px">
            <div class="card-head">
                <div>
                    <h2>Kata Sandi Baru</h2>
                    <p>Isi kata sandi lama untuk memastikan ini benar Anda</p>
                </div>
            </div>
            @include('partials.password-form')
        </section>
    </main>
</body>

</html>
