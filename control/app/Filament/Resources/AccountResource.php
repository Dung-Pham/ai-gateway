<?php

namespace App\Filament\Resources;

use App\Enums\AccountStatus;
use App\Filament\RelationManagers\UsageEventsRelationManager;
use App\Filament\Resources\AccountResource\Pages;
use App\Filament\Support\Audit;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use App\Filament\Support\GatedByRole;
use App\Filament\Support\Perm;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = Account::class;

    protected static ?string $slug = 'tai-khoan';

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Tài khoản AI';

    protected static ?string $modelLabel = 'tài khoản AI';

    protected static ?string $pluralModelLabel = 'tài khoản AI';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'label';

    protected static function viewAbility(): string
    {
        return 'accounts.view';
    }

    protected static function manageAbility(): ?string
    {
        return 'accounts.manage';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Tài khoản AI')
                ->description('Chỉ nhập thông tin mô tả. Giao diện này KHÔNG nhận mật khẩu, mã 2FA hay token: đăng nhập tài khoản do người vận hành làm trên server.')
                ->columns(['default' => 1, 'md' => 2])->schema([
                    Forms\Components\Select::make('provider_id')->label('Hãng')->relationship('provider', 'name')->required()->native(false)
                        ->disabledOn('edit')->dehydrated(),
                    Forms\Components\TextInput::make('label')->label('Nhãn')->required()->maxLength(64)
                        ->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')->unique(ignoreRecord: true)->disabledOn('edit')->dehydrated()->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set, string $operation) => $operation === 'create' && filled($state) ? $set('runner_url', "http://runner-{$state}:8800") : null)
                        ->helperText('Chữ thường, số, gạch ngang (ví dụ claude-runner-01). Xuất hiện trong nhật ký nên không đổi được sau khi tạo.'),
                    Forms\Components\TextInput::make('plan')->label('Gói')->maxLength(64)->placeholder('Ví dụ: Gói Max'),
                    Forms\Components\TextInput::make('max_concurrency')->label('Số lượt chạy song song tối đa')->numeric()->minValue(1)->maxValue(16)->default(2)->required()
                        ->helperText('Mỗi lượt là một tiến trình CLI tốn RAM. Giá trị an toàn chốt sau khi đo (thăm dò P8).'),
                    Forms\Components\TextInput::make('runner_url')->label('Địa chỉ runner (nội bộ)')->required()->maxLength(255)->columnSpanFull()
                        ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                            // Chống SSRF: chỉ chấp nhận tên máy dạng runner-<nhãn> trong mạng Docker nội bộ, không IP, không đường dẫn.
                            $u = parse_url((string) $value);
                            $ok = is_array($u) && ($u['scheme'] ?? '') === 'http' && preg_match('/^runner-[a-z0-9]+(-[a-z0-9]+)*$/', $u['host'] ?? '')
                                && array_intersect_key($u, array_flip(['user', 'pass', 'query', 'fragment'])) === [] && in_array($u['path'] ?? '', ['', '/'], true)
                                && (! isset($u['port']) || ($u['port'] >= 1024 && $u['port'] <= 65535));
                            if (! $ok) {
                                $fail('Chỉ nhận dạng http://runner-<nhãn>:<cổng> (tên máy nội bộ, không IP, không đường dẫn).');
                            }
                        }])
                        ->helperText('Tự điền theo nhãn. Chỉ nhận tên máy nội bộ dạng runner-<nhãn>, để gateway không bị trỏ tới địa chỉ tùy ý.'),
                    Forms\Components\Textarea::make('notes')->label('Ghi chú')->rows(2)->maxLength(500)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('provider'))
            ->columns([
                ViewColumn::make('label')->label('Tài khoản')->view('filament.tables.columns.account-name')->searchable()->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge()->sortable(),
                ViewColumn::make('quota')->label('Mức dùng')->view('filament.tables.columns.quota'),
                TextColumn::make('login_days_left')->label('Đăng nhập')->badge()
                    ->state(fn (Account $r) => match ($r->login_level) { 'unknown' => 'Chưa rõ hạn', 'expired' => 'Đã hết hạn', default => 'Còn '.$r->login_days_left.' ngày' })
                    ->color(fn (Account $r) => match ($r->login_level) { 'expired', 'critical' => 'danger', 'warning' => 'warning', 'ok' => 'success', default => 'gray' })
                    ->tooltip(fn (Account $r) => $r->login_expires_at ? 'Hết hạn '.$r->login_expires_at->setTimezone(config('gateway.timezone'))->format('d/m/Y').' ('.$r->login_source_label.')' : 'Hãng này chưa cho biết hạn đăng nhập; đặt hạn ước tính ở Hệ thống → Cài đặt.'),
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
                Tables\Actions\EditAction::make()->label('Sửa'),
                self::loginAction(),
                self::checkAction(),
                self::pauseAction(),
                self::resumeAction(),
            ])
            ->defaultSort('provider_id')
            ->striped()
            ->poll('30s')
            ->emptyStateHeading('Chưa có tài khoản AI nào')
            ->emptyStateDescription('Thêm tài khoản bằng script của người vận hành (xem hướng dẫn trong runbook), sau đó bấm "Kiểm tra ngay".');
    }

    /** "Đăng nhập lại": hướng dẫn từng bước + lệnh sao chép được; xong thì kiểm tra và ghi nhận thời điểm đăng nhập. KHÔNG nhận mật khẩu/mã/token. */
    public static function loginAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('login')->label('Đăng nhập lại')->icon('heroicon-m-key')->color('gray')
            ->authorize(fn () => Perm::can('accounts.manage'))
            ->modalHeading(fn (Account $r) => "Đăng nhập lại {$r->label}")
            ->modalContent(fn (Account $r) => view('filament.modals.account-login', ['account' => $r, 'steps' => \App\Services\Gateway\LoginGuide::steps($r), 'command' => \App\Services\Gateway\LoginGuide::command($r)]))
            ->modalSubmitActionLabel('Tôi đã đăng nhập xong: kiểm tra')->modalCancelActionLabel('Đóng')
            ->action(function (Account $r) {
                $res = app(\App\Services\Gateway\AccountSync::class)->applyCheck($r, app(\App\Services\Gateway\GatewayClient::class)->check($r->label), recordLogin: true);
                Audit::log('account.login', $r->label, ['kết quả' => $res['ok'] ? 'đạt' : 'không đạt']);
                Notification::make()->title($res['ok'] ? "{$r->label}: đã ghi nhận đăng nhập" : "{$r->label}: chưa đăng nhập được")->body($res['message'])->{$res['ok'] ? 'success' : 'danger'}()->send();
            });
    }

    /** "Kiểm tra ngay": gateway gọi một lượt rất nhỏ qua runner của tài khoản. Dùng ở bảng và trang chi tiết. */
    public static function checkAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('check')->label('Kiểm tra ngay')->icon('heroicon-m-arrow-path')->color('gray')
            ->authorize(fn () => Perm::can('accounts.manage'))
            ->requiresConfirmation()->modalHeading(fn (Account $r) => "Kiểm tra {$r->label}?")
            ->modalDescription('Gateway sẽ gọi thử một lượt rất nhỏ qua runner của tài khoản này. Lượt này tính vào hạn mức của tài khoản nhưng không tính cho ứng dụng nào.')
            ->action(function (Account $r) {
                $res = app(\App\Services\Gateway\AccountSync::class)->applyCheck($r, app(\App\Services\Gateway\GatewayClient::class)->check($r->label));
                Audit::log('account.check', $r->label, ['kết quả' => $res['ok'] ? 'đạt' : 'không đạt']);
                Notification::make()->title($res['ok'] ? "{$r->label}: đạt" : "{$r->label}: không đạt")->body($res['message'])->{$res['ok'] ? 'success' : 'danger'}()->send();
            });
    }

    public static function pauseAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('pause')->label('Tạm dừng')->icon('heroicon-m-pause')->color('gray')
            ->authorize(fn () => Perm::can('accounts.manage'))->visible(fn (Account $r) => in_array($r->status, [AccountStatus::Active, AccountStatus::Draining]))
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
            ->authorize(fn () => Perm::can('accounts.manage'))->visible(fn (Account $r) => $r->status === AccountStatus::Paused)
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
            Section::make('Đăng nhập')->description('Phiên đăng nhập có hạn. Giao diện nhắc trước khi hết hạn; đăng nhập do người sở hữu tài khoản làm trong terminal.')->columns(['default' => 1, 'md' => 3])->schema([
                TextEntry::make('login_expires_at')->label('Hết hạn')->dateTime('d/m/Y')->placeholder('Chưa rõ hạn'),
                TextEntry::make('login_days_left')->label('Còn lại')->state(fn (Account $r) => $r->login_days_left === null ? '—' : ($r->login_days_left <= 0 ? 'Đã hết hạn' : $r->login_days_left.' ngày'))
                    ->badge()->color(fn (Account $r) => match ($r->login_level) { 'expired', 'critical' => 'danger', 'warning' => 'warning', 'ok' => 'success', default => 'gray' }),
                TextEntry::make('login_source_label')->label('Nguồn')->color('gray'),
                TextEntry::make('logged_in_at')->label('Đăng nhập lần cuối được ghi nhận')->dateTime('d/m/Y H:i')->placeholder('Chưa ghi nhận'),
            ]),
            Section::make('Cần đăng nhập lại')->icon('heroicon-m-exclamation-triangle')->iconColor('danger')
                ->visible(fn (Account $r) => $r->status === AccountStatus::AuthExpired)
                ->schema([
                    TextEntry::make('notes')->hiddenLabel()->default('Phiên đăng nhập đã hết.'),
                    TextEntry::make('relogin')->hiddenLabel()->state('Bấm "Đăng nhập lại" ở đầu trang để xem các bước và lệnh. Giao diện web không nhận mật khẩu, mã hay token. Xong, bấm "Tôi đã đăng nhập xong: kiểm tra".')->color('gray'),
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
            'create' => Pages\CreateAccount::route('/them'),
            'view' => Pages\ViewAccount::route('/{record}'),
            'edit' => Pages\EditAccount::route('/{record}/sua'),
        ];
    }
}
