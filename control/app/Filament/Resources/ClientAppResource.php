<?php

namespace App\Filament\Resources;

use App\Enums\AppStatus;
use App\Filament\RelationManagers\UsageEventsRelationManager;
use App\Filament\Resources\ClientAppResource\Pages;
use App\Filament\Resources\ClientAppResource\RelationManagers\KeysRelationManager;
use App\Filament\Support\Audit;
use App\Models\ClientApp;
use App\Models\Capability;
use App\Models\ModelWeight;
use App\Models\Pool;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use App\Filament\Support\GatedByRole;
use App\Filament\Support\Perm;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class ClientAppResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = ClientApp::class;

    protected static ?string $slug = 'ung-dung';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Ứng dụng';

    protected static ?string $modelLabel = 'ứng dụng';

    protected static ?string $pluralModelLabel = 'ứng dụng';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function viewAbility(): string
    {
        return 'apps.view';
    }

    protected static function manageAbility(): ?string
    {
        return 'apps.manage';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Chủ ứng dụng chỉ thấy ứng dụng được gán; mở đường dẫn của ứng dụng khác sẽ ra 404.
        return Perm::scopeToOwnedApps(parent::getEloquentQuery(), 'apps.id');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông tin')->columns(['default' => 1, 'md' => 2])->schema([
                Forms\Components\TextInput::make('name')->label('Tên ứng dụng')->required()->maxLength(96),
                Forms\Components\TextInput::make('slug')->label('Mã định danh')->disabled()->dehydrated(false)->helperText('Không đổi được vì đang dùng trong nhật ký và API.'),
                Forms\Components\TextInput::make('owner')->label('Bộ phận phụ trách')->maxLength(96),
                Forms\Components\Textarea::make('description')->label('Mô tả')->rows(2)->maxLength(500)->columnSpanFull(),
            ]),
            Forms\Components\Section::make('Khả năng và định tuyến')
                ->description('Không có dòng nào nghĩa là ứng dụng không được dùng khả năng đó. Mỗi khả năng chỉ một dòng.')
                ->schema([
                    Forms\Components\Repeater::make('policies')->relationship()->hiddenLabel()->addActionLabel('Cấp thêm một khả năng')->columns(['default' => 1, 'md' => 2])
                        ->itemLabel(fn (array $state) => Capability::find($state['capability_id'] ?? null)?->name)->collapsible()
                        ->schema([
                            Forms\Components\Select::make('capability_id')->label('Khả năng')->required()->native(false)->distinct()
                                ->options(fn () => Capability::where('enabled', true)->pluck('name', 'id')->all())
                                ->helperText('Khả năng rủi ro cao (như Tìm web) chỉ cấp khi thật sự cần.'),
                            Forms\Components\Toggle::make('enabled')->label('Đang bật')->default(true)->inline(false),
                            Forms\Components\TagsInput::make('allowed_models')->label('Model được phép')
                                ->suggestions(fn () => ModelWeight::orderBy('model')->pluck('model')->all())
                                ->placeholder('Để trống = mọi model của pool')->columnSpanFull(),
                            Forms\Components\Select::make('pool_id')->label('Pool chính')->native(false)->required()->options(fn () => Pool::pluck('name', 'id')->all()),
                            Forms\Components\Select::make('fallback_pool_id')->label('Pool dự phòng')->native(false)->options(fn () => Pool::pluck('name', 'id')->all())->placeholder('Không có'),
                        ]),
                ]),
            Forms\Components\Section::make('Hạn mức')
                ->description('Hạn mức nội bộ do bạn đặt. "Cứng" chặn khi vượt, "mềm" chỉ cảnh báo. Không phải hạn mức của hãng.')
                ->schema([
                    Forms\Components\Repeater::make('limits')->relationship()->hiddenLabel()->addActionLabel('Thêm hạn mức riêng cho một khả năng')->columns(['default' => 1, 'md' => 5])
                        ->schema([
                            Forms\Components\Select::make('capability_code')->label('Áp dụng cho')->native(false)->placeholder('Tất cả khả năng')
                                ->options(fn () => Capability::pluck('name', 'code')->all()),
                            Forms\Components\TextInput::make('rpm')->label('Yêu cầu / phút')->numeric()->minValue(1),
                            Forms\Components\TextInput::make('max_concurrency')->label('Song song tối đa')->numeric()->minValue(1),
                            Forms\Components\TextInput::make('units_per_day')->label('Đơn vị / ngày')->numeric()->minValue(1),
                            Forms\Components\TextInput::make('units_per_month')->label('Đơn vị / tháng')->numeric()->minValue(1)
                                ->gte('units_per_day'),
                            Forms\Components\Select::make('mode')->label('Khi vượt')->native(false)->options(['hard' => 'Chặn (cứng)', 'soft' => 'Chỉ cảnh báo (mềm)'])->default('hard')->required(),
                        ]),
                ]),
        ]);
    }

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
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\Action::make('suspend')->label('Tạm ngưng')->icon('heroicon-m-pause')->color('gray')->authorize(fn () => Perm::can('apps.manage'))
                    ->visible(fn (ClientApp $r) => $r->status === AppStatus::Active)
                    ->requiresConfirmation()->modalHeading(fn (ClientApp $r) => "Tạm ngưng {$r->name}?")
                    ->modalDescription('Mọi yêu cầu mới của ứng dụng sẽ bị từ chối ngay. Bật lại được bất cứ lúc nào.')
                    ->action(function (ClientApp $r) {
                        $r->update(['status' => AppStatus::Suspended]);
                        Audit::log('app.suspend', $r->name);
                        Notification::make()->title("Đã tạm ngưng {$r->name}")->success()->send();
                    }),
                Tables\Actions\Action::make('activate')->label('Bật lại')->icon('heroicon-m-play')->authorize(fn () => Perm::can('apps.manage'))
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
            'edit' => Pages\EditClientApp::route('/{record}/sua'),
        ];
    }
}
