@extends('layouts.app')
@section('title', 'Rekening Koran')
@section('content')
    @php($rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.'))
    <a class="back-link no-print" href="{{ route('savings.index') }}">
        ← Kembali ke Simpanan</a>
    <div class="page-heading">
        <div>
            <p class="eyebrow">SIMPANAN</p>
            <h1>Rekening Koran</h1>
            <p>Mutasi dan saldo berjalan satu rekening simpanan.</p>
        </div>
    </div>

    <section class="card transaction-form no-print" style="max-width:680px;margin-bottom:16px">
        <form method="get" action="{{ route('savings.statement') }}">
            <label>Rekening <span>ketik nomor rekening, nama anggota, atau jenis simpanan</span>
                <input list="dl-rekening" data-combobox="rekening" autocomplete="off"
                    placeholder="Contoh: Budi, SAV-0128, Wajib" required>
                <datalist id="dl-rekening">
                    @foreach ($accounts as $a)
                        <option value="{{ $a->account_no }} — {{ $a->member->name }} · Simpanan {{ $a->type }}"
                            data-id="{{ $a->id }}"></option>
                    @endforeach
                </datalist>
                <input type="hidden" name="rekening" value="{{ $saving?->id }}">
                @error('rekening')
                    <small class="negative">{{ $message }}</small>
                @enderror
            </label>
            <div class="form-row">
                <label>Dari tanggal <span>opsional</span><input type="date" name="dari" value="{{ $dari }}">
                    @error('dari')
                        <small class="negative">{{ $message }}</small>
                    @enderror
                </label>
                <label>Sampai tanggal <span>opsional</span><input type="date" name="sampai"
                        value="{{ $sampai }}">
                    @error('sampai')
                        <small class="negative">{{ $message }}</small>
                    @enderror
                </label>
            </div>
            <button class="btn btn-primary full" type="submit">Tampilkan</button>
        </form>
    </section>

    @if ($statement)
        <section class="card">
            <div class="card-head">
                <div>
                    <h2>{{ $saving->account_no }} · {{ $saving->member->name }}</h2>
                    <p>Simpanan {{ $saving->type }} · {{ $saving->member->member_no }} · {{ $saving->member->branch }} ·
                        Periode {{ $dari ? \Carbon\Carbon::parse($dari)->format('d M Y') : 'awal' }} s/d
                        {{ $sampai ? \Carbon\Carbon::parse($sampai)->format('d M Y') : 'sekarang' }}</p>
                </div><button class="btn btn-primary small no-print" type="button" onclick="window.print()">Cetak</button>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No. Transaksi</th>
                            <th>Keterangan</th>
                            <th class="right">Setoran</th>
                            <th class="right">Penarikan</th>
                            <th class="right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5"><strong>Saldo awal</strong></td>
                            <td class="right"><strong>{{ $rp($statement['opening']) }}</strong></td>
                        </tr>
                        @forelse($statement['rows'] as $line)
                            @php($t = $line['trx'])
                            <tr>
                                <td>{{ $t->trx_date->format('d M Y H:i') }}</td>
                                <td>{{ $t->trx_no }}</td>
                                <td>{{ $t->description }}</td>
                                <td class="right">{{ $t->direction === 'in' ? $rp($t->amount) : '' }}</td>
                                <td class="right">{{ $t->direction === 'out' ? $rp($t->amount) : '' }}</td>
                                <td class="right">{{ $rp($line['saldo']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty">Belum ada mutasi tercatat pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3"><strong>Total / Saldo akhir</strong></td>
                            <td class="right"><strong>{{ $rp($statement['in']) }}</strong></td>
                            <td class="right"><strong>{{ $rp($statement['out']) }}</strong></td>
                            <td class="right"><strong>{{ $rp($statement['closing']) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="table-footer"><span>Dicetak {{ now()->format('d M Y H:i') }} oleh {{ auth()->user()->name }}.
                    Saldo awal dihitung dari saldo rekening dikurangi mutasi yang tercatat.</span></div>
        </section>
    @endif
@endsection
