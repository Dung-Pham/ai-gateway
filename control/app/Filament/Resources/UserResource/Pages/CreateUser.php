<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Support\Audit;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Thêm người dùng';

    protected function afterCreate(): void
    {
        Audit::log('user.create', $this->record->email, ['vai trò' => $this->record->role->value]);
    }
}
