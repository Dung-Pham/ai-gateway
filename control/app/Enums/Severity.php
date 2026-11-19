<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Severity: string implements HasLabel, HasColor, HasIcon
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::Info => 'Thông tin',
            self::Warning => 'Chú ý',
            self::Critical => 'Nghiêm trọng',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Warning => 'warning',
            self::Critical => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Info => 'heroicon-m-information-circle',
            self::Warning => 'heroicon-m-exclamation-triangle',
            self::Critical => 'heroicon-m-x-circle',
        };
    }
}
