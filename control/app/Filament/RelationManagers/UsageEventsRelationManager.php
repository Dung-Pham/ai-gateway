<?php

namespace App\Filament\RelationManagers;

use App\Filament\Support\UsageEventTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Dùng chung cho Tài khoản và Ứng dụng (cả hai đều có quan hệ `usageEvents`). Chỉ xem. */
class UsageEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'usageEvents';

    protected static ?string $title = 'Nhật ký yêu cầu gần đây';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Nhật ký yêu cầu';
    }

    public function table(Table $table): Table
    {
        $isAccount = $this->getOwnerRecord() instanceof \App\Models\Account;

        return $table
            ->recordTitleAttribute('request_id')
            ->columns(UsageEventTable::columns(showApp: $isAccount, showAccount: ! $isAccount))
            ->defaultSort('ts', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Chưa có yêu cầu nào')
            ->emptyStateDescription('Khi gateway nhận yêu cầu, từng lượt sẽ hiện ở đây (chỉ số liệu, không có nội dung).');
    }
}
