<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Enums\AccountStatus;
use App\Filament\Resources\AccountResource;
use App\Filament\Support\Audit;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    public function getTitle(): string
    {
        return $this->record->label;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('check')->label('Kiểm tra ngay')->icon('heroicon-m-arrow-path')->color('gray')
                ->requiresConfirmation()->modalHeading('Kiểm tra phiên đăng nhập?')
                ->modalDescription('Gateway sẽ gọi thử một lượt rất nhẹ. Lượt này tính vào hạn mức của tài khoản. (Khung dựng: chưa có gateway thật nên chỉ ghi nhận thao tác.)')
                ->action(function () {
                    Audit::log('account.check', $this->record->label, ['ghi chú' => 'khung dựng, chưa gọi gateway']);
                    Notification::make()->title('Đã ghi nhận yêu cầu kiểm tra')->body('Khung dựng chưa nối với gateway thật.')->warning()->send();
                }),
            Actions\Action::make('pause')->label('Tạm dừng')->icon('heroicon-m-pause')->color('gray')
                ->visible(fn () => in_array($this->record->status, [AccountStatus::Active, AccountStatus::Draining]))
                ->requiresConfirmation()->modalHeading("Tạm dừng {$this->record->label}?")
                ->modalDescription('Tài khoản sẽ không nhận yêu cầu mới. Yêu cầu đang chạy vẫn hoàn tất. Có thể bật lại bất cứ lúc nào.')
                ->action(function () {
                    $this->record->update(['status' => AccountStatus::Paused]);
                    Audit::log('account.pause', $this->record->label);
                    Notification::make()->title("Đã tạm dừng {$this->record->label}")->success()->send();
                    $this->redirect(AccountResource::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('resume')->label('Bật lại')->icon('heroicon-m-play')
                ->visible(fn () => $this->record->status === AccountStatus::Paused)
                ->requiresConfirmation()->modalHeading("Bật lại {$this->record->label}?")
                ->action(function () {
                    $this->record->update(['status' => AccountStatus::Active]);
                    Audit::log('account.resume', $this->record->label);
                    Notification::make()->title("Đã bật lại {$this->record->label}")->success()->send();
                    $this->redirect(AccountResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
