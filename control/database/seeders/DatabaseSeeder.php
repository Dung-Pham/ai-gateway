<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Tài khoản đăng nhập thử: lấy từ .env cục bộ (không có trong repo). Thiếu thì bỏ qua, dùng `php artisan make:filament-user`.
        $email = env('GATEWAY_DEMO_ADMIN_EMAIL');
        $password = env('GATEWAY_DEMO_ADMIN_PASSWORD');
        if ($email && $password) {
            User::updateOrCreate(['email' => $email], ['name' => 'Quản trị thử', 'password' => Hash::make($password)]);
        }

        if (config('gateway.demo')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
