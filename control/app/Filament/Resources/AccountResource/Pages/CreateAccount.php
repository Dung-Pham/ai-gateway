<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Enums\AccountStatus;
use App\Filament\Resources\AccountResource;
use App\Filament\Support\Audit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected static ?string $title = 'Thêm tài khoản AI';

    public function getSubheading(): ?string
    {
        return 'Bước 1 trong 3: tạo bản ghi. Bước 2: người vận hành tạo runner và đăng nhập trên server. Bước 3: bấm "Kiểm tra ngay".';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Tài khoản mới chưa đăng nhập nên chưa nhận yêu cầu cho tới khi kiểm tra phiên thành công.
        return $data + ['status' => AccountStatus::Pending->value, 'current_concurrency' => 0];
    }

    protected function afterCreate(): void
    {
        Audit::log('account.create', $this->record->label, ['hãng' => $this->record->provider->name]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()->success()->persistent()->title('Đã tạo bản ghi tài khoản (chờ đăng nhập)')
            ->body('Tiếp theo: người vận hành tạo runner và đăng nhập trên server (lệnh cụ thể chốt sau bước thăm dò CLI), rồi bấm "Kiểm tra ngay" ở trang tài khoản.');
    }
}
