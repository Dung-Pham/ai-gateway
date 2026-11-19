<x-filament-widgets::widget>
    <x-filament::section heading="Tài khoản AI" description="Mỗi tài khoản có nhãn và biểu tượng hãng cố định ở mọi màn hình.">
        <x-slot name="headerEnd">
            <a href="{{ \App\Filament\Resources\AccountResource::getUrl() }}" class="inline-flex min-h-[44px] items-center text-sm font-medium text-primary-600 hover:underline dark:text-primary-400 lg:min-h-0">Quản lý tài khoản</a>
        </x-slot>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($accounts as $a)
                @php $attn = in_array($a->status->value, ['auth_expired', 'cooldown']); @endphp
                <a href="{{ \App\Filament\Resources\AccountResource::getUrl('view', ['record' => $a]) }}"
                   @class([
                       'flex items-center gap-4 rounded-xl p-4 ring-1 transition hover:bg-gray-50 dark:hover:bg-white/5',
                       'ring-gray-950/10 dark:ring-white/10' => ! $attn,
                       'ring-2 ring-warning-500/60' => $a->status->value === 'cooldown',
                       'ring-2 ring-danger-500/60' => $a->status->value === 'auth_expired',
                   ])>
                    <x-gw.gauge :value="$a->peak_pct" />
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-gw.provider :provider="$a->provider" size="h-6 w-6" />
                            <span class="gw-mono truncate text-sm font-medium text-gray-950 dark:text-white">{{ $a->label }}</span>
                        </div>
                        <x-filament::badge :color="$a->status->getColor()" :icon="$a->status->getIcon()" size="sm">{{ $a->status->getLabel() }}</x-filament::badge>
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            Slot <span class="gw-mono">{{ $a->current_concurrency }}/{{ $a->max_concurrency }}</span>
                            @if ($a->status->value === 'cooldown' && $a->cooldown_until)
                                · nghỉ đến <span class="gw-mono">{{ $a->cooldown_until->setTimezone(config('gateway.timezone'))->format('H:i') }}</span>
                            @else
                                · {{ $a->quota_source_label }}
                            @endif
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
