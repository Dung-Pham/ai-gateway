<?php

namespace App\Filament\Resources\ModelWeightResource\Pages;

use App\Filament\Resources\ModelWeightResource;
use App\Filament\Support\Audit;
use Filament\Resources\Pages\EditRecord;

class EditModelWeight extends EditRecord
{
    protected static string $resource = ModelWeightResource::class;

    protected static ?string $title = 'Sửa hệ số model';

    protected function afterSave(): void
    {
        Audit::log('weight.update', $this->record->model, ['hệ số' => $this->record->weight]);
    }
}
