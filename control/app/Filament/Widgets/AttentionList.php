<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AccountResource;
use App\Filament\Resources\ClientAppResource;
use App\Models\Alert;
use Filament\Widgets\Widget;

class AttentionList extends Widget
{
    protected static string $view = 'filament.widgets.attention-list';

    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = ['lg' => 1];

    protected function getViewData(): array
    {
        $order = ['critical' => 0, 'warning' => 1, 'info' => 2];
        $alerts = Alert::open()->whereNull('acknowledged_at')->get()
            ->sortBy([fn ($a, $b) => $order[$a->severity->value] <=> $order[$b->severity->value], fn ($a, $b) => $b->opened_at <=> $a->opened_at])
            ->take(5)
            ->map(function (Alert $a) {
                $url = match ($a->subject_type) {
                    'account' => AccountResource::getUrl('view', ['record' => $a->subject_id]),
                    'app' => ClientAppResource::getUrl('view', ['record' => $a->subject_id]),
                    default => null,
                };

                return ['alert' => $a, 'url' => $url];
            });

        return ['items' => $alerts, 'total' => Alert::open()->whereNull('acknowledged_at')->count()];
    }
}
