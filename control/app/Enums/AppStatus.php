<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AppStatus: string implements HasLabel, HasColor, HasIcon
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Hoạt động',
            self::Suspended => 'Tạm ngưng',
        };
    }

    public function getColor(): string
    {
        return $this === self::Active ? 'success' : 'gray';
    }

    public function getIcon(): string
    {
        return $this === self::Active ? 'heroicon-m-check-circle' : 'heroicon-m-pause-circle';
    }
}
