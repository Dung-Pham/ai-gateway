<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModelWeightResource\Pages;
use App\Filament\Support\Audit;
use App\Filament\Support\GatedByRole;
use App\Filament\Support\Perm;
use App\Models\ModelWeight;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModelWeightResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = ModelWeight::class;

    protected static ?string $slug = 'he-thong/he-so-model';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Hệ thống';

    protected static ?string $navigationLabel = 'Hệ số model';

    protected static ?string $modelLabel = 'hệ số model';

    protected static ?string $pluralModelLabel = 'hệ số model';

    protected static ?int $navigationSort = 2;

    protected static function viewAbility(): string
    {
        return 'weights.view';
    }

    protected static function manageAbility(): ?string
    {
        return 'weights.manage';
    }

    public static function canDelete($record): bool
    {
        return Perm::can('weights.manage');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Hệ số quy đổi')->description('Đơn vị quy đổi của một lượt gọi = (token vào + 3 × token ra) / 100 × hệ số. Dùng để so sánh mức dùng giữa các model, không phải hạn mức của hãng.')->columns(['default' => 1, 'md' => 2])->schema([
                Forms\Components\TextInput::make('model')->label('Tên model')->required()->maxLength(64)->regex('/^[A-Za-z0-9._:-]+$/')->unique(ignoreRecord: true)->disabledOn('edit')->dehydrated(),
                Forms\Components\TextInput::make('weight')->label('Hệ số')->required()->numeric()->minValue(0.001)->maxValue(1000)->helperText('1 = mức chuẩn. Model tốn hạn mức hơn thì đặt số lớn hơn.'),
                Forms\Components\TextInput::make('note')->label('Ghi chú')->maxLength(255)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('model')->label('Model')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('weight')->label('Hệ số')->numeric(3, ',', '.')->fontFamily('mono')->alignEnd()->sortable(),
                TextColumn::make('note')->label('Ghi chú')->color('gray')->placeholder('—'),
                TextColumn::make('updated_at')->label('Cập nhật')->since()->color('gray'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\DeleteAction::make()->label('Xóa')->authorize(fn () => Perm::can('weights.manage'))
                    ->modalHeading('Xóa hệ số của model này?')->modalDescription('Các lượt gọi sau của model này sẽ không có hệ số cho tới khi bạn thêm lại.')
                    ->after(fn ($record) => Audit::log('weight.delete', $record->model)),
            ])
            ->defaultSort('model')
            ->paginated(false)
            ->striped()
            ->emptyStateHeading('Chưa có hệ số nào')
            ->emptyStateDescription('Thêm hệ số cho từng model để gateway quy đổi mức dùng ra cùng một đơn vị.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModelWeights::route('/'),
            'create' => Pages\CreateModelWeight::route('/them'),
            'edit' => Pages\EditModelWeight::route('/{record}/sua'),
        ];
    }
}
