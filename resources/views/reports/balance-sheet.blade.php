@extends('layouts.app')
@section('title', 'Neraca')
@section('content')
    @php($rp = fn($n) => ($n < -0.004 ? '(' : '') . 'Rp ' . number_format(abs($n), 0, ',', '.') . ($n < -0.004 ? ')' : ''))
    <div class="page-heading">
        <div>
            <p class="eyebrow">LAPORAN KEUANGAN</p>
            <h1>Neraca</h1>
            <p>Posisi aset, kewajiban, dan ekuitas koperasi pada satu tanggal, dari jurnal yang sudah Posted.</p>
        </div>
        <div class="heading-actions no-print"><a class="btn btn-light"
                href="{{ route('reports.balance', array_merge(request()->query(), ['export' => 1])) }}">↓ Export
                CSV</a><button class="btn btn-primary" type="button" onclick="window.print()">Cetak</button></div>
    </div>

    <form method="get" action="{{ route('reports.balance') }}" class="card no-print" style="margin-bottom:14px">
        <div class="toolbar">
            <label style="font-size:13px;color:#64748b">Per tanggal <input type="date" name="tanggal"
                    value="{{ $date }}" max="{{ now()->toDateString() }}"
                    style="height:36px;border:1px solid var(--line);border-radius:9px;padding:0 10px;font-size:13px;margin-left:6px"></label>
            <label style="font-size:13px;color:#64748b;display:flex;align-items:center;gap:6px"><input type="checkbox"
                    name="bandingkan" value="1" @checked($compare) style="width:16px;height:16px"> Bandingkan
                dengan akhir tahun lalu ({{ \Carbon\Carbon::parse($prevDate)->format('d M Y') }})</label>
            <button class="btn btn-primary" type="submit">Tampilkan</button>
        </div>
        @error('tanggal')
            <small class="negative" style="padding:0 16px 12px;display:block">{{ $message }}</small>
        @enderror
    </form>
    <section class="card">
        <div class="card-head">
            <div>
                <h2>Neraca per {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</h2>
                <p>{{ config('app.name') }}</p>
            </div>
            @if ($now['balanced'])
            <span class="badge success">✓ Aset = Kewajiban + Ekuitas</span>@else<span class="badge danger">⚠ Tidak
                    seimbang: selisih {{ $rp($now['totalAssets'] - $now['totalLiabilities'] - $now['totalEquity']) }}</span>
            @endif
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Akun</th>
                        <th class="right">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</th>
                        @if ($compare)
                            <th class="right">{{ \Carbon\Carbon::parse($prevDate)->format('d M Y') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach (['Aset' => ['totalAssets', 'Total Aset'], 'Kewajiban' => ['totalLiabilities', 'Total Kewajiban'], 'Ekuitas' => ['totalEquity', 'Total Ekuitas']] as $group => [$totalKey, $totalLabel])
                        <tr class="stmt-head">
                            <td colspan="{{ $compare ? 3 : 2 }}">{{ strtoupper($group) }}</td>
                        </tr>
                        @forelse($lines[$group] as $r)
                            <tr>
                                <td>{{ $r['code'] }} — {{ $r['name'] }}</td>
                                <td class="right">{{ $rp($r['a']) }}</td>
                                @if ($compare)
                                    <td class="right">{{ $rp($r['b']) }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $compare ? 3 : 2 }}" class="empty">Belum ada saldo.</td>
                            </tr>
                        @endforelse
                        @if ($group === 'Ekuitas')
                            <tr>
                                <td>SHU Tahun Berjalan <small class="block">pendapatan − beban sejak 1 Januari
                                        {{ substr($date, 0, 4) }}</small></td>
                                <td class="right">{{ $rp($now['shuCurrent']) }}</td>
                                @if ($compare)
                                    <td class="right">{{ $rp($prev['shuCurrent']) }}</td>
                                @endif
                            </tr>
                            <tr>
                                <td>SHU Tahun Sebelumnya <small class="block">belum dibagi / belum ditutup ke
                                        cadangan</small></td>
                                <td class="right">{{ $rp($now['shuPrior']) }}</td>
                                @if ($compare)
                                    <td class="right">{{ $rp($prev['shuPrior']) }}</td>
                                @endif
                            </tr>
                        @endif
                        <tr class="stmt-total">
                            <td>{{ $totalLabel }}</td>
                            <td class="right">{{ $rp($now[$totalKey]) }}</td>
                            @if ($compare)
                                <td class="right">{{ $rp($prev[$totalKey]) }}</td>
                            @endif
                        </tr>
                    @endforeach
                    <tr class="stmt-total">
                        <td>TOTAL KEWAJIBAN DAN EKUITAS</td>
                        <td class="right">{{ $rp($now['totalLiabilities'] + $now['totalEquity']) }}</td>
                        @if ($compare)
                            <td class="right">{{ $rp($prev['totalLiabilities'] + $prev['totalEquity']) }}</td>
                        @endif
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-footer"><span>Dicetak {{ now()->format('d M Y H:i') }} oleh {{ auth()->user()->name }}. Angka
                dalam kurung adalah nilai negatif (pengurang). Pembukuan belum ditutup per tahun, jadi SHU tahun lalu tampil
                terpisah di Ekuitas.</span></div>
    </section>

    @if ($reconciliation)
        <section class="card no-print" style="margin-top:16px">
            <div class="card-head">
                <div>
                    <h2>Rekonsiliasi dengan Data Rekening</h2>
                    <p>Saldo akun di jurnal harus sama dengan saldo rekening simpanan dan pinjaman</p>
                </div>
                @if (collect($reconciliation)->every(fn($r) => abs($r['diff']) < 0.01))
                <span class="badge success">✓ Semua cocok</span>@else<span class="badge warning">⚠ Ada selisih</span>
                @endif
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th class="right">Saldo di jurnal</th>
                            <th class="right">Saldo rekening / pinjaman</th>
                            <th class="right">Selisih</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reconciliation as $r)
                            <tr>
                                <td>{{ $r['code'] }} — {{ $r['name'] }}</td>
                                <td class="right">{{ $rp($r['ledger']) }}</td>
                                <td class="right">{{ $rp($r['sub']) }}</td>
                                <td class="right {{ abs($r['diff']) < 0.01 ? '' : 'negative' }}">
                                    {{ abs($r['diff']) < 0.01 ? '-' : $rp($r['diff']) }}</td>
                                <td>{!! abs($r['diff']) < 0.01
                                    ? '<span class="badge success">Cocok</span>'
                                    : '<span class="badge warning">Periksa</span>' !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer"><span>Selisih biasanya berarti saldo awal simpanan atau pinjaman anggota lama belum
                    diisi (menu Simpanan → Saldo Awal), atau saldo diubah langsung di database tanpa transaksi.</span></div>
        </section>
    @endif
@endsection
