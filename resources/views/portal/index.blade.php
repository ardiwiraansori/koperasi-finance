<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akun Saya · {{ \App\Models\Setting::cooperativeName() }}</title>
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/app.css') }}">
    @include('partials.pwa')
</head>

<body class="portal-body">
    @php($rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.'))
    @php($dash = fn($x) => filled($x) ? e($x) : '<span class="muted">—</span>')
    <header class="portal-top">
        <div class="branch-switch"><img src="{{ asset('icon-192.png') }}" alt="" width="36" height="36"
                style="border-radius:50%"> {{ \App\Models\Setting::cooperativeName() }}</div>
        <div style="display:flex;gap:8px;align-items:center"><a class="btn btn-light"
                href="{{ route('password.edit') }}">Ganti Kata Sandi</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-primary"
                    type="submit">Keluar</button></form>
        </div>
    </header>
    <main class="portal" id="main">
        <div class="page-heading">
            <div><span class="eyebrow">Akun Saya</span>
                <h1>Halo, {{ $member->name }}</h1>
                <p>{{ $member->member_no }} · {{ $member->branch }} · Anggota sejak
                    {{ $member->joined_at->translatedFormat('d F Y') }}</p>
            </div>
        </div>

        <div class="member-kpis">
            <div class="card"><span>Total
                    Simpanan</span><strong>{{ $rp($member->savings->sum('balance')) }}</strong><small>{{ $member->savings->count() }}
                    rekening simpanan</small></div>
            <div class="card"><span>Sisa
                    Pinjaman</span><strong>{{ $rp($running->sum('outstanding')) }}</strong><small>{{ $running->count() }}
                    pinjaman berjalan</small></div>
            <div class="card"><span>Angsuran Berikutnya</span>
                @if ($next)
                    @php($days = (int) today()->diffInDays($next->next_due_at, false))
                    <strong>{{ $rp($next->installment) }}</strong><small>{{ $next->next_due_at->translatedFormat('d F Y') }}
                        ·
                        {{ $days > 0 ? $days . ' hari lagi' : ($days === 0 ? 'jatuh tempo hari ini' : 'terlambat ' . -$days . ' hari') }}</small>
                @else
                    <strong>—</strong><small>Tidak ada angsuran berjalan</small>
                @endif
            </div>
        </div>

        {{-- Angka gabungan seluruh koperasi (bukan data anggota lain). Dua kolom di tablet, satu kolom di HP. --}}
        @php($q = $coop['quality'])
        <section class="coop" aria-labelledby="coop-title">
            <div class="coop-head">
                <h2 id="coop-title">Kondisi Koperasi</h2>
                <p>Angka gabungan seluruh anggota. Data anggota lain tidak ditampilkan.</p>
            </div>
            <div class="coop-kpis">
                <div class="card coop-card"><span>Total Simpanan</span><strong>{{ $rp($coop['savings']) }}</strong><small>seluruh
                        anggota</small></div>
                <div class="card coop-card"><span>Pinjaman Beredar</span><strong>{{ $rp($coop['loans']) }}</strong><small>{{ $coop['loanCount'] }}
                        pinjaman berjalan</small></div>
                <div class="card coop-card {{ $coop['arrearsCount'] ? 'warn' : '' }}"><span>Tunggakan</span><strong>{{ $rp($coop['arrears']) }}</strong><small>
                        @if ($coop['arrearsCount'])
                            {{ $coop['arrearsCount'] }} pinjaman · {{ $coop['arrearsPct'] }}% dari pinjaman beredar
                        @else
                            Tidak ada tunggakan
                        @endif
                    </small></div>
                <div class="card coop-card coop-quality">
                    <span>Kualitas Pinjaman</span>
                    <div class="quality-head"><strong>{{ $q['score'] }}<small>/100</small></strong><em>{{ $q['verdict'] }}</em></div>
                    <div class="quality-bar" role="img"
                        aria-label="Lancar {{ $q['goodPct'] }} persen, terlambat 1 sampai 90 hari {{ $q['watchPct'] }} persen, terlambat di atas 90 hari {{ $q['npl'] }} persen">
                        <i class="ok" style="width:{{ $q['goodPct'] }}%"></i><i class="watch"
                            style="width:{{ $q['watchPct'] }}%"></i><i class="bad" style="width:{{ $q['npl'] }}%"></i>
                    </div>
                    <ul class="quality-legend">
                        <li><i class="ok"></i>Lancar <b>{{ $q['goodPct'] }}%</b></li>
                        <li><i class="watch"></i>Terlambat 1–90 hari <b>{{ $q['watchPct'] }}%</b></li>
                        <li><i class="bad"></i>Terlambat di atas 90 hari (bermasalah) <b>{{ $q['npl'] }}%</b></li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Simpanan</h2>
                    <p>Saldo per jenis simpanan</p>
                </div>
            </div>
            <div class="account-list">
                @foreach ($member->savings as $saving)
                    <div class="account-row">
                        <div>
                            <div><strong>Simpanan {{ $saving->type }}</strong><small>{{ $saving->account_no }}</small>
                            </div>
                        </div><strong>{{ $rp($saving->balance) }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Pinjaman</h2>
                    <p>Fasilitas pinjaman Anda</p>
                </div>
            </div>
            @forelse($member->loans as $loan)
                <div class="loan-mini">
                    <div><strong>{{ $loan->loan_no }}</strong><small>{{ $loan->product }} · {{ $loan->term_months }}
                            bulan · cicilan {{ $rp($loan->installment) }}</small></div>
                    <div class="right"><span
                            class="badge {{ $loan->display_status === 'Lunas' ? 'neutral' : ($loan->display_status === 'Tunggakan' ? 'danger' : 'success') }}">{{ $loan->display_status }}</span><strong>{{ $rp($loan->outstanding) }}</strong>
                    </div>
            </div>@empty<p class="empty">Belum ada pinjaman.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Transaksi Terakhir</h2>
                    <p>10 transaksi terbaru</p>
                </div>
            </div>
            @forelse($transactions as $trx)
                <div class="portal-trx">
                    <div><strong>{{ $trx->description }}</strong><small>{{ $trx->trx_date->translatedFormat('d M Y') }}
                            · {{ $trx->category }}</small></div><strong
                        class="amount {{ $trx->direction === 'in' ? 'in' : 'out' }}">{{ $trx->direction === 'in' ? '+' : '−' }}
                        {{ $rp($trx->amount) }}</strong>
            </div>@empty<p class="empty">Belum ada transaksi.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Data Saya</h2>
                    <p>Untuk mengubah data, hubungi pengurus koperasi</p>
                </div>
            </div>
            <dl class="data-list">
                <div>
                    <dt>Nomor HP</dt>
                    <dd>{!! $dash($member->phone) !!}</dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd>{!! $dash($member->email) !!}</dd>
                </div>
                <div class="wide">
                    <dt>Alamat</dt>
                    <dd>{!! $dash($member->address) !!}</dd>
                </div>
                <div class="wide">
                    <dt>Ahli waris</dt>
                    <dd>{!! filled($member->heir_name)
                        ? e($member->heir_name) . ' <span class="muted">(' . e($member->heir_relation) . ')</span>'
                        : '<span class="muted">—</span>' !!}</dd>
                </div>
            </dl>
        </section>
    </main>
</body>

</html>
