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
            Actions\EditAction::make()->label('Sửa')->icon('heroicon-m-pencil-square'),
            Actions\Action::make('login_now')->label('Đăng nhập')->icon('heroicon-m-key')->color('primary')->authorize(fn () => \App\Filament\Support\Perm::can('accounts.manage'))
                ->visible(fn () => \App\Services\Gateway\LoginGuide::supportsWeb($this->record))
                ->url(fn () => AccountResource::getUrl('dang-nhap', ['record' => $this->record])),
            Actions\Action::make('login')->label('Đăng nhập bằng terminal')->icon('heroicon-m-command-line')->color('gray')->authorize(fn () => \App\Filament\Support\Perm::can('accounts.manage'))
                ->modalHeading("Đăng nhập lại {$this->record->label}")
                ->modalContent(fn () => view('filament.modals.account-login', ['account' => $this->record, 'steps' => \App\Services\Gateway\LoginGuide::steps($this->record), 'command' => \App\Services\Gateway\LoginGuide::command($this->record)]))
                ->modalSubmitActionLabel('Tôi đã đăng nhập xong: kiểm tra')->modalCancelActionLabel('Đóng')
                ->action(function () {
                    $res = app(\App\Services\Gateway\AccountSync::class)->applyCheck($this->record, app(\App\Services\Gateway\GatewayClient::class)->check($this->record->label), recordLogin: true);
                    Audit::log('account.login', $this->record->label, ['kết quả' => $res['ok'] ? 'đạt' : 'không đạt']);
                    Notification::make()->title($res['ok'] ? "{$this->record->label}: đã ghi nhận đăng nhập" : "{$this->record->label}: chưa đăng nhập được")->body($res['message'])->{$res['ok'] ? 'success' : 'danger'}()->send();
                    $this->redirect(AccountResource::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('check')->label('Kiểm tra ngay')->icon('heroicon-m-arrow-path')->color('gray')->authorize(fn () => \App\Filament\Support\Perm::can('accounts.manage'))
                ->requiresConfirmation()->modalHeading('Kiểm tra phiên đăng nhập?')
                ->modalDescription('Gateway sẽ gọi thử một lượt rất nhỏ qua runner của tài khoản này. Lượt này tính vào hạn mức của tài khoản nhưng không tính cho ứng dụng nào.')
                ->action(function () {
                    $res = app(\App\Services\Gateway\AccountSync::class)->applyCheck($this->record, app(\App\Services\Gateway\GatewayClient::class)->check($this->record->label));
                    Audit::log('account.check', $this->record->label, ['kết quả' => $res['ok'] ? 'đạt' : 'không đạt']);
                    Notification::make()->title($res['ok'] ? "{$this->record->label}: đạt" : "{$this->record->label}: không đạt")->body($res['message'])->{$res['ok'] ? 'success' : 'danger'}()->send();
                    $this->redirect(AccountResource::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('pause')->label('Tạm dừng')->icon('heroicon-m-pause')->color('gray')->authorize(fn () => \App\Filament\Support\Perm::can('accounts.manage'))
                ->visible(fn () => in_array($this->record->status, [AccountStatus::Active, AccountStatus::Draining]))
                ->requiresConfirmation()->modalHeading("Tạm dừng {$this->record->label}?")
                ->modalDescription('Tài khoản sẽ không nhận yêu cầu mới. Yêu cầu đang chạy vẫn hoàn tất. Có thể bật lại bất cứ lúc nào.')
                ->action(function () {
                    $this->record->update(['status' => AccountStatus::Paused]);
                    Audit::log('account.pause', $this->record->label);
                    Notification::make()->title("Đã tạm dừng {$this->record->label}")->success()->send();
                    $this->redirect(AccountResource::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('resume')->label('Bật lại')->icon('heroicon-m-play')->authorize(fn () => \App\Filament\Support\Perm::can('accounts.manage'))
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
