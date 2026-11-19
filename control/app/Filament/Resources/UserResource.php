<?php

namespace App\Filament\Resources;

use App\Enums\Role;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Support\Audit;
use App\Filament\Support\GatedByRole;
use App\Filament\Support\Perm;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'he-thong/nguoi-dung';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Hệ thống';

    protected static ?string $navigationLabel = 'Người dùng';

    protected static ?string $modelLabel = 'người dùng';

    protected static ?string $pluralModelLabel = 'người dùng';

    protected static ?int $navigationSort = 4;

    protected static function viewAbility(): string
    {
        return 'users.manage';
    }

    protected static function manageAbility(): ?string
    {
        return 'users.manage';
    }

    public static function lastAdminId(): ?int
    {
        $admins = User::where('role', Role::Admin->value)->pluck('id');

        return $admins->count() === 1 ? $admins->first() : null;
    }

    /** Không tự xóa mình và không xóa quản trị viên cuối cùng, để không tự khóa mình khỏi hệ thống. */
    public static function canDelete($record): bool
    {
        return Perm::can('users.manage') && $record->id !== auth()->id() && $record->id !== static::lastAdminId();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Người dùng')->columns(['default' => 1, 'md' => 2])->schema([
                Forms\Components\TextInput::make('name')->label('Họ tên')->required()->maxLength(120),
                Forms\Components\TextInput::make('email')->label('Email đăng nhập')->email()->required()->maxLength(190)->unique(ignoreRecord: true),
                Forms\Components\Select::make('role')->label('Vai trò')->required()->native(false)->live()
                    ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->getLabel()])->all())
                    ->helperText(fn ($get) => $get('role') ? Role::from($get('role'))->description() : 'Chọn vai trò để xem mô tả quyền.')
                    ->disabled(fn (?User $record) => $record && ($record->id === auth()->id() || $record->id === UserResource::lastAdminId()))->dehydrated(),
                Forms\Components\TextInput::make('password')->label('Mật khẩu')->password()->revealable()->minLength(12)->maxLength(128)
                    ->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Để trống = giữ mật khẩu hiện tại. Tối thiểu 12 ký tự.' : 'Tối thiểu 12 ký tự. Nên đặt mật khẩu tạm và yêu cầu người dùng đổi.'),
                Forms\Components\Select::make('apps')->label('Ứng dụng được gán')->relationship('apps', 'name')->multiple()->preload()->native(false)
                    ->visible(fn ($get) => $get('role') === Role::AppOwner->value)->required(fn ($get) => $get('role') === Role::AppOwner->value)
                    ->helperText('Chủ ứng dụng chỉ thấy các ứng dụng này.')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('apps'))
            ->columns([
                TextColumn::make('name')->label('Họ tên')->weight('medium')->description(fn (User $r) => $r->email)->searchable(['name', 'email'])->sortable(),
                TextColumn::make('role')->label('Vai trò')->badge()->sortable(),
                TextColumn::make('apps.name')->label('Ứng dụng được gán')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('created_at')->label('Tạo lúc')->since()->color('gray')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\DeleteAction::make()->label('Xóa')->visible(fn (User $r) => static::canDelete($r))
                    ->modalHeading('Xóa người dùng này?')->modalDescription('Người dùng mất quyền truy cập ngay. Không hoàn tác được; muốn cho vào lại phải tạo tài khoản mới.')
                    ->after(fn (User $r) => Audit::log('user.delete', $r->email)),
            ])
            ->striped()
            ->emptyStateHeading('Chưa có người dùng nào');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/them'),
            'edit' => Pages\EditUser::route('/{record}/sua'),
        ];
    }
}
