@extends('layouts.app')
@section('title', 'Pencairan')
@section('content')
    @php($rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.'))
    <div class="page-heading">
        <div>
            <p class="eyebrow">KASIR</p>
            <h1>Pencairan Pinjaman</h1>
            <p>Pinjaman yang sudah disetujui admin. Serahkan uangnya lalu klik Cairkan; jurnal dan sisa pokok tercatat
                otomatis.</p>
        </div>
        <div class="heading-actions"><span class="badge neutral">Saldo kas {{ $rp($cash) }}</span></div>
    </div>
    <div class="journal-list">
        @forelse($loans as $loan)
            @php($lines = $payoffs[$loan->id] ?? [])
            @php($net = $loan->principal - array_sum(array_column($lines, 'total')))
            <div class="card">
                <div class="approval-request">
                    <span class="approval-icon green large">₿</span>
                    <div class="grow">
                        <div class="request-top"><span>PENCAIRAN · {{ $loan->loan_no }}</span><small>Diajukan
                                {{ $loan->applied_at->format('d M Y') }}@if ($loan->approvedBy)
                                    · disetujui {{ $loan->approvedBy->name }}
                                @endif
                            </small>
                        </div>
                        <h3><a class="table-link"
                                href="{{ route('members.show', $loan->member) }}">{{ $loan->member->name }}</a>
                            <small>{{ $loan->member->member_no }}</small></h3>
                        <p>Plafon {{ $rp($loan->principal) }} · Tenor {{ $loan->term_months }} bulan · Bunga
                            {{ number_format($loan->interest_rate, 1, ',', '.') }}% per tahun · Cicilan
                            {{ $rp($loan->installment) }}</p>
                        <p>Uang yang diserahkan ke anggota: <strong>{{ $rp($net) }}</strong>
                            @if ($net > $cash)
                                <b class="negative">Saldo kas tidak cukup.</b>
                            @endif
                        </p>
                        @if ($lines)
                            @include('partials.loan-payoff', [
                                'lines' => $lines,
                                'principal' => $loan->principal,
                            ])
                        @endif
                    </div>
                    <div class="request-actions">
                        <form method="post" action="{{ route('loans.disburse', $loan) }}"
                            data-dialog="Cairkan pinjaman {{ $loan->loan_no }}"
                            data-dialog-text="{{ 'Cairkan ' . $rp($loan->principal) . ' ke ' . $loan->member->name . '? ' . ($lines ? 'Pinjaman lama dilunasi dulu; uang yang diserahkan ' . $rp($net) . '.' : 'Kas akan berkurang.') }}"
                            data-dialog-ok="Cairkan">@csrf<button class="btn btn-primary" type="submit">Cairkan</button>
                        </form>
                        <a class="btn btn-primary" href="{{ route('loans.show', $loan) }}">Detail</a>
                    </div>
                </div>
            </div>
            @empty
                <div class="card">
                    <div class="empty">Tidak ada pinjaman yang menunggu dicairkan.</div>
                </div>
            @endforelse
        </div>
    @endsection
