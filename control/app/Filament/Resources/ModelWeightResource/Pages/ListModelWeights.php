<?php

namespace App\Filament\Resources\ModelWeightResource\Pages;

use App\Filament\Resources\ModelWeightResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListModelWeights extends ListRecords
{
    protected static string $resource = ModelWeightResource::class;

    protected static ?string $title = 'Hệ số model';

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Thêm hệ số')->icon('heroicon-m-plus')];
    }
}
