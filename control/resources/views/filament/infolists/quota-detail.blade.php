@php $a = $getRecord(); @endphp
<div class="flex flex-wrap items-center gap-x-10 gap-y-4">
    @foreach ([['Phiên 5 giờ', $a->pct_5h], ['Tuần', $a->pct_week]] as [$name, $val])
        <div class="flex items-center gap-4">
            <x-gw.gauge :value="$val" :size="88" />
            <div>
                <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $name }}</div>
                <div class="text-xs text-gray-600 dark:text-gray-400">{{ $val === null ? 'Chưa đo được' : 'Đã dùng ' . round($val) . '%' }}</div>
            </div>
        </div>
    @endforeach
    <div class="text-sm text-gray-600 dark:text-gray-400">
        <div>Nguồn: <strong class="text-gray-950 dark:text-white">{{ $a->quota_source_label }}</strong></div>
        <div>Cập nhật: {{ $a->quota_updated_at ? $a->quota_updated_at->diffForHumans() : 'chưa có' }}</div>
        @if ($a->quota_source === 'estimate')
            <div class="mt-1 text-xs">Đây là ước lượng, không phải số liệu từ hãng.</div>
        @elseif ($a->quota_source === null)
            <div class="mt-1 text-xs">Chưa có cách đọc mức dùng bằng máy cho tài khoản này (xem kết quả thăm dò CLI).</div>
        @endif
    </div>
</div>
