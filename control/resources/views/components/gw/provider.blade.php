@props(['provider', 'size' => 'h-8 w-8'])
<span class="inline-flex {{ $size }} shrink-0 items-center justify-center rounded-lg text-sm font-bold text-white"
      style="background: {{ $provider->color }}" aria-hidden="true">{{ mb_substr($provider->name, 0, 1) }}</span>
