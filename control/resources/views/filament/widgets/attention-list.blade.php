<x-filament-widgets::widget>
    <x-filament::section heading="Cần chú ý" :description="$total ? $total . ' cảnh báo chưa xác nhận' : null">
        <x-slot name="headerEnd">
            <a href="{{ \App\Filament\Resources\AlertResource::getUrl() }}" class="inline-flex min-h-[44px] items-center text-sm font-medium text-primary-600 hover:underline dark:text-primary-400 lg:min-h-0">Xem tất cả</a>
        </x-slot>

        @forelse ($items as $item)
            @php $a = $item['alert']; @endphp
            <div class="flex gap-3 border-t border-gray-200 py-3 first:border-t-0 first:pt-0 dark:border-white/10">
                <x-filament::icon :icon="$a->severity->getIcon()" @class([
                    'mt-0.5 h-5 w-5 shrink-0',
                    'text-danger-600 dark:text-danger-400' => $a->severity->value === 'critical',
                    'text-warning-600 dark:text-warning-400' => $a->severity->value === 'warning',
                    'text-info-600 dark:text-info-400' => $a->severity->value === 'info',
                ]) />
                <div class="min-w-0 flex-1">
                    @if ($item['url'])
                        <a href="{{ $item['url'] }}" class="inline-block py-3 text-sm font-medium text-gray-950 hover:underline dark:text-white lg:py-0">{{ $a->title }}</a>
                    @else
                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $a->title }}</span>
                    @endif
                    <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">{{ $a->severity->getLabel() }} · {{ $a->opened_at->diffForHumans() }}</p>
                </div>
            </div>
        @empty
            <div class="flex items-center gap-3 py-2 text-sm text-gray-600 dark:text-gray-400">
                <x-filament::icon icon="heroicon-m-check-circle" class="h-5 w-5 text-success-600 dark:text-success-400" />
                Không có gì cần xử lý. Mọi cảnh báo đã được xác nhận.
            </div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
