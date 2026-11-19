<?php

namespace App\Filament\Widgets;

use App\Models\ClientApp;
use App\Models\UsageEvent;
use Filament\Widgets\Widget;

class AppUsage extends Widget
{
    protected static string $view = 'filament.widgets.app-usage';

    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $today = UsageEvent::where('ts', '>=', now()->startOfDay())->where('status', 'ok')
            ->selectRaw('app_id, SUM(weighted_units) u')->groupBy('app_id')->pluck('u', 'app_id');

        $rows = ClientApp::with(['limits' => fn ($q) => $q->whereNull('capability_code')])->orderBy('id')->get()->map(function (ClientApp $app) use ($today) {
            $used = (int) ($today[$app->id] ?? 0);
            $limit = $app->limits->first();
            $daily = $limit?->units_per_day;

            return ['app' => $app, 'used' => $used, 'daily' => $daily, 'mode' => $limit?->mode, 'pct' => $daily ? $used / $daily * 100 : null];
        })->sortByDesc(fn ($r) => $r['pct'] ?? -1)->values();

        return ['rows' => $rows];
    }
}
