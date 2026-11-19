<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CapabilityResource\Pages;
use App\Filament\Support\Audit;
use App\Models\Capability;
use Filament\Notifications\Notification;
use App\Filament\Support\GatedByRole;
use App\Filament\Support\Perm;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CapabilityResource extends Resource
{
    use GatedByRole;

    protected static function viewAbility(): string
    {
        return 'capabilities.view';
    }

    protected static function manageAbility(): ?string
    {
        return 'capabilities.manage';
    }

    protected static ?string $model = Capability::class;

    protected static ?string $slug = 'he-thong/kha-nang';

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Hệ thống';

    protected static ?string $navigationLabel = 'Khả năng';

    protected static ?string $modelLabel = 'khả năng';

    protected static ?string $pluralModelLabel = 'danh mục khả năng';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        // Thêm khả năng mới là việc của lập trình viên (viết bộ xử lý + chạy bộ test bảo mật), không tạo từ giao diện.
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Khả năng')->weight('medium')->description(fn (Capability $r) => $r->description)->wrap(),
                TextColumn::make('code')->label('Mã')->badge()->color('gray')->fontFamily('mono'),
                TextColumn::make('risk_level')->label('Rủi ro')->badge(),
                TextColumn::make('handler')->label('Bộ xử lý')->fontFamily('mono'),
                IconColumn::make('enabled')->label('Đang bật')->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->label(fn (Capability $r) => $r->enabled ? 'Tắt' : 'Bật')->icon(fn (Capability $r) => $r->enabled ? 'heroicon-m-power' : 'heroicon-m-play')
                    ->color(fn (Capability $r) => $r->enabled ? 'gray' : 'primary')->authorize(fn () => Perm::can('capabilities.manage'))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Capability $r) => ($r->enabled ? 'Tắt' : 'Bật') . " khả năng \"{$r->name}\"?")
                    ->modalDescription(fn (Capability $r) => $r->enabled
                        ? 'Mọi ứng dụng đang dùng khả năng này sẽ bị từ chối khi gọi.'
                        : ($r->risk_level->value === 'high' ? 'Khả năng rủi ro cao. Chỉ bật khi runner riêng đã được cách ly và kiểm thử bảo mật trên staging.' : 'Ứng dụng chỉ dùng được sau khi bạn cấp khả năng này cho từng ứng dụng.'))
                    ->action(function (Capability $r) {
                        $r->update(['enabled' => ! $r->enabled]);
                        Audit::log('capability.toggle', $r->code, ['bật' => $r->enabled]);
                        Notification::make()->title('Đã cập nhật khả năng')->success()->send();
                    }),
            ])
            ->paginated(false)
            ->striped();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCapabilities::route('/')];
    }
}
