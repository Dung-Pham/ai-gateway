<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\ClientApp;
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
            User::updateOrCreate(['email' => $email], ['name' => 'Quản trị thử', 'password' => Hash::make($password), 'role' => Role::Admin->value]);
        }

        if (config('gateway.demo')) {
            $this->call(DemoDataSeeder::class);
            // Người dùng mẫu theo từng vai trò, dùng chung mật khẩu thử trong .env (chỉ ở chế độ mẫu cục bộ).
            if ($password) {
                foreach ([['operator@gateway.test', 'Vận hành thử', Role::Operator], ['viewer@gateway.test', 'Người xem thử', Role::Viewer], ['owner@gateway.test', 'Chủ ứng dụng thử', Role::AppOwner]] as [$mail, $name, $role]) {
                    $u = User::updateOrCreate(['email' => $mail], ['name' => $name, 'password' => Hash::make($password), 'role' => $role->value]);
                    if ($role === Role::AppOwner) {
                        $u->apps()->sync(ClientApp::where('slug', 'seo-studio')->pluck('id'));
                    }
                }
            }
        }
    }
}
