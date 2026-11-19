<?php

namespace App\Filament\Resources\ClientAppResource\Pages;

use App\Enums\AppStatus;
use App\Filament\Resources\ClientAppResource;
use App\Filament\Support\Audit;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewClientApp extends ViewRecord
{
    protected static string $resource = ClientAppResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('suspend')->label('Tạm ngưng ứng dụng')->icon('heroicon-m-pause')->color('danger')->outlined()
                ->visible(fn () => $this->record->status === AppStatus::Active)
                ->requiresConfirmation()->modalHeading("Tạm ngưng {$this->record->name}?")
                ->modalDescription('Mọi yêu cầu mới của ứng dụng sẽ bị từ chối ngay. Khóa API không bị thu hồi. Bật lại được bất cứ lúc nào.')
                ->action(function () {
                    $this->record->update(['status' => AppStatus::Suspended]);
                    Audit::log('app.suspend', $this->record->name);
                    Notification::make()->title('Đã tạm ngưng ứng dụng')->success()->send();
                    $this->redirect(ClientAppResource::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('activate')->label('Bật lại ứng dụng')->icon('heroicon-m-play')
                ->visible(fn () => $this->record->status === AppStatus::Suspended)
                ->requiresConfirmation()->modalHeading("Bật lại {$this->record->name}?")
                ->action(function () {
                    $this->record->update(['status' => AppStatus::Active]);
                    Audit::log('app.activate', $this->record->name);
                    Notification::make()->title('Đã bật lại ứng dụng')->success()->send();
                    $this->redirect(ClientAppResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
