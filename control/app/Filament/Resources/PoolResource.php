<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PoolResource\Pages;
use App\Models\Account;
use App\Models\Pool;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use App\Filament\Support\GatedByRole;
use App\Filament\Support\Perm;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PoolResource extends Resource
{
    use GatedByRole;

    protected static function viewAbility(): string
    {
        return 'pools.view';
    }

    protected static function manageAbility(): ?string
    {
        return 'pools.manage';
    }

    protected static ?string $model = Pool::class;

    protected static ?string $slug = 'dinh-tuyen';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Định tuyến';

    protected static ?string $modelLabel = 'pool';

    protected static ?string $pluralModelLabel = 'pool định tuyến';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Pool')->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('name')->label('Tên pool')->required()->maxLength(64)->unique(ignoreRecord: true),
                Select::make('strategy')->label('Cách chọn tài khoản')->options(Pool::strategies())->required()->native(false)
                    ->helperText('"Ít dùng nhất" ưu tiên tài khoản còn nhiều chỗ trống; "Theo thứ tự" luôn thử tài khoản ưu tiên cao trước.'),
                Textarea::make('description')->label('Mô tả')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Tài khoản trong pool')->description('Số ưu tiên nhỏ hơn được thử trước. Tài khoản đang nghỉ hoặc hết phiên tự bị bỏ qua.')->schema([
                Repeater::make('members')->relationship()->hiddenLabel()->addActionLabel('Thêm tài khoản')->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        Select::make('account_id')->label('Tài khoản')->required()->native(false)->distinct()->searchable()
                            ->options(fn () => Account::with('provider')->orderBy('label')->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->label} ({$a->provider->name})"])->all()),
                        TextInput::make('priority')->label('Ưu tiên')->numeric()->default(1)->minValue(1)->required(),
                        TextInput::make('weight')->label('Trọng số')->numeric()->default(1)->minValue(1)->required(),
                    ])->itemLabel(fn (array $state) => Account::find($state['account_id'] ?? null)?->label),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('accounts')->withCount('policies'))
            ->columns([
                TextColumn::make('name')->label('Pool')->weight('medium')->description(fn (Pool $r) => $r->description)->searchable(),
                TextColumn::make('strategy')->label('Cách chọn')->formatStateUsing(fn ($state) => Pool::strategies()[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('accounts.label')->label('Tài khoản')->badge()->fontFamily('mono')->color('primary'),
                TextColumn::make('policies_count')->label('Chính sách dùng')->alignEnd()->fontFamily('mono'),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('Sửa')])
            ->striped()
            ->emptyStateHeading('Chưa có pool nào')
            ->emptyStateDescription('Pool gom các tài khoản cùng nhiệm vụ để gateway tự chọn và chuyển dự phòng.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPools::route('/'),
            'create' => Pages\CreatePool::route('/them'),
            'edit' => Pages\EditPool::route('/{record}/sua'),
        ];
    }
}
