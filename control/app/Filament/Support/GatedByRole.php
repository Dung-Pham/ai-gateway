<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Gắn quyền theo vai trò cho Resource. Mặc định từ chối: muốn tạo/sửa phải khai báo manageAbility().
 * Không Resource nào xóa bản ghi qua trait này (ghi đè riêng nếu thật sự cần).
 */
trait GatedByRole
{
    abstract protected static function viewAbility(): string;

    protected static function manageAbility(): ?string
    {
        return null;
    }

    public static function canViewAny(): bool
    {
        return Perm::can(static::viewAbility());
    }

    public static function canView(Model $record): bool
    {
        return Perm::can(static::viewAbility());
    }

    public static function canCreate(): bool
    {
        $ability = static::manageAbility();

        return $ability !== null && Perm::can($ability);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
