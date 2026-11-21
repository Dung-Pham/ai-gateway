<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Mở khóa khi một người mất điện thoại và mất cả mã khôi phục. Chạy TRÊN SERVER bởi người vận hành có quyền (không có trong giao diện web),
 * nên kẻ chiếm được một tài khoản quản trị qua web cũng không tắt được 2FA của người khác.
 */
class DisableTwoFactor extends Command
{
    protected $signature = 'gateway:disable-2fa {email : email của người dùng cần đặt lại 2FA} {--yes : bỏ qua câu hỏi xác nhận}';

    protected $description = 'Tắt 2FA của một người dùng (người đó sẽ phải bật lại khi đăng nhập nếu REQUIRE_2FA=true)';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $u = User::where('email', $email)->first();
        if (! $u) {
            $this->error('Không có người dùng này.');

            return self::FAILURE;
        }
        if (! $u->two_factor_secret && ! $u->two_factor_confirmed_at) {
            $this->info('Người dùng này chưa bật 2FA: không có gì để tắt.');

            return self::SUCCESS;
        }
        if (! $this->option('yes') && ! $this->confirm("Tắt 2FA của {$u->email}? Hãy chắc chắn đã xác minh danh tính người này (gọi điện, gặp trực tiếp).")) {
            $this->line('Đã hủy.');

            return self::FAILURE;
        }
        $u->disableTwoFactorAuthentication();
        $u->breezySessions()->delete();                        // buộc xác thực lại
        AuditLog::create(['actor' => 'máy chủ (artisan)', 'action' => 'user.2fa_reset', 'subject' => $u->email, 'details' => null, 'ip' => null]);
        $this->info("Đã tắt 2FA của {$u->email}. Người này đăng nhập bằng mật khẩu rồi bật lại 2FA ở Hồ sơ của tôi.");

        return self::SUCCESS;
    }
}
