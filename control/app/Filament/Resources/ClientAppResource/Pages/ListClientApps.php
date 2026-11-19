<?php

namespace App\Filament\Resources\ClientAppResource\Pages;

use App\Filament\Resources\ClientAppResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClientApps extends ListRecords
{
    protected static string $resource = ClientAppResource::class;

    protected static ?string $title = 'Ứng dụng';

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Thêm ứng dụng')->icon('heroicon-m-plus')];
    }
}
