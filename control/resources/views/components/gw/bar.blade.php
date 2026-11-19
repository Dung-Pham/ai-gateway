@props(['pct' => 0, 'label' => 'Mức dùng'])
@php
    $p = max(0, min(100, (float) $pct));
    $tone = $p >= 90 ? 'bad' : ($p >= 70 ? 'warn' : '');
@endphp
<div class="gw-bar" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($p) }}">
    <span class="{{ $tone }}" style="width: {{ $p }}%"></span>
</div>
