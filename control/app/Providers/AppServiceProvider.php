<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo;
use Jeffgreco13\FilamentBreezy\Livewire\TwoFactorAuthentication;
use Jeffgreco13\FilamentBreezy\Livewire\UpdatePassword;
use Jeffgreco13\FilamentBreezy\Pages\TwoFactorPage;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SỬA LỖI "419 Phiên làm việc đã hết hạn" khi bấm Bật 2FA / đổi mật khẩu ở Hồ sơ, và khi nhập mã 2FA lúc đăng nhập.
        // filament-breezy chỉ đăng ký các thành phần Livewire của nó khi giao diện quản trị KHỞI ĐỘNG. Livewire (có kiểm tra "release token")
        // tìm thành phần theo tên TRƯỚC bước đó, không thấy thì trả 419. Đăng ký sớm ở đây để luôn tìm thấy.
        Livewire::component('two_factor_authentication', TwoFactorAuthentication::class);
        Livewire::component('personal_info', PersonalInfo::class);
        Livewire::component('update_password', UpdatePassword::class);
        Livewire::component('two-factor-page', TwoFactorPage::class);
    }
}
