<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UsageStatus: string implements HasLabel, HasColor, HasIcon
{
    case Ok = 'ok';
    case RateLimited = 'rate_limited';
    case Error = 'error';
    case Denied = 'denied';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ok => 'Thành công',
            self::RateLimited => 'Chạm giới hạn',
            self::Error => 'Lỗi',
            self::Denied => 'Bị từ chối',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::RateLimited => 'warning',
            self::Error => 'danger',
            self::Denied => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Ok => 'heroicon-m-check-circle',
            self::RateLimited => 'heroicon-m-clock',
            self::Error => 'heroicon-m-x-circle',
            self::Denied => 'heroicon-m-no-symbol',
        };
    }
}
