@extends('layouts.app')
@section('title', 'Tunggakan dan Kolektibilitas')
@section('content')
    @php($rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.'))
    <div class="page-heading">
        <div>
            <p class="eyebrow">LAPORAN PINJAMAN</p>
            <h1>Tunggakan dan Kolektibilitas</h1>
            <p>Pinjaman berjalan yang lewat jatuh tempo, dikelompokkan menurut umur tunggakan per
                {{ now()->format('d M Y') }}.</p>
        </div>
        <div class="heading-actions no-print"><a class="btn btn-light"
                href="{{ route('reports.arrears', ['export' => 1]) }}">↓ Export CSV</a><button class="btn btn-primary"
                type="button" onclick="window.print()">Cetak</button></div>
    </div>

    <div class="summary-strip">
        <div><span>Pinjaman menunggak</span><strong>{{ $summary['loans'] }}</strong></div>
        <div><span>Anggota menunggak</span><strong>{{ $summary['members'] }}</strong></div>
        <div><span>Sisa pokok menunggak</span><strong>{{ $rp($summary['outstanding']) }}</strong><small
                class="block">{{ $summary['overduePct'] }}% dari portofolio {{ $rp($summary['portfolio']) }}</small></div>
        <div><span>NPL (di atas 90 hari)</span><strong
                class="{{ $summary['npl'] > 0 ? 'negative' : '' }}">{{ $summary['npl'] }}%</strong><small
                class="block">dari sisa pokok pinjaman berjalan</small></div>
    </div>

    <section class="card" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <h2>Kolektibilitas</h2>
                <p>Sebaran pinjaman berjalan menurut umur tunggakan</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kelompok</th>
                        <th class="right">Pinjaman</th>
                        <th class="right">Sisa Pokok</th>
                        <th class="right">% Portofolio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $g)
                        <tr>
                            <td>{{ $g['label'] }}</td>
                            <td class="right">{{ $g['loans'] }}</td>
                            <td class="right">{{ $rp($g['outstanding']) }}</td>
                            <td class="right">{{ $g['pct'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong>Total pinjaman berjalan</strong></td>
                        <td class="right"><strong>{{ collect($groups)->sum('loans') }}</strong></td>
                        <td class="right"><strong>{{ $rp($summary['portfolio']) }}</strong></td>
                        <td class="right"><strong>100%</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Daftar Pinjaman Menunggak</h2>
                <p>Dari yang terlama. Perkiraan tertunggak = cicilan × jumlah jatuh tempo yang sudah lewat.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Anggota</th>
                        <th>Pinjaman</th>
                        <th>Jatuh Tempo</th>
                        <th class="right">Terlambat</th>
                        <th class="right">Cicilan</th>
                        <th class="right">Perkiraan Tertunggak</th>
                        <th class="right">Sisa Pokok</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td><a class="table-link"
                                    href="{{ route('members.show', $r['member']) }}">{{ $r['member']->name }}</a><small
                                    class="block">{{ $r['member']->member_no }}@if ($r['member']->phone)
                                        · {{ $r['member']->phone }}
                                    @endif
                                </small></td>
                            <td><a class="table-link"
                                    href="{{ route('loans.show', $r['loan']) }}">{{ $r['loan']->loan_no }}</a><small
                                    class="block">{{ $r['bucket'] }}</small></td>
                            <td>{{ $r['loan']->next_due_at->format('d M Y') }}</td>
                            <td class="right">{{ $r['days'] }} hari<small class="block">{{ $r['missed'] }} jatuh tempo
                                    terlewat</small></td>
                            <td class="right">{{ $rp($r['loan']->installment) }}</td>
                            <td class="right negative"><strong>{{ $rp($r['due']) }}</strong></td>
                            <td class="right">{{ $rp($r['outstanding']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty">Tidak ada pinjaman menunggak. 🎉</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="5"><strong>Total</strong></td>
                            <td class="right"><strong>{{ $rp($summary['due']) }}</strong></td>
                            <td class="right"><strong>{{ $rp($summary['outstanding']) }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="table-footer"><span>Dicetak {{ now()->format('d M Y H:i') }} oleh {{ auth()->user()->name }}. Umur
                dihitung sejak jatuh tempo terlama yang belum dibayar. Pengelompokan sama dengan Dashboard; sesuaikan dengan
                kebijakan koperasi bila berbeda. Perkiraan tertunggak paling banyak sebesar sisa pokok ditambah bunga, dan pembayaran
                sebagian (di bawah cicilan) belum dikurangkan. Denda keterlambatan belum ada.</span></div>
    </section>
@endsection
