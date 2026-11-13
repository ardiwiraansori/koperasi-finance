<div class="approval-tabs no-print">
@php($tabs = ['accounting.index' => ['Jurnal Umum', 'accounting'], 'accounting.ledger' => ['Buku Besar', 'accounting'], 'accounting.trial' => ['Neraca Saldo', 'accounting'], 'accounting.types' => ['Jenis Transaksi', 'types.edit'], 'accounting.manual' => ['Jurnal Manual', 'journal.manual']])
@foreach($tabs as $route => [$label, $ability])
@if(auth()->user()->allows($ability))
<a class="btn btn-primary {{ request()->routeIs($route.'*') ? 'tab-active' : '' }}" href="{{ route($route) }}" style="{{ request()->routeIs($route.'*') ? '' : 'opacity:.62' }}">{{ $label }}</a>
@endif
@endforeach
</div>
