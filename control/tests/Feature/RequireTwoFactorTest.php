<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** REQUIRE_2FA=true: người dùng CHƯA bật 2FA không dùng được giao diện, phải bật trước. Đặt biến môi trường trước khi ứng dụng khởi động. */
class RequireTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        putenv('REQUIRE_2FA=true');
        $_ENV['REQUIRE_2FA'] = $_SERVER['REQUIRE_2FA'] = 'true';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('REQUIRE_2FA');
        unset($_ENV['REQUIRE_2FA'], $_SERVER['REQUIRE_2FA']);
    }

    private function user(bool $with2fa): User
    {
        $u = User::create(['name' => 'a', 'email' => 'a@t.test', 'password' => 'mat-khau-thu-nghiem-12', 'role' => Role::Admin->value]);
        if ($with2fa) {
            $u->enableTwoFactorAuthentication();
            $u->confirmTwoFactorAuthentication();
            $u->refresh()->setTwoFactorSession();
        }
        $this->actingAs($u->refresh());

        return $u;
    }

    public function test_cau_hinh_bat_buoc_duoc_nap(): void
    {
        $this->assertTrue((bool) config('gateway.require_2fa'));
    }

    public function test_chua_bat_2fa_thi_bi_chuyen_ve_trang_thiet_lap_khong_vao_duoc_trang_khac(): void
    {
        $this->user(false);
        foreach (['/', '/tai-khoan', '/ung-dung'] as $path) {
            $r = $this->get($path);
            $r->assertRedirect();
            $this->assertStringContainsString('/my-profile', (string) $r->headers->get('Location'), "{$path} phải chuyển tới trang hồ sơ để bật 2FA");
        }
    }

    public function test_da_bat_va_xac_thuc_2fa_thi_vao_binh_thuong(): void
    {
        $this->user(true);
        $this->get('/tai-khoan')->assertOk();
    }
}
