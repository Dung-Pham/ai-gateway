<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\TextColumn;

class UsageEventTable
{
    public static function columns(bool $showApp = true, bool $showAccount = true): array
    {
        return array_values(array_filter([
            TextColumn::make('ts')->label('Thời gian')->dateTime('d/m H:i:s')->fontFamily('mono')->sortable(),
            $showApp ? TextColumn::make('app.name')->label('Ứng dụng')->searchable()->limit(28) : null,
            $showAccount ? TextColumn::make('account.label')->label('Tài khoản')->fontFamily('mono')->searchable() : null,
            TextColumn::make('capability')->label('Khả năng')->badge()->color('gray'),
            TextColumn::make('model')->label('Model')->fontFamily('mono'),
            TextColumn::make('weighted_units')->label('Đơn vị')->numeric(0, ',', '.')->fontFamily('mono')->alignEnd()->sortable()
                ->tooltip(fn ($record) => $record->estimated ? 'Số ước lượng (CLI không báo token)' : 'Số đếm từ kết quả của CLI'),
            TextColumn::make('latency_ms')->label('Độ trễ')->formatStateUsing(fn ($state) => number_format($state / 1000, 1, ',', '.') . ' s')->fontFamily('mono')->alignEnd()->sortable(),
            TextColumn::make('status')->label('Kết quả')->badge(),
            TextColumn::make('request_id')->label('Mã yêu cầu')->fontFamily('mono')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true),
        ]));
    }
}
