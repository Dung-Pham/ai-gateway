<?php

namespace App\Filament\Pages;

use App\Enums\AppStatus;
use App\Filament\Support\Audit;
use App\Models\ClientApp;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class EmergencyStop extends Page implements HasActions
{
    use InteractsWithActions;

    protected static string $view = 'filament.pages.emergency-stop';

    protected static ?string $navigationIcon = 'heroicon-o-hand-raised';

    protected static ?string $navigationGroup = 'Hệ thống';

    protected static ?string $navigationLabel = 'Dừng khẩn cấp';

    protected static ?string $title = 'Dừng khẩn cấp';

    protected static ?string $slug = 'he-thong/dung-khan-cap';

    protected static ?int $navigationSort = 3;

    public function stopAction(): Action
    {
        return Action::make('stop')->label('Tạm ngưng tất cả ứng dụng')->icon('heroicon-m-hand-raised')->color('danger')
            ->modalHeading('Tạm ngưng toàn bộ ứng dụng?')
            ->modalDescription('Mọi ứng dụng đang hoạt động sẽ bị từ chối yêu cầu mới ngay lập tức. Khóa API không bị thu hồi. Có thể khôi phục ở đây.')
            ->modalSubmitActionLabel('Tạm ngưng tất cả')
            ->form([TextInput::make('confirm')->label('Gõ DUNG (viết hoa) để xác nhận')->required()->autocomplete(false)
                ->rule(fn () => fn (string $attribute, $value, \Closure $fail) => $value === 'DUNG' ? null : $fail('Gõ đúng chữ DUNG, viết hoa.'))])
            ->action(function () {
                $ids = ClientApp::where('status', AppStatus::Active->value)->pluck('id')->all();
                ClientApp::whereIn('id', $ids)->update(['status' => AppStatus::Suspended->value]);
                Cache::forever('gw.emergency.prev', $ids);
                Audit::log('emergency.stop', 'tất cả ứng dụng', ['số ứng dụng' => count($ids)]);
                Notification::make()->title('Đã tạm ngưng ' . count($ids) . ' ứng dụng')->danger()->send();
            });
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')->label('Khôi phục các ứng dụng đã bị ngưng')->icon('heroicon-m-arrow-uturn-left')->color('gray')
            ->visible(fn () => count(Cache::get('gw.emergency.prev', [])) > 0)
            ->requiresConfirmation()->modalHeading('Khôi phục các ứng dụng?')
            ->modalDescription('Chỉ khôi phục những ứng dụng đã bị ngưng bởi lần dừng khẩn cấp gần nhất.')
            ->action(function () {
                $ids = Cache::pull('gw.emergency.prev', []);
                ClientApp::whereIn('id', $ids)->update(['status' => AppStatus::Active->value]);
                Audit::log('emergency.restore', 'ứng dụng', ['số ứng dụng' => count($ids)]);
                Notification::make()->title('Đã khôi phục ' . count($ids) . ' ứng dụng')->success()->send();
            });
    }
}
