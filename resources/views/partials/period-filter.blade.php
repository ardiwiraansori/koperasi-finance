{{-- Filter periode laporan. Parameter: $routeName, $from, $to, dan $compare (null = tanpa kotak "Bandingkan"). --}}
@php($presets = ['Bulan ini' => [now()->startOfMonth(), now()], 'Tahun ini' => [now()->startOfYear(), now()], 'Tahun lalu' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()]])
<form method="get" action="{{ route($routeName) }}" class="card no-print" style="margin-bottom:14px">
    <div class="toolbar">
        <label style="font-size:13px;color:#64748b">Dari <input type="date" name="dari" value="{{ $from }}"
                style="height:36px;border:1px solid var(--line);border-radius:9px;padding:0 10px;font-size:13px;margin-left:6px"></label>
        <label style="font-size:13px;color:#64748b">Sampai <input type="date" name="sampai"
                value="{{ $to }}"
                style="height:36px;border:1px solid var(--line);border-radius:9px;padding:0 10px;font-size:13px;margin-left:6px"></label>
        @if ($compare !== null)
            <label style="font-size:13px;color:#64748b;display:flex;align-items:center;gap:6px"><input type="checkbox"
                    name="bandingkan" value="1" @checked($compare) style="width:16px;height:16px">
                Bandingkan dengan periode sebelumnya</label>
        @endif
        <button class="btn btn-primary" type="submit">Tampilkan</button>
    </div>
    <div class="toolbar" style="padding-top:0">
        @foreach ($presets as $label => [$a, $b])
            <a class="btn btn-primary small"
                href="{{ route($routeName, ['dari' => $a->toDateString(), 'sampai' => $b->toDateString()] + ($compare ? ['bandingkan' => 1] : [])) }}">{{ $label }}</a>
        @endforeach
    </div>
    @if ($errors->any())
        <small class="negative" style="padding:0 16px 12px;display:block">{{ $errors->first() }}</small>
    @endif
</form>
