<?php

namespace App\Filament\Resources;

use App\Enums\UsageStatus;
use App\Filament\Resources\UsageEventResource\Pages;
use App\Filament\Support\UsageEventTable;
use App\Models\UsageEvent;
use Filament\Forms\Components\DatePicker;
use App\Filament\Support\GatedByRole;
use App\Filament\Support\Perm;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsageEventResource extends Resource
{
    use GatedByRole;

    protected static function viewAbility(): string
    {
        return 'logs.view';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Perm::scopeToOwnedApps(parent::getEloquentQuery(), 'usage_events.app_id');
    }

    protected static ?string $model = UsageEvent::class;

    protected static ?string $slug = 'nhat-ky';

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Nhật ký';

    protected static ?string $modelLabel = 'lượt gọi';

    protected static ?string $pluralModelLabel = 'nhật ký yêu cầu';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['app', 'account']))
            ->columns(UsageEventTable::columns())
            ->filters([
                Tables\Filters\SelectFilter::make('app_id')->label('Ứng dụng')->relationship('app', 'name'),
                Tables\Filters\SelectFilter::make('account_id')->label('Tài khoản')->relationship('account', 'label'),
                Tables\Filters\SelectFilter::make('capability')->label('Khả năng')->options(['text' => 'Văn bản', 'image_in' => 'Phân tích ảnh', 'image_out' => 'Sinh ảnh', 'web_search' => 'Tìm web']),
                Tables\Filters\SelectFilter::make('status')->label('Kết quả')->options(collect(UsageStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])->all()),
                Tables\Filters\Filter::make('ts')->label('Khoảng thời gian')->form([
                    DatePicker::make('from')->label('Từ ngày')->native(false)->displayFormat('d/m/Y'),
                    DatePicker::make('until')->label('Đến ngày')->native(false)->displayFormat('d/m/Y'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $v) => $q->where('ts', '>=', $v))
                    ->when($data['until'] ?? null, fn ($q, $v) => $q->where('ts', '<=', $v . ' 23:59:59'))),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->defaultSort('ts', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateHeading('Chưa có yêu cầu nào')
            ->emptyStateDescription('Nhật ký chỉ ghi số liệu của từng lượt gọi. Nội dung prompt và phản hồi không được lưu.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUsageEvents::route('/')];
    }
}
