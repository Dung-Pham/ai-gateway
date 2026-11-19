<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Builder;

/** Điểm kiểm tra quyền duy nhất cho giao diện. Mặc định từ chối khi chưa đăng nhập. */
class Perm
{
    public static function can(string $ability): bool
    {
        return auth()->user()?->allows($ability) ?? false;
    }

    /** Chủ ứng dụng chỉ thấy ứng dụng được gán; vai trò khác thấy tất cả (null = không lọc). */
    public static function ownedAppIds(): ?array
    {
        $user = auth()->user();

        return $user && $user->isAppOwner() ? $user->apps()->pluck('apps.id')->all() : null;
    }

    public static function scopeToOwnedApps(Builder $query, string $column): Builder
    {
        $ids = static::ownedAppIds();

        return $ids === null ? $query : $query->whereIn($column, $ids ?: [0]);
    }
}
