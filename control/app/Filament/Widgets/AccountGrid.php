<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use Filament\Widgets\Widget;

class AccountGrid extends Widget
{
    protected static string $view = 'filament.widgets.account-grid';

    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return ['accounts' => Account::with('provider')->orderBy('provider_id')->orderBy('label')->get()];
    }
}
