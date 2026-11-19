@php $a = $getRecord(); @endphp
<div class="flex items-center gap-3 px-3 py-2">
    <x-gw.provider :provider="$a->provider" />
    <div class="min-w-0">
        <div class="gw-mono truncate text-sm font-medium text-gray-950 dark:text-white">{{ $a->label }}</div>
        <div class="truncate text-xs text-gray-600 dark:text-gray-400">{{ $a->provider->name }}@if ($a->plan) · {{ $a->plan }}@endif</div>
    </div>
</div>
