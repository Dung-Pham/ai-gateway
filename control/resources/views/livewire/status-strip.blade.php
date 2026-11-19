<div wire:poll.15s class="gw-strip" role="status" aria-label="Trạng thái toàn hệ thống">
    <div class="gw-strip-inner">
        <span class="gw-chip"><span class="gw-dot" style="background:#4fd08a"></span><strong>{{ $active }}</strong> tài khoản sẵn sàng</span>
        <span class="gw-chip" style="{{ $cooldown ? 'color:#f0b35a' : 'color:var(--gw-ink-soft)' }}"><x-filament::icon icon="heroicon-m-clock" class="h-4 w-4" /><strong>{{ $cooldown }}</strong> đang nghỉ</span>
        <span class="gw-chip" style="{{ $expired ? 'color:#ff9d93' : 'color:var(--gw-ink-soft)' }}"><x-filament::icon icon="heroicon-m-x-circle" class="h-4 w-4" /><strong>{{ $expired }}</strong> hết phiên</span>
        @if ($paused)
            <span class="gw-chip" style="color:var(--gw-ink-soft)"><x-filament::icon icon="heroicon-m-pause-circle" class="h-4 w-4" /><strong>{{ $paused }}</strong> tạm dừng</span>
        @endif
        <span class="gw-chip" style="color:var(--gw-ink-soft)"><strong style="color:#fff">{{ $apps }}</strong> ứng dụng hoạt động</span>
        <a href="{{ \App\Filament\Resources\AlertResource::getUrl() }}" class="gw-chip" style="{{ $openAlerts ? 'color:#f0b35a' : 'color:var(--gw-ink-soft)' }}">
            <x-filament::icon icon="heroicon-m-bell-alert" class="h-4 w-4" /><strong>{{ $openAlerts }}</strong> cảnh báo đang mở
        </a>
        @if (! $demo)
            @php $ok = ($sync['ok'] ?? null) === true && \Carbon\Carbon::parse($sync['at'])->gt(now()->subMinutes(3)); @endphp
            <span class="gw-chip" style="color:{{ $ok ? 'var(--gw-ink-soft)' : '#ff9d93' }}" title="{{ $sync['message'] ?? '' }}">
                <span class="gw-dot" style="background:{{ $ok ? '#4fd08a' : '#ff9d93' }}"></span>{{ $ok ? 'Gateway kết nối' : ($sync === null ? 'Gateway chưa đồng bộ' : 'Gateway mất kết nối') }}
            </span>
        @endif
        <span class="gw-chip gw-mono" style="margin-left:auto;color:var(--gw-ink-soft)">
            @if ($demo)
                <span style="background:#f0b35a;color:#0b1b33;border-radius:4px;padding:0 .4rem;font-weight:600;font-family:'Be Vietnam Pro',sans-serif">DỮ LIỆU MẪU</span>
            @endif
            cập nhật {{ now()->setTimezone(config('gateway.timezone'))->format('H:i:s') }}
        </span>
    </div>
</div>
