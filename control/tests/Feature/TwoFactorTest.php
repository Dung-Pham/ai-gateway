<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jeffgreco13\FilamentBreezy\Pages\TwoFactorPage;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Xác nhận 2FA (TOTP) hoạt động và tương thích Google Authenticator: mã 6 số, đổi mỗi 30 giây, khóa Base32, chuẩn RFC 6238. */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function userWith2fa(): User
    {
        $u = User::create(['name' => 'a', 'email' => 'a@t.test', 'password' => 'mat-khau-thu-nghiem-12', 'role' => Role::Admin->value]);
        $u->enableTwoFactorAuthentication();
        $u->confirmTwoFactorAuthentication();
        $this->actingAs($u->refresh());

        return $u->refresh();
    }

    /** Breezy lưu khóa đã mã hóa; giải mã để tạo mã TOTP như ứng dụng Authenticator làm. */
    private function secret(User $u): string
    {
        $v = (string) $u->two_factor_secret;

        return str_starts_with($v, 'eyJ') ? decrypt($v) : $v;
    }

    public function test_khoa_va_ma_dung_chuan_google_authenticator(): void
    {
        $u = $this->userWith2fa();
        $secret = $this->secret($u);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{16,}$/', $secret, 'khóa phải là Base32');
        $otp = (new Google2FA)->getCurrentOtp($secret);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
        $qr = $u->getTwoFactorQrCodeUrl();
        $this->assertStringContainsString('otpauth://totp/', urldecode($qr));
    }

    public function test_ham_xac_minh_cua_trang_2fa_nhan_ma_dung_va_tu_choi_ma_sai(): void
    {
        // Đây chính là hàm TwoFactorPage gọi khi người dùng nhập mã. Chưa kiểm luồng nhập mã trên trình duyệt thật.
        $u = $this->userWith2fa();
        $otp = (new Google2FA)->getCurrentOtp($this->secret($u));
        $wrong = $otp === '000000' ? '111111' : '000000';
        $this->assertFalse(filament('filament-breezy')->verify(code: $wrong));
        $this->assertTrue(filament('filament-breezy')->verify(code: $otp));
    }

    public function test_co_8_ma_khoi_phuc(): void
    {
        $u = $this->userWith2fa();
        $this->assertCount(8, $u->two_factor_recovery_codes);
    }
}
