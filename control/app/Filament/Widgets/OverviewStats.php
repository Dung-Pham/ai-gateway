<?php

namespace App\Filament\Widgets;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\UsageEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStats extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = ['lg' => 3];

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $startToday = now()->startOfDay();
        $reqToday = UsageEvent::where('ts', '>=', $startToday)->count();
        // So với hôm qua cùng khoảng giờ để so sánh công bằng.
        $reqYesterday = UsageEvent::whereBetween('ts', [$startToday->copy()->subDay(), now()->subDay()])->count();
        $delta = $reqYesterday > 0 ? round(($reqToday - $reqYesterday) / $reqYesterday * 100) : null;

        $units = (int) UsageEvent::where('ts', '>=', $startToday)->where('status', 'ok')->sum('weighted_units');

        $last24 = UsageEvent::where('ts', '>=', now()->subDay());
        $total24 = (clone $last24)->count();
        $errors24 = (clone $last24)->whereIn('status', ['error', 'rate_limited'])->count();
        $errRate = $total24 ? $errors24 / $total24 * 100 : 0;

        $accounts = Account::all();
        $ready = $accounts->where('status', AccountStatus::Active)->count();

        $hourly = UsageEvent::where('ts', '>=', now()->subHours(12))->pluck('ts')
            ->countBy(fn ($t) => $t->copy()->startOfHour()->format('YmdH'));
        $spark = collect(range(11, 0))->map(fn ($h) => $hourly[now()->subHours($h)->format('YmdH')] ?? 0)->all();

        return [
            Stat::make('Yêu cầu hôm nay', number_format($reqToday, 0, ',', '.'))
                ->description($delta === null ? 'Chưa có dữ liệu hôm qua' : ($delta >= 0 ? "+{$delta}%" : "{$delta}%") . ' so với hôm qua cùng giờ')
                ->descriptionIcon($delta !== null && $delta < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->chart($spark)->color('primary'),
            Stat::make('Đơn vị quy đổi hôm nay', number_format($units, 0, ',', '.'))
                ->description('Đếm nội bộ, không phải hạn mức của hãng')->descriptionIcon('heroicon-m-calculator')->color('info'),
            Stat::make('Tỉ lệ lỗi 24 giờ', number_format($errRate, 1, ',', '.') . '%')
                ->description(number_format($errors24, 0, ',', '.') . ' / ' . number_format($total24, 0, ',', '.') . ' yêu cầu lỗi hoặc chạm giới hạn')
                ->descriptionIcon($errRate >= 5 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($errRate >= 10 ? 'danger' : ($errRate >= 5 ? 'warning' : 'success')),
            Stat::make('Tài khoản sẵn sàng', "{$ready} / {$accounts->count()}")
                ->description($accounts->where('status', AccountStatus::Cooldown)->count() . ' đang nghỉ · ' . $accounts->where('status', AccountStatus::AuthExpired)->count() . ' hết phiên')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color($ready === $accounts->count() ? 'success' : 'warning'),
        ];
    }
}
