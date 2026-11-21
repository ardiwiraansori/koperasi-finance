@extends('layouts.app')
@section('title', 'Simpanan')
@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">SIMPANAN</p>
            <h1>Rekening Simpanan</h1>
            <p>Pantau saldo dan rekening simpanan seluruh anggota.</p>
        </div>
        <div class="heading-actions">
            @if (auth()->user()->allows('opening'))
                <a class="btn btn-light" href="{{ route('savings.opening') }}">Saldo Awal</a>
            @endif
            <a class="btn btn-light" href="{{ route('savings.statement') }}">
                Rekening Koran</a>
            @if (auth()->user()->allows('savings.tx'))
                <a class="btn btn-light" href="{{ route('savings.withdraw') }}">− Penarikan</a><a class="btn btn-primary"
                    href="{{ route('savings.deposit') }}">＋ Setoran</a>
            @endif
        </div>
    </div>
    <div class="kpi-grid three">
        @foreach (['Pokok' => 'blue', 'Wajib' => 'green', 'Sukarela' => 'purple'] as $type => $color)
            <div class="kpi-card">
                <div class="kpi-icon {{ $color }}">◫</div>
                <div><span>Simpanan {{ $type }}</span><strong>Rp
                        {{ number_format(($summary[$type] ?? 0) / 1000000, 1, ',', '.') }} Jt</strong><small
                        class="muted">{{ $savings->where('type', $type)->count() }} rekening</small></div>
            </div>
        @endforeach
    </div>
    <div class="card">
        <div class="toolbar">
            <div class="search-box">⌕ <input aria-label="Cari rekening atau anggota" data-table-search="#savingsTable"
                    placeholder="Cari rekening atau anggota..."></div><select aria-label="Filter jenis simpanan"
                data-filter-table="#savingsTable" data-filter-col="2">
                <option value="">Semua Jenis</option>
                <option>Pokok</option>
                <option>Wajib</option>
                <option>Sukarela</option>
            </select><select aria-label="Filter cabang" data-filter-table="#savingsTable" data-filter-col="3">
                <option value="">Semua Cabang</option>
                @foreach (\App\Http\Controllers\MemberController::BRANCHES as $b)
                    <option>{{ $b }}</option>
                @endforeach
            </select>
        </div>
        <div class="table-wrap">
            <table class="data-table" id="savingsTable">
                <thead>
                    <tr>
                        <th>Rekening</th>
                        <th>Anggota</th>
                        <th>Jenis</th>
                        <th>Cabang</th>
                        <th class="right">Saldo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($savings as $saving)
                        <tr>
                            <td><a class="table-link" title="Lihat rekening koran"
                                    href="{{ route('savings.statement', ['rekening' => $saving->id]) }}"><strong>{{ $saving->account_no }}</strong></a>
                            </td>
                            <td><a class="table-link"
                                    href="{{ route('members.show', $saving->member) }}">{{ $saving->member->name }}</a><small
                                    class="block">{{ $saving->member->member_no }}</small></td>
                            <td>Simpanan {{ $saving->type }}</td>
                            <td>{{ $saving->member->branch }}</td>
                            <td class="right"><strong>Rp {{ number_format($saving->balance, 0, ',', '.') }}</strong></td>
                            <td><span
                                    class="badge {{ $saving->member->status === 'Aktif' ? 'success' : 'neutral' }}">{{ $saving->member->status }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
