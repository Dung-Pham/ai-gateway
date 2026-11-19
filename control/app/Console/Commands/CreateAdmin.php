<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Tạo quản trị viên đầu tiên trên server. Mật khẩu nhập tại chỗ (không qua tham số dòng lệnh, không vào lịch sử shell). */
class CreateAdmin extends Command
{
    protected $signature = 'gateway:create-admin {email : địa chỉ email đăng nhập} {--name=Quản trị viên} {--generate : tự sinh mật khẩu ngẫu nhiên và in ra MỘT lần}';

    protected $description = 'Tạo (hoặc đặt lại mật khẩu) một quản trị viên';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email không hợp lệ.');

            return self::FAILURE;
        }
        if ($this->option('generate')) {
            $password = Str::password(20, symbols: false);
            $shown = true;
        } else {
            $password = (string) $this->secret('Mật khẩu (tối thiểu 12 ký tự)');
            if ($password !== (string) $this->secret('Nhập lại mật khẩu')) {
                $this->error('Hai lần nhập không khớp.');

                return self::FAILURE;
            }
            $shown = false;
        }
        if (mb_strlen($password) < 12) {
            $this->error('Mật khẩu phải có ít nhất 12 ký tự.');

            return self::FAILURE;
        }
        $existing = User::where('email', $email)->first();
        $u = User::updateOrCreate(['email' => $email], ['name' => $existing?->name ?? $this->option('name'), 'password' => $password, 'role' => Role::Admin->value]);
        $this->info(($existing ? 'Đã đặt lại mật khẩu cho ' : 'Đã tạo quản trị viên ').$u->email.'.');
        if ($shown) {
            $this->line("Mật khẩu (chỉ hiện một lần, hãy đổi ngay sau khi đăng nhập): {$password}");
        }

        return self::SUCCESS;
    }
}
