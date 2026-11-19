<?php

namespace App\Filament\Resources;

use App\Enums\AppStatus;
use App\Filament\RelationManagers\UsageEventsRelationManager;
use App\Filament\Resources\ClientAppResource\Pages;
use App\Filament\Resources\ClientAppResource\RelationManagers\KeysRelationManager;
use App\Filament\Support\Audit;
use App\Models\ClientApp;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class ClientAppResource extends Resource
{
    protected static ?string $model = ClientApp::class;

    protected static ?string $slug = 'ung-dung';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Ứng dụng';

    protected static ?string $modelLabel = 'ứng dụng';

    protected static ?string $pluralModelLabel = 'ứng dụng';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['policies.capability', 'limits'])->withMax('keys', 'last_used_at'))
            ->columns([
                TextColumn::make('name')->label('Ứng dụng')->weight('medium')->description(fn (ClientApp $r) => $r->owner)->searchable()->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge()->sortable(),
                TextColumn::make('policies.capability.name')->label('Khả năng được cấp')->badge()->color('gray'),
                ViewColumn::make('usage')->label('Mức dùng hôm nay')->view('filament.tables.columns.app-usage'),
                TextColumn::make('keys_max_last_used_at')->label('Dùng lần cuối')->since()->color('gray')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Trạng thái')->options(collect(AppStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\Action::make('suspend')->label('Tạm ngưng')->icon('heroicon-m-pause')->color('gray')
                    ->visible(fn (ClientApp $r) => $r->status === AppStatus::Active)
                    ->requiresConfirmation()->modalHeading(fn (ClientApp $r) => "Tạm ngưng {$r->name}?")
                    ->modalDescription('Mọi yêu cầu mới của ứng dụng sẽ bị từ chối ngay. Bật lại được bất cứ lúc nào.')
                    ->action(function (ClientApp $r) {
                        $r->update(['status' => AppStatus::Suspended]);
                        Audit::log('app.suspend', $r->name);
                        Notification::make()->title("Đã tạm ngưng {$r->name}")->success()->send();
                    }),
                Tables\Actions\Action::make('activate')->label('Bật lại')->icon('heroicon-m-play')
                    ->visible(fn (ClientApp $r) => $r->status === AppStatus::Suspended)
                    ->requiresConfirmation()->modalHeading(fn (ClientApp $r) => "Bật lại {$r->name}?")
                    ->action(function (ClientApp $r) {
                        $r->update(['status' => AppStatus::Active]);
                        Audit::log('app.activate', $r->name);
                        Notification::make()->title("Đã bật lại {$r->name}")->success()->send();
                    }),
            ])
            ->striped()
            ->emptyStateHeading('Chưa có ứng dụng nào')
            ->emptyStateDescription('Thêm ứng dụng đầu tiên để cấp khóa API, khả năng và hạn mức.')
            ->emptyStateActions([Tables\Actions\Action::make('create')->label('Thêm ứng dụng')->url(fn () => static::getUrl('create'))->icon('heroicon-m-plus')]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Thông tin')->columns(['default' => 1, 'md' => 3])->schema([
                TextEntry::make('name')->label('Tên')->weight('semibold'),
                TextEntry::make('owner')->label('Bộ phận phụ trách')->placeholder('—'),
                TextEntry::make('status')->label('Trạng thái')->badge(),
                TextEntry::make('description')->label('Mô tả')->columnSpanFull()->placeholder('—'),
            ]),
            Section::make('Khả năng và định tuyến')->description('Không có dòng nào nghĩa là ứng dụng không được dùng khả năng đó (mặc định từ chối).')->schema([
                RepeatableEntry::make('policies')->hiddenLabel()->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('capability.name')->label('Khả năng')->weight('medium'),
                    TextEntry::make('allowed_models')->label('Model được phép')->badge()->color('gray')->fontFamily('mono')->placeholder('Mọi model của pool'),
                    TextEntry::make('pool.name')->label('Pool chính')->placeholder('—'),
                    TextEntry::make('fallbackPool.name')->label('Pool dự phòng')->placeholder('Không có'),
                ]),
            ]),
            Section::make('Hạn mức')->description('Hạn mức nội bộ do bạn đặt. "Cứng" thì chặn khi vượt, "mềm" chỉ cảnh báo.')->schema([
                RepeatableEntry::make('limits')->hiddenLabel()->columns(['default' => 2, 'md' => 5])->schema([
                    TextEntry::make('rpm')->label('Yêu cầu / phút')->numeric(0, ',', '.')->fontFamily('mono'),
                    TextEntry::make('max_concurrency')->label('Song song tối đa')->fontFamily('mono'),
                    TextEntry::make('units_per_day')->label('Đơn vị / ngày')->numeric(0, ',', '.')->fontFamily('mono'),
                    TextEntry::make('units_per_month')->label('Đơn vị / tháng')->numeric(0, ',', '.')->fontFamily('mono'),
                    TextEntry::make('mode')->label('Chế độ')->badge()->formatStateUsing(fn ($state) => $state === 'soft' ? 'Mềm (cảnh báo)' : 'Cứng (chặn)')->color(fn ($state) => $state === 'soft' ? 'warning' : 'danger'),
                ]),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [KeysRelationManager::class, UsageEventsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientApps::route('/'),
            'create' => Pages\CreateClientApp::route('/them'),
            'view' => Pages\ViewClientApp::route('/{record}'),
        ];
    }
}
