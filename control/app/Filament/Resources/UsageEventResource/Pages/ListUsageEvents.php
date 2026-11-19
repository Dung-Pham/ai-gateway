<?php

namespace App\Filament\Resources\UsageEventResource\Pages;

use App\Filament\Resources\UsageEventResource;
use Filament\Resources\Pages\ListRecords;

class ListUsageEvents extends ListRecords
{
    protected static string $resource = UsageEventResource::class;

    protected static ?string $title = 'Nhật ký yêu cầu';

    public function getSubheading(): ?string
    {
        return 'Chỉ có số liệu từng lượt gọi, không lưu nội dung prompt hay phản hồi.';
    }
}
