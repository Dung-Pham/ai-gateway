<?php

namespace App\Services\Gateway;

use App\Models\Account;
use App\Models\Alert;

/** Mở/cập nhật/đóng cảnh báo "đăng nhập sắp hết hạn" cho một tài khoản. Một cảnh báo mở cho mỗi tài khoản (không sinh trùng theo mốc). */
class LoginReminder
{
    public const TYPE = 'account_login_expiring';

    public static function evaluate(Account $a): void
    {
        $days = $a->login_days_left;
        $open = Alert::open()->where('type', self::TYPE)->where('subject_id', $a->id)->first();
        if ($days === null || $days > max(LoginPolicy::remindDays())) {
            $open?->update(['resolved_at' => now()]);

            return;
        }
        $severity = $days <= 3 ? 'critical' : 'warning';
        $title = $days <= 0 ? "{$a->label}: đăng nhập đã hết hạn" : "{$a->label}: đăng nhập hết hạn sau {$days} ngày";
        $body = 'Người sở hữu tài khoản cần đăng nhập lại trước hạn ('.$a->login_expires_at->setTimezone(config('gateway.timezone'))->format('d/m/Y').', '.$a->login_source_label.'). Mở trang tài khoản → "Đăng nhập lại" để xem các bước.';
        if ($open) {
            if ($open->title !== $title || (string) ($open->severity->value ?? $open->severity) !== $severity) {
                $open->update(['title' => $title, 'severity' => $severity, 'body' => $body, 'acknowledged_at' => null]);   // mức đổi: báo lại
            }

            return;
        }
        Alert::create(['severity' => $severity, 'type' => self::TYPE, 'title' => $title, 'body' => $body,
            'subject_type' => 'account', 'subject_id' => $a->id, 'opened_at' => now()]);
    }
}
