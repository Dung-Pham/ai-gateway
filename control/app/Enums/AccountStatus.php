<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AccountStatus: string implements HasLabel, HasColor, HasIcon
{
    case Active = 'active';
    case Paused = 'paused';
    case Cooldown = 'cooldown';
    case AuthExpired = 'auth_expired';
    case Draining = 'draining';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Sẵn sàng',
            self::Paused => 'Tạm dừng',
            self::Cooldown => 'Đang nghỉ',
            self::AuthExpired => 'Hết phiên',
            self::Draining => 'Rút dần',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'gray',
            self::Cooldown => 'warning',
            self::AuthExpired => 'danger',
            self::Draining => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-m-check-circle',
            self::Paused => 'heroicon-m-pause-circle',
            self::Cooldown => 'heroicon-m-clock',
            self::AuthExpired => 'heroicon-m-x-circle',
            self::Draining => 'heroicon-m-arrow-down-circle',
        };
    }
}
