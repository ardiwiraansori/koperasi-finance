@extends('layouts.app')
@section('title', 'Arus Kas')
@section('content')
    @php($rp = fn($n) => ($n < -0.004 ? '(' : '') . 'Rp ' . number_format(abs($n), 0, ',', '.') . ($n < -0.004 ? ')' : ''))
    @php($d = fn($s) => \Carbon\Carbon::parse($s)->format('d M Y'))
    <div class="page-heading">
        <div>
            <p class="eyebrow">LAPORAN KEUANGAN</p>
            <h1>Arus Kas</h1>
            <p>Dari mana kas masuk dan ke mana kas keluar pada satu periode, dikelompokkan menurut kegiatan.</p>
        </div>
        <div class="heading-actions no-print"><a class="btn btn-light"
                href="{{ route('reports.cashflow', array_merge(request()->query(), ['export' => 1])) }}">↓ Export
                CSV</a><button class="btn btn-primary" type="button" onclick="window.print()">Cetak</button></div>
    </div>

    @include('partials.period-filter', [
        'routeName' => 'reports.cashflow',
        'from' => $from,
        'to' => $to,
        'compare' => null,
    ])

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Laporan Arus Kas</h2>
                <p>{{ \App\Models\Setting::cooperativeName() }} · {{ $d($from) }} s/d {{ $d($to) }}</p>
            </div>
            <span
                class="badge {{ $flow['net'] >= 0 ? 'success' : 'danger' }}">{{ $flow['net'] >= 0 ? 'Kas bertambah' : 'Kas berkurang' }}</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Uraian</th>
                        <th class="right">{{ $d($from) }} – {{ $d($to) }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($flow['sections'] as $section)
                        @continue(!$section['rows'])
                        <tr class="stmt-head">
                            <td colspan="2">{{ strtoupper($section['title']) }}</td>
                        </tr>
                        @foreach ($section['rows'] as $r)
                            <tr>
                                <td>{{ $r['label'] }}</td>
                                <td class="right">{{ $rp($r['amount']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="stmt-total">
                            <td>Jumlah {{ $section['title'] }}</td>
                            <td class="right">{{ $rp($section['total']) }}</td>
                        </tr>
                    @endforeach
                    @if (collect($flow['sections'])->sum(fn($s) => count($s['rows'])) === 0)
                        <tr>
                            <td colspan="2" class="empty">Tidak ada pergerakan kas pada periode ini.</td>
                        </tr>
                    @endif
                    <tr class="stmt-total">
                        <td>KENAIKAN (PENURUNAN) KAS</td>
                        <td class="right">{{ $rp($flow['net']) }}</td>
                    </tr>
                    <tr>
                        <td>Saldo kas awal periode <small class="block">per
                                {{ $d(\Carbon\Carbon::parse($from)->subDay()->toDateString()) }}</small></td>
                        <td class="right">{{ $rp($flow['opening']) }}</td>
                    </tr>
                    <tr class="stmt-total stmt-result">
                        <td>SALDO KAS AKHIR PERIODE</td>
                        <td class="right">{{ $rp($flow['closing']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-footer"><span>
                @if ($flow['matches'])
                    ✓ Saldo akhir sama dengan saldo Kas Teller di buku besar.
                @else
                    ⚠ Saldo akhir berbeda dari Kas Teller di buku besar ({{ $rp($flow['ledgerClosing']) }}). Periksa jurnal
                    yang menyentuh kas.
                @endif Dicetak {{ now()->format('d M Y H:i') }} oleh {{ auth()->user()->name }}.
                Angka dalam kurung bernilai negatif.
            </span></div>
    </section>
@endsection
