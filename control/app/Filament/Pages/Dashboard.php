<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountGrid;
use App\Filament\Widgets\AppUsage;
use App\Filament\Widgets\AttentionList;
use App\Filament\Widgets\OverviewStats;
use App\Filament\Widgets\TrafficChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Tổng quan';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $title = 'Tổng quan';

    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return [OverviewStats::class, TrafficChart::class, AttentionList::class, AccountGrid::class, AppUsage::class];
    }

    public function getColumns(): int|string|array
    {
        return ['default' => 1, 'lg' => 3];
    }
}
