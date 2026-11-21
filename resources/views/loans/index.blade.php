@extends('layouts.app')
@section('title', 'Pinjaman')
@section('content')
    @php($badges = ['Tunggakan' => 'danger', 'Menunggu Approval' => 'warning', 'Disetujui' => 'warning', 'Lunas' => 'neutral', 'Ditolak' => 'neutral', 'Aktif' => 'success'])
    <div class="page-heading">
        <div>
            <p class="eyebrow">PINJAMAN</p>
            <h1>Portofolio Pinjaman</h1>
            <p>Kelola pengajuan, approval, pencairan, angsuran, dan kualitas pinjaman.</p>
        </div>
        <div class="heading-actions">
            @if (auth()->user()->allows('opening'))
                <a class="btn btn-light" href="{{ route('loans.opening') }}">Saldo Awal</a>
                @endif @if (auth()->user()->allows('loan.apply'))
                    <a class="btn btn-primary" href="{{ route('loans.create') }}">＋ Pengajuan Pinjaman</a>
                @endif
        </div>
    </div>
    <div class="pipeline">
        @foreach ($pipeline as $label => $total)
            <div><span
                    class="pipe-icon {{ $label === 'Aktif' ? 'done' : '' }}">{{ $loop->iteration }}</span><small>{{ $label === 'Disetujui' ? 'Menunggu Pencairan' : $label }}</small><strong>{{ $total }}</strong>
            </div>
            @if (!$loop->last)
                <i></i>
            @endif
        @endforeach
    </div>
    <div class="card">
        <div class="toolbar">
            <div class="search-box">⌕ <input aria-label="Cari pinjaman" data-table-search="#loanTable"
                    placeholder="Cari nomor pinjaman atau anggota..."></div>
            <select aria-label="Filter status pinjaman" data-filter-table="#loanTable" data-filter-col="6">
                <option value="">Semua Status</option>
                @foreach (['Menunggu Approval', 'Disetujui', 'Aktif', 'Tunggakan', 'Lunas', 'Ditolak'] as $s)
                    <option>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="table-wrap">
            <table class="data-table" id="loanTable">
                <thead>
                    <tr>
                        <th>Pinjaman</th>
                        <th>Anggota</th>
                        <th>Produk</th>
                        <th>Tenor</th>
                        <th class="right">Plafon</th>
                        <th class="right">Outstanding</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loans as $loan)
                        <tr>
                            <td><a class="table-link"
                                    href="{{ route('loans.show', $loan) }}"><strong>{{ $loan->loan_no }}</strong></a><small
                                    class="block">{{ $loan->applied_at->format('d M Y') }}</small></td>
                            <td><a class="table-link"
                                    href="{{ route('members.show', $loan->member) }}">{{ $loan->member->name }}</a><small
                                    class="block">{{ $loan->member->member_no }}</small></td>
                            <td>{{ $loan->product }}</td>
                            <td>{{ $loan->term_months }} bln <small
                                    class="block">{{ number_format($loan->interest_rate, 1, ',', '.') }}%</small></td>
                            <td class="right">Rp {{ number_format($loan->principal, 0, ',', '.') }}</td>
                            <td class="right"><strong>Rp {{ number_format($loan->outstanding, 0, ',', '.') }}</strong></td>
                            <td><span
                                    class="badge {{ $badges[$loan->display_status] ?? 'neutral' }}">{{ $loan->display_status }}</span>
                                @if ($loan->daysOverdue() > 0)
                                    <small class="block negative">{{ $loan->daysOverdue() }} hari lewat tempo</small>
                                @endif
                            </td>
                            <td class="right"><a class="row-action" href="{{ route('loans.show', $loan) }}"
                                    title="Detail">›</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty">Belum ada pinjaman.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
