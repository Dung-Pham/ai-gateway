<?php

namespace App\Filament\Resources\ModelWeightResource\Pages;

use App\Filament\Resources\ModelWeightResource;
use App\Filament\Support\Audit;
use Filament\Resources\Pages\CreateRecord;

class CreateModelWeight extends CreateRecord
{
    protected static string $resource = ModelWeightResource::class;

    protected static ?string $title = 'Thêm hệ số model';

    protected function afterCreate(): void
    {
        Audit::log('weight.create', $this->record->model, ['hệ số' => $this->record->weight]);
    }
}
