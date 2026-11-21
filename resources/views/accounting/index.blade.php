@extends('layouts.app')
@section('title', 'Akuntansi')
@section('content')
    @php($rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.'))
    <div class="page-heading">
        <div>
            <p class="eyebrow">AKUNTANSI</p>
            <h1>Jurnal Umum</h1>
            <p>Telusuri jurnal otomatis maupun jurnal manual beserta sumber transaksinya.</p>
        </div>
        <div class="heading-actions no-print"><a class="btn btn-light"
                href="{{ route('accounting.export', request()->query()) }}">↓ Export CSV</a>
            @if (auth()->user()->allows('journal.manual'))
                <a class="btn btn-primary" href="{{ route('accounting.manual') }}">＋ Jurnal Manual</a>
            @endif
        </div>
    </div>
    @include('accounting._tabs')

    <form method="get" action="{{ route('accounting.index') }}" class="card no-print" style="margin-bottom:14px">
        <div class="toolbar">
            <div class="search-box">⌕ <input name="q" aria-label="Cari jurnal" value="{{ request('q') }}"
                    placeholder="Cari nomor jurnal, keterangan, atau referensi..."></div>
            <input type="date" name="dari" value="{{ request('dari') }}" title="Dari tanggal"
                style="height:36px;border:1px solid var(--line);border-radius:9px;padding:0 10px;font-size:13px">
            <input type="date" name="sampai" value="{{ request('sampai') }}" title="Sampai tanggal"
                style="height:36px;border:1px solid var(--line);border-radius:9px;padding:0 10px;font-size:13px">
            <select name="sumber" aria-label="Sumber jurnal">
                <option value="">Semua Sumber</option>
                <option value="AUTO JOURNAL" @selected(request('sumber') === 'AUTO JOURNAL')>Otomatis</option>
                <option value="MANUAL" @selected(request('sumber') === 'MANUAL')>Manual</option>
            </select>
            <button class="btn btn-primary" type="submit">Terapkan</button>
            @if (request()->hasAny(['q', 'dari', 'sampai', 'sumber']))
                <a class="btn btn-light" href="{{ route('accounting.index') }}">Reset</a>
            @endif
        </div>
        @if ($errors->any())
            <small class="negative" style="padding:0 16px 12px;display:block">{{ $errors->first() }}</small>
        @endif
    </form>
    <div class="summary-strip">
        <div><span>Total Jurnal</span><strong>{{ number_format($count) }}</strong></div>
        <div><span>Posted</span><strong>{{ number_format($posted) }}</strong></div>
        <div><span>Debit</span><strong>{{ $rp($debit) }}</strong></div>
        <div><span>Kredit</span><strong>{{ $rp($credit) }}</strong></div>
    </div>

    <div class="journal-list">
        @forelse($journals as $journal)
            @php($jd = $journal->lines->sum('debit'))
            <details class="card journal" @if ($loop->first) open @endif>
                <summary>
                    <div><span class="journal-no">{{ $journal->journal_no }}</span>
                        <div><strong>{{ $journal->description }}</strong><small>{{ $journal->journal_date->format('d M Y') }}{{ $journal->reference ? ' · Ref ' . $journal->reference : '' }}
                                · {{ $journal->source === 'MANUAL' ? 'Manual' : 'Otomatis' }}</small></div>
                    </div>
                    <div class="journal-total"><span
                            class="badge success">{{ $journal->status }}</span><strong>{{ $rp($jd) }}</strong><i>⌄</i>
                    </div>
                </summary>
                <div class="journal-body">
                    <div class="journal-meta"><span>Sumber <strong>{{ $journal->source }}</strong></span><span>Reference
                            <strong>{{ $journal->reference ?? '-' }}</strong></span><span>Status
                            <strong>{{ $journal->status }}</strong></span></div>
                    <table class="journal-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Akun</th>
                                <th class="right">Debit</th>
                                <th class="right">Kredit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($journal->lines as $line)
                                <tr>
                                    <td>{{ $line->account_code }}</td>
                                    <td>{{ $line->account_name }}</td>
                                    <td class="right">{{ $line->debit > 0 ? $rp($line->debit) : '-' }}</td>
                                    <td class="right">{{ $line->credit > 0 ? $rp($line->credit) : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">TOTAL</td>
                                <td class="right">{{ $rp($jd) }}</td>
                                <td class="right">{{ $rp($journal->lines->sum('credit')) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    <div class="balance-check">
                        {{ round($jd, 2) === round($journal->lines->sum('credit'), 2) ? '✓ Jurnal seimbang' : '⚠ Jurnal TIDAK seimbang' }}
                    </div>
                </div>
            </details>
        @empty
            <div class="card">
                <div class="empty">Tidak ada jurnal yang cocok dengan filter.</div>
            </div>
        @endforelse
    </div>
    @if ($journals->hasPages())
        <div class="table-footer no-print" style="margin-top:10px"><span>Halaman {{ $journals->currentPage() }}</span>
            <div>
                @if ($journals->previousPageUrl())
                    <a class="btn btn-primary small" href="{{ $journals->previousPageUrl() }}">‹ Sebelumnya</a>
                    @endif @if ($journals->nextPageUrl())
                        <a class="btn btn-primary small" href="{{ $journals->nextPageUrl() }}">Berikutnya ›</a>
                    @endif
            </div>
        </div>
    @endif
@endsection
