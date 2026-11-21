{{-- Rincian top-up: pinjaman lama yang dilunasi dari pencairan pinjaman ini. Parameter: $lines (Loan::payoffLines), $principal. --}}
@php($rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
<div class="form-note" style="margin-top:8px"><strong>Top-up: pelunasan pinjaman lama</strong>
@foreach($lines as $l)
<div>{{ $l['loan']->loan_no }} · {{ $l['loan']->display_status }}@if($l['days']) ({{ $l['days'] }} hari terlambat)@endif · sisa pokok {{ $rp($l['outstanding']) }} + bunga {{ $rp($l['interest']) }}@if($l['months'] > 1) ({{ $l['months'] }} bulan)@endif ={{ $rp($l['total']) }}</div>
@endforeach
@php($total = array_sum(array_column($lines, 'total')))
<div>Total pelunasan {{ $rp($total) }}. Uang yang diterima anggota: <strong>{{ $rp($principal - $total) }}</strong> dari plafon {{ $rp($principal) }}.@if($principal <= $total) <b class="negative">Plafon belum cukup untuk melunasi pinjaman lama.</b>@endif</div>
</div>
