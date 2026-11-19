<?php

namespace App\Filament\Resources\ClientAppResource\Pages;

use App\Filament\Resources\ClientAppResource;
use App\Filament\Support\Audit;
use Filament\Resources\Pages\EditRecord;

class EditClientApp extends EditRecord
{
    protected static string $resource = ClientAppResource::class;

    public function getTitle(): string
    {
        return 'Sửa ' . $this->record->name;
    }

    protected function afterSave(): void
    {
        Audit::log('app.update', $this->record->name, ['khả năng' => $this->record->policies()->count(), 'hạn mức' => $this->record->limits()->count()]);
    }
}
