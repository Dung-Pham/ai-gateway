<?php

namespace Tests\Feature;

use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo;
use Jeffgreco13\FilamentBreezy\Livewire\TwoFactorAuthentication;
use Jeffgreco13\FilamentBreezy\Livewire\UpdatePassword;
use Jeffgreco13\FilamentBreezy\Pages\TwoFactorPage;
use Livewire\Mechanisms\ComponentRegistry;
use Tests\TestCase;

/**
 * Lỗi thật gặp ở server 21/11/2026: bấm "Bật" 2FA trả 419. Livewire tìm thành phần theo tên TRƯỚC khi giao diện quản trị khởi động,
 * mà filament-breezy chỉ đăng ký chúng lúc khởi động. Test này chạy KHÔNG có yêu cầu HTTP nào trước, giống một yêu cầu /livewire/update thật.
 */
class BreezyComponentsRegisteredTest extends TestCase
{
    public function test_cac_thanh_phan_ho_so_va_2fa_tim_thay_ngay_ca_khi_giao_dien_chua_khoi_dong(): void
    {
        $registry = app(ComponentRegistry::class);
        $this->assertSame(TwoFactorAuthentication::class, $registry->getClass('two_factor_authentication'));
        $this->assertSame(PersonalInfo::class, $registry->getClass('personal_info'));
        $this->assertSame(UpdatePassword::class, $registry->getClass('update_password'));
        $this->assertSame(TwoFactorPage::class, $registry->getClass('two-factor-page'));
    }
}
