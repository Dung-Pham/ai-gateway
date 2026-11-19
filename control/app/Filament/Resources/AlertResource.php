<?php

namespace App\Filament\Resources;

use App\Enums\Severity;
use App\Filament\Resources\AlertResource\Pages;
use App\Filament\Support\Audit;
use App\Models\Alert;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AlertResource extends Resource
{
    protected static ?string $model = Alert::class;

    protected static ?string $slug = 'canh-bao';

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Cảnh báo';

    protected static ?string $modelLabel = 'cảnh báo';

    protected static ?string $pluralModelLabel = 'cảnh báo';

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Alert::open()->whereNull('acknowledged_at')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return Alert::open()->whereNull('acknowledged_at')->where('severity', 'critical')->exists() ? 'danger' : 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('severity')->label('Mức độ')->badge()->sortable(),
                TextColumn::make('title')->label('Cảnh báo')->weight('medium')->description(fn (Alert $r) => $r->body)->wrap()->searchable(),
                TextColumn::make('state')->label('Tình trạng')->badge()
                    ->state(fn (Alert $r) => $r->resolved_at ? 'Đã giải quyết' : ($r->acknowledged_at ? 'Đã xác nhận' : 'Mới'))
                    ->color(fn ($state) => match ($state) { 'Mới' => 'danger', 'Đã xác nhận' => 'warning', default => 'gray' }),
                TextColumn::make('opened_at')->label('Mở lúc')->since()->sortable()->tooltip(fn (Alert $r) => $r->opened_at->format('d/m/Y H:i')),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('open')->label('Hiển thị')->placeholder('Tất cả')->trueLabel('Đang mở')->falseLabel('Đã giải quyết')->default(true)
                    ->queries(true: fn (Builder $q) => $q->whereNull('resolved_at'), false: fn (Builder $q) => $q->whereNotNull('resolved_at'), blank: fn (Builder $q) => $q),
                Tables\Filters\SelectFilter::make('severity')->label('Mức độ')->options(collect(Severity::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('ack')->label('Đã biết')->icon('heroicon-m-eye')->color('gray')
                    ->visible(fn (Alert $r) => ! $r->acknowledged_at && ! $r->resolved_at)
                    ->action(function (Alert $r) {
                        $r->update(['acknowledged_at' => now()]);
                        Audit::log('alert.ack', $r->title);
                        Notification::make()->title('Đã xác nhận cảnh báo')->success()->send();
                    }),
                Tables\Actions\Action::make('resolve')->label('Đã giải quyết')->icon('heroicon-m-check')->color('success')
                    ->visible(fn (Alert $r) => ! $r->resolved_at)
                    ->requiresConfirmation()->modalHeading('Đánh dấu đã giải quyết?')->modalDescription('Cảnh báo sẽ chuyển sang lịch sử. Nếu sự cố còn, hệ thống sẽ mở cảnh báo mới.')
                    ->action(function (Alert $r) {
                        $r->update(['resolved_at' => now(), 'acknowledged_at' => $r->acknowledged_at ?? now()]);
                        Audit::log('alert.resolve', $r->title);
                        Notification::make()->title('Đã đóng cảnh báo')->success()->send();
                    }),
            ])
            ->defaultSort('opened_at', 'desc')
            ->striped()
            ->poll('30s')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('Không có cảnh báo nào')
            ->emptyStateDescription('Mọi thứ đang ổn. Cảnh báo mới sẽ hiện ở đây và ở dải trạng thái trên cùng.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAlerts::route('/')];
    }
}
