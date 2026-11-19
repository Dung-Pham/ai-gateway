<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\Role;
use App\Filament\Resources\UserResource;
use App\Filament\Support\Audit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'Sửa ' . $this->record->name;
    }

    /** Chặn hạ quyền người cuối cùng là quản trị viên (kể cả khi trường vai trò bị gửi lên trái phép). */
    protected function beforeSave(): void
    {
        $newRole = $this->data['role'] ?? null;
        if ($this->record->role === Role::Admin && $newRole !== Role::Admin->value && $this->record->id === UserResource::lastAdminId()) {
            Notification::make()->title('Không thể hạ quyền quản trị viên cuối cùng')->danger()->send();
            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        Audit::log('user.update', $this->record->email, ['vai trò' => $this->record->role->value]);
    }
}
