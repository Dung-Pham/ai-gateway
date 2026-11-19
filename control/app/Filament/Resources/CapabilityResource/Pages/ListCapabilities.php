<?php

namespace App\Filament\Resources\CapabilityResource\Pages;

use App\Filament\Resources\CapabilityResource;
use Filament\Resources\Pages\ListRecords;

class ListCapabilities extends ListRecords
{
    protected static string $resource = CapabilityResource::class;

    protected static ?string $title = 'Danh mục khả năng';

    public function getSubheading(): ?string
    {
        return 'Ứng dụng chỉ dùng được khả năng nào mà bạn cấp riêng cho nó. Mặc định không có gì.';
    }
}
