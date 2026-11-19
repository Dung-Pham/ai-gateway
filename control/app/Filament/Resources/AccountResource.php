<?php

namespace App\Filament\Resources;

use App\Enums\AccountStatus;
use App\Filament\RelationManagers\UsageEventsRelationManager;
use App\Filament\Resources\AccountResource\Pages;
use App\Filament\Support\Audit;
use App\Models\Account;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $slug = 'tai-khoan';

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Tài khoản AI';

    protected static ?string $modelLabel = 'tài khoản AI';

    protected static ?string $pluralModelLabel = 'tài khoản AI';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'label';

    public static function canCreate(): bool
    {
        // Thêm tài khoản là việc của người vận hành (script tạo runner + đăng nhập qua SSH), không làm trên web.
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('provider'))
            ->columns([
                ViewColumn::make('label')->label('Tài khoản')->view('filament.tables.columns.account-name')->searchable()->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge()->sortable(),
                ViewColumn::make('quota')->label('Mức dùng')->view('filament.tables.columns.quota'),
                TextColumn::make('slots')->label('Slot')->state(fn (Account $r) => "{$r->current_concurrency}/{$r->max_concurrency}")->fontFamily('mono')->alignEnd(),
                TextColumn::make('cooldown_until')->label('Nghỉ đến')->dateTime('H:i d/m')->fontFamily('mono')->placeholder('—'),
                TextColumn::make('last_auth_check_at')->label('Kiểm tra phiên')->since()->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider')->label('Hãng')->relationship('provider', 'name'),
                Tables\Filters\SelectFilter::make('status')->label('Trạng thái')->options(collect(AccountStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                self::pauseAction(),
                self::resumeAction(),
            ])
            ->defaultSort('provider_id')
            ->striped()
            ->poll('30s')
            ->emptyStateHeading('Chưa có tài khoản AI nào')
            ->emptyStateDescription('Thêm tài khoản bằng script của người vận hành (xem hướng dẫn trong runbook), sau đó bấm "Kiểm tra ngay".');
    }

    public static function pauseAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('pause')->label('Tạm dừng')->icon('heroicon-m-pause')->color('gray')
            ->visible(fn (Account $r) => in_array($r->status, [AccountStatus::Active, AccountStatus::Draining]))
            ->requiresConfirmation()->modalHeading(fn (Account $r) => "Tạm dừng {$r->label}?")
            ->modalDescription('Tài khoản sẽ không nhận yêu cầu mới. Yêu cầu đang chạy vẫn hoàn tất. Có thể bật lại bất cứ lúc nào.')
            ->action(function (Account $r) {
                $r->update(['status' => AccountStatus::Paused]);
                Audit::log('account.pause', $r->label);
                Notification::make()->title("Đã tạm dừng {$r->label}")->success()->send();
            });
    }

    public static function resumeAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('resume')->label('Bật lại')->icon('heroicon-m-play')->color('primary')
            ->visible(fn (Account $r) => $r->status === AccountStatus::Paused)
            ->requiresConfirmation()->modalHeading(fn (Account $r) => "Bật lại {$r->label}?")
            ->action(function (Account $r) {
                $r->update(['status' => AccountStatus::Active]);
                Audit::log('account.resume', $r->label);
                Notification::make()->title("Đã bật lại {$r->label}")->success()->send();
            });
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Thông tin')->columns(['default' => 1, 'md' => 3])->schema([
                TextEntry::make('label')->label('Nhãn')->fontFamily('mono')->weight('semibold')->copyable(),
                TextEntry::make('provider.name')->label('Hãng')->badge()->color('gray'),
                TextEntry::make('status')->label('Trạng thái')->badge(),
                TextEntry::make('plan')->label('Gói')->placeholder('—'),
                TextEntry::make('runner_url')->label('Runner (nội bộ)')->fontFamily('mono'),
                TextEntry::make('last_auth_check_at')->label('Kiểm tra phiên lần cuối')->since(),
                TextEntry::make('cooldown_until')->label('Nghỉ đến')->dateTime('H:i d/m/Y')->placeholder('—')->visible(fn (Account $r) => $r->status === AccountStatus::Cooldown),
            ]),
            Section::make('Mức dùng')->description('Nguồn của từng con số được ghi rõ. Không có số nào là hạn mức chính thức của hãng.')->schema([
                ViewEntry::make('quota')->view('filament.infolists.quota-detail')->hiddenLabel(),
            ]),
            Section::make('Cần đăng nhập lại')->icon('heroicon-m-exclamation-triangle')->iconColor('danger')
                ->visible(fn (Account $r) => $r->status === AccountStatus::AuthExpired)
                ->schema([
                    TextEntry::make('notes')->hiddenLabel()->default('Phiên đăng nhập đã hết.'),
                    TextEntry::make('relogin')->hiddenLabel()->state('Đăng nhập lại do người vận hành làm bằng tay trên server (SSH vào runner của tài khoản này). Giao diện web không nhận mật khẩu hay token. Lệnh cụ thể sẽ chốt sau bước thăm dò CLI. Xong, quay lại đây bấm "Kiểm tra ngay".')->color('gray'),
                ]),
            Section::make('Thuộc pool')->schema([
                TextEntry::make('pools.name')->hiddenLabel()->badge()->placeholder('Chưa thuộc pool nào'),
            ])->collapsible(),
        ]);
    }

    public static function getRelations(): array
    {
        return [UsageEventsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'view' => Pages\ViewAccount::route('/{record}'),
        ];
    }
}
