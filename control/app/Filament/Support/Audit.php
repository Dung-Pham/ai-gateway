<?php

namespace App\Filament\Support;

use App\Models\AuditLog;

class Audit
{
    /** Ghi nhật ký kiểm toán cho mọi thao tác quản trị (không ghi nội dung nhạy cảm). */
    public static function log(string $action, ?string $subject = null, array $details = []): void
    {
        AuditLog::create([
            'actor' => auth()->user()?->email ?? 'hệ thống',
            'action' => $action,
            'subject' => $subject,
            'details' => $details ?: null,
            'ip' => request()->ip(),
        ]);
    }
}
