@props(['value' => null, 'size' => 72])
@php
    $r = 30;
    $c = 2 * M_PI * $r;
    $v = $value === null ? null : max(0, min(100, (float) $value));
    $tone = $v === null ? '' : ($v >= 90 ? 'gw-gauge-bad' : ($v >= 70 ? 'gw-gauge-warn' : 'gw-gauge-ok'));
@endphp
<div class="relative inline-flex shrink-0 items-center justify-center" style="width:{{ $size }}px;height:{{ $size }}px"
     role="img" aria-label="{{ $v === null ? 'Chưa đo được mức dùng' : 'Mức dùng ' . round($v) . ' phần trăm' }}">
    <svg viewBox="0 0 72 72" width="{{ $size }}" height="{{ $size }}" class="-rotate-90" aria-hidden="true">
        <circle cx="36" cy="36" r="{{ $r }}" fill="none" stroke-width="7" class="gw-gauge-track" @if ($v === null) stroke-dasharray="3 6" @endif />
        @if ($v !== null)
            <circle cx="36" cy="36" r="{{ $r }}" fill="none" stroke-width="7" stroke-linecap="round" class="{{ $tone }}"
                    stroke-dasharray="{{ round($c, 2) }}" stroke-dashoffset="{{ round($c * (1 - $v / 100), 2) }}" />
        @endif
    </svg>
    <span class="gw-mono absolute text-sm font-semibold text-gray-950 dark:text-white">{{ $v === null ? '—' : round($v) . '%' }}</span>
</div>
