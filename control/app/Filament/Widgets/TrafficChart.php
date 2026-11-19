<?php

namespace App\Filament\Widgets;

use App\Models\ClientApp;
use App\Models\UsageEvent;
use Filament\Widgets\ChartWidget;

class TrafficChart extends ChartWidget
{
    protected static ?string $heading = 'Lưu lượng 24 giờ qua';

    protected static ?string $description = 'Số yêu cầu mỗi giờ, tách theo ứng dụng.';

    protected static ?string $maxHeight = '280px';

    protected static ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = ['lg' => 2];

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $from = now()->subHours(23)->startOfHour();
        $events = UsageEvent::where('ts', '>=', $from)->get(['ts', 'app_id']);
        $apps = ClientApp::orderBy('id')->get();
        $palette = ['#1E5BC6', '#14B8D4', '#7C5CE0', '#94A3B8'];

        $labels = collect(range(0, 23))->map(fn ($i) => $from->copy()->addHours($i));
        $datasets = $apps->values()->map(function ($app, $i) use ($events, $labels, $palette) {
            $byHour = $events->where('app_id', $app->id)->countBy(fn ($e) => $e->ts->format('YmdH'));

            return [
                'label' => $app->name,
                'data' => $labels->map(fn ($h) => $byHour[$h->format('YmdH')] ?? 0)->all(),
                'borderColor' => $palette[$i % count($palette)],
                'backgroundColor' => $palette[$i % count($palette)],
                'borderWidth' => 2,
                'pointBackgroundColor' => $palette[$i % count($palette)],
                'pointBorderColor' => $palette[$i % count($palette)],
                'pointRadius' => 0,
                'pointHoverRadius' => 4,
                'tension' => 0.35,
                'fill' => false,
            ];
        })->all();

        return ['datasets' => $datasets, 'labels' => $labels->map(fn ($h) => $h->format('H:00'))->all()];
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8]]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => 'rgba(143,161,189,0.18)']],
                'x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 8]],
            ],
        ];
    }
}
