<?php

namespace App\Services\Gateway;

use App\Models\Setting;

/** Quy tắc nhắc đăng nhập lại: mốc nhắc và hạn ước tính theo hãng. Chỉnh được ở Hệ thống → Cài đặt. */
class LoginPolicy
{
    public const DEFAULT_REMIND_DAYS = [14, 7, 3, 1];

    /** Hạn ước tính (ngày) CHỈ cho hãng mà CLI không cho biết hạn. Grok: tài liệu CLI ghi token hết hạn sau 7 ngày (chưa biết có tự làm mới không). */
    public const DEFAULT_LIFETIME_DAYS = ['grok' => 7];

    /** @return list<int> các mốc nhắc, giảm dần */
    public static function remindDays(): array
    {
        $v = Setting::read('login.remind_days', self::DEFAULT_REMIND_DAYS);
        $v = is_array($v) ? array_values(array_unique(array_filter(array_map('intval', $v), fn ($d) => $d > 0))) : self::DEFAULT_REMIND_DAYS;
        rsort($v);

        return $v ?: self::DEFAULT_REMIND_DAYS;
    }

    public static function lifetimeDays(string $providerCode): ?int
    {
        $m = Setting::read('login.lifetime_days', self::DEFAULT_LIFETIME_DAYS);
        $d = is_array($m) ? ($m[$providerCode] ?? null) : null;

        return is_numeric($d) && (int) $d > 0 ? (int) $d : null;
    }

    /** unknown | expired | critical | warning | ok */
    public static function level(?int $daysLeft): string
    {
        if ($daysLeft === null) {
            return 'unknown';
        }
        if ($daysLeft <= 0) {
            return 'expired';
        }
        if ($daysLeft <= 3) {
            return 'critical';
        }

        return $daysLeft <= max(self::remindDays()) ? 'warning' : 'ok';
    }
}
