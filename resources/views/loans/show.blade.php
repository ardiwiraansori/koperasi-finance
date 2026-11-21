@extends('layouts.app')
@section('title', $loan->loan_no)
@section('content')
    @php($rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.'))
    @php($badges = ['Tunggakan' => 'danger', 'Menunggu Approval' => 'warning', 'Disetujui' => 'warning', 'Lunas' => 'neutral', 'Ditolak' => 'neutral', 'Aktif' => 'success'])
    <a class="back-link no-print" href="{{ route('loans.index') }}">
        ← Kembali ke Pinjaman</a>
    <div class="profile-head card">
        <div class="profile-main"><span class="avatar xl">{{ mb_strtoupper(mb_substr($loan->member->name, 0, 1)) }}</span>
            <div>
                <div class="title-row">
                    <h1>{{ $loan->loan_no }}</h1><span
                        class="badge {{ $badges[$loan->display_status] ?? 'neutral' }}">{{ $loan->display_status }}</span>
                </div>
                <p>{{ $loan->product }} · bunga {{ number_format($loan->interest_rate, 1, ',', '.') }}% per tahun ·
                    {{ $loan->term_months }} bulan</p>
                <div class="contact-row"><a class="table-link"
                        href="{{ route('members.show', $loan->member) }}">{{ $loan->member->name }}
                        ({{ $loan->member->member_no }})</a><span>Diajukan {{ $loan->applied_at->format('d M Y') }}</span>
                    @if ($loan->disbursed_at)
                        <span>Dicairkan {{ $loan->disbursed_at->format('d M Y') }}</span>
                    @endif
                </div>
                @if ($loan->appliedBy || $loan->approvedBy || $loan->disbursedBy)
                    <p class="muted">
                        @if ($loan->appliedBy)
                            Diajukan oleh {{ $loan->appliedBy->name }}
                            @endif @if ($loan->approvedBy)
                                · Disetujui oleh {{ $loan->approvedBy->name }}
                                @endif @if ($loan->disbursedBy)
                                    · Dicairkan oleh {{ $loan->disbursedBy->name }}
                                @endif
                    </p>
                @endif
                @if ($loan->settledBy)
                    <p class="muted">Dilunasi lewat top-up <a class="table-link"
                            href="{{ route('loans.show', $loan->settledBy) }}">{{ $loan->settledBy->loan_no }}</a></p>
                @endif
                @if ($loan->settledLoans->isNotEmpty())
                    <p class="muted">Top-up: melunasi @foreach ($loan->settledLoans as $old)
                            <a class="table-link"
                                href="{{ route('loans.show', $old) }}">{{ $old->loan_no }}</a>{{ $loop->last ? '' : ', ' }}
                        @endforeach
                    </p>
                @endif
                @if ($payoff)
                    @include('partials.loan-payoff', ['lines' => $payoff, 'principal' => $loan->principal])
                @endif
                @if ($loan->status === 'Ditolak' && $loan->note)
                    <p class="negative">Ditolak: {{ $loan->note }}</p>
                @endif
            </div>
        </div>
        <div class="heading-actions no-print">
            @if (auth()->user()->allows('loan.decide') && $loan->status === 'Menunggu Approval')
                <form method="post" action="{{ route('loans.approve', $loan) }}">@csrf<button class="btn btn-primary"
                        type="submit">Setujui</button></form>
            @endif
            @if (auth()->user()->allows('loan.disburse') && $loan->status === 'Disetujui')
                <form method="post" action="{{ route('loans.disburse', $loan) }}"
                    data-dialog="Cairkan pinjaman {{ $loan->loan_no }}"
                    data-dialog-text="{{ 'Cairkan ' . $rp($loan->principal) . ' ke ' . $loan->member->name . '? ' . ($payoff ? 'Pinjaman lama dilunasi dulu; uang keluar dari kas sekitar ' . $rp($loan->principal - array_sum(array_column($payoff, 'total'))) . '.' : 'Kas akan berkurang.') }}"
                    data-dialog-ok="Cairkan">@csrf<button class="btn btn-primary" type="submit">Cairkan Dana</button>
                </form>
            @endif
            @if (auth()->user()->allows('loan.decide') && in_array($loan->status, ['Menunggu Approval', 'Disetujui'], true))
                <form method="post" action="{{ route('loans.reject', $loan) }}" class="reject-form"
                    data-dialog="Tolak pengajuan {{ $loan->loan_no }}"
                    data-dialog-text="Pengajuan {{ $loan->member->name }} tidak bisa diproses lagi setelah ditolak."
                    data-dialog-ok="Tolak" data-dialog-reason="Alasan penolakan" data-dialog-danger>@csrf<input
                        type="hidden" name="note"><button class="btn btn-primary" type="submit">Tolak</button></form>
            @endif
            <button class="btn btn-primary" type="button" onclick="window.print()">Cetak</button>
        </div>
    </div>

    <div class="member-kpis">
        <div class="card"><span>Plafon</span><strong>{{ $rp($loan->principal) }}</strong><small>{{ $loan->term_months }}
                bulan</small></div>
        <div class="card"><span>Cicilan per Bulan</span><strong>{{ $rp($loan->installment) }}</strong><small>anuitas,
                bunga {{ number_format($loan->interest_rate, 1, ',', '.') }}%</small></div>
        <div class="card"><span>Sisa
                Pokok</span><strong>{{ $rp($loan->outstanding) }}</strong><small>{{ $loan->status === 'Lunas' ? 'lunas' : ($loan->disbursed_at ? 'setelah angsuran tercatat' : 'belum dicairkan') }}</small>
        </div>
        <div class="card"><span>Jatuh Tempo
                Berikutnya</span><strong>{{ $loan->next_due_at?->format('d M Y') ?? '-' }}</strong><small
                class="{{ $loan->daysOverdue() > 0 ? 'negative' : '' }}">{{ $loan->daysOverdue() > 0 ? $loan->daysOverdue() . ' hari lewat tempo' : 'tepat waktu' }}</small>
        </div>
    </div>

    <div class="detail-grid">
        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Riwayat Pencairan dan Angsuran</h2>
                    <p>Tercatat lewat Kasir dan Pinjaman</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table compact">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th class="right">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $t)
                            <tr>
                                <td>{{ $t->trx_date->format('d M Y') }}<small class="block">{{ $t->trx_no }}</small>
                                </td>
                                <td>{{ $t->description }}</td>
                                <td class="right amount {{ $t->direction === 'in' ? 'in' : 'out' }}">
                                    {{ $t->direction === 'in' ? '+' : '-' }} {{ $rp($t->amount) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="empty">Belum ada pencairan atau angsuran tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <div>
                    <h2>{{ $remaining ? 'Jadwal Sisa Angsuran' : 'Jadwal Angsuran' }}</h2>
                    <p>{{ $remaining ? 'Dari sisa pokok sekarang, mulai jatuh tempo berikutnya' : ($loan->disbursed_at ? 'Dihitung dari tanggal pencairan' : 'Perkiraan, dihitung dari hari ini') }}
                    </p>
                </div>
            </div>
            <div class="table-wrap" style="max-height:420px;overflow:auto">
                <table class="data-table compact">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Jatuh tempo</th>
                            <th class="right">Pokok</th>
                            <th class="right">Bunga</th>
                            <th class="right">Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schedule as $row)
                            <tr>
                                <td>{{ $row['no'] }}</td>
                                <td>{{ $row['due']->format('d M Y') }}</td>
                                <td class="right">{{ $rp($row['principal']) }}</td>
                                <td class="right">{{ $rp($row['interest']) }}</td>
                                <td class="right">{{ $rp($row['balance']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
