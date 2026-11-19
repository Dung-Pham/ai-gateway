@php
    $app = $getRecord();
    $limit = $app->limits->whereNull('capability_code')->first();
    $daily = $limit?->units_per_day;
    $used = $app->unitsToday();
    $pct = $daily ? $used / $daily * 100 : null;
@endphp
<div class="w-56 px-3 py-2">
    @if ($pct === null)
        <p class="text-xs text-gray-600 dark:text-gray-400">Chưa đặt hạn mức ngày</p>
    @else
        <x-gw.bar :pct="$pct" :label="'Mức dùng hôm nay của ' . $app->name" />
        <p class="gw-mono mt-1 text-xs text-gray-600 dark:text-gray-400">{{ number_format($used, 0, ',', '.') }} / {{ number_format($daily, 0, ',', '.') }} · <span class="font-semibold text-gray-950 dark:text-white">{{ round($pct) }}%</span></p>
    @endif
</div>
