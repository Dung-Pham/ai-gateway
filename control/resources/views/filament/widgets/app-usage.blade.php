<x-filament-widgets::widget>
    <x-filament::section heading="Ứng dụng: mức dùng hôm nay" description="Đơn vị quy đổi đã dùng so với hạn mức ngày do bạn đặt cho từng ứng dụng.">
        <x-slot name="headerEnd">
            <a href="{{ \App\Filament\Resources\ClientAppResource::getUrl() }}" class="inline-flex min-h-[44px] items-center text-sm font-medium text-primary-600 hover:underline dark:text-primary-400 lg:min-h-0">Quản lý ứng dụng</a>
        </x-slot>
        <div class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($rows as $r)
                @php $app = $r['app']; @endphp
                <div class="grid items-center gap-x-6 gap-y-2 py-3 first:pt-0 last:pb-0 md:grid-cols-[minmax(0,16rem)_1fr_auto]">
                    <div class="min-w-0">
                        <a href="{{ \App\Filament\Resources\ClientAppResource::getUrl('view', ['record' => $app]) }}" class="block truncate py-3 text-sm font-medium text-gray-950 hover:underline dark:text-white lg:py-0">{{ $app->name }}</a>
                        <x-filament::badge :color="$app->status->getColor()" size="sm" class="mt-1 w-fit">{{ $app->status->getLabel() }}</x-filament::badge>
                    </div>
                    <div>
                        @if ($r['pct'] !== null)
                            <x-gw.bar :pct="$r['pct']" :label="'Mức dùng hôm nay của ' . $app->name" />
                        @else
                            <p class="text-xs text-gray-600 dark:text-gray-400">Chưa đặt hạn mức ngày</p>
                        @endif
                    </div>
                    <div class="gw-mono text-right text-sm text-gray-950 dark:text-white">
                        {{ number_format($r['used'], 0, ',', '.') }}
                        @if ($r['daily'])
                            <span class="text-gray-600 dark:text-gray-400">/ {{ number_format($r['daily'], 0, ',', '.') }}</span>
                            <span class="ml-1 font-semibold">{{ round($r['pct']) }}%</span>
                            <span class="ml-1 font-sans text-xs text-gray-600 dark:text-gray-400">({{ $r['mode'] === 'soft' ? 'mềm' : 'cứng' }})</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
