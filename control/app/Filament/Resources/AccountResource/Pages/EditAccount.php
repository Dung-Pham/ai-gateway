<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use App\Filament\Support\Audit;
use Filament\Resources\Pages\EditRecord;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    public function getTitle(): string
    {
        return 'Sửa ' . $this->record->label;
    }

    protected function afterSave(): void
    {
        Audit::log('account.update', $this->record->label);
    }
}
