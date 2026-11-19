@php $a = $getRecord(); $peak = $a->peak_pct; @endphp
<div class="w-44 px-3 py-2">
    @if ($peak === null)
        <p class="text-xs text-gray-600 dark:text-gray-400">Chưa đo được</p>
    @else
        <x-gw.bar :pct="$peak" :label="'Mức dùng của ' . $a->label" />
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
            <span class="gw-mono">5h {{ $a->pct_5h === null ? '—' : round($a->pct_5h) . '%' }} · tuần {{ $a->pct_week === null ? '—' : round($a->pct_week) . '%' }}</span>
            <span class="block">{{ $a->quota_source_label }}</span>
        </p>
    @endif
</div>
