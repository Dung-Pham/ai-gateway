<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $slug = 'he-thong/kiem-toan';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Hệ thống';

    protected static ?string $navigationLabel = 'Kiểm toán';

    protected static ?string $modelLabel = 'bản ghi kiểm toán';

    protected static ?string $pluralModelLabel = 'nhật ký kiểm toán';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Thời gian')->dateTime('d/m/Y H:i:s')->fontFamily('mono')->sortable(),
                TextColumn::make('actor')->label('Người thực hiện')->searchable(),
                TextColumn::make('action')->label('Hành động')->badge()->color('gray')->fontFamily('mono')->searchable(),
                TextColumn::make('subject')->label('Đối tượng')->searchable(),
                TextColumn::make('details')->label('Chi tiết')->state(fn (AuditLog $record) => $record->details ? collect($record->details)->map(fn ($v, $k) => "{$k}: " . (is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE)))->implode(' · ') : '—')->wrap()->color('gray'),
                TextColumn::make('ip')->label('IP')->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('Chưa có thao tác nào')
            ->emptyStateDescription('Mọi thay đổi trong giao diện (tạo khóa, tạm ngưng, bật khả năng…) sẽ được ghi lại tại đây và không xóa được.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}
