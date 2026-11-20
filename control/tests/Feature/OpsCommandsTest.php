<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Capability;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_baseline_idempotent_va_khong_ghi_de_sua_doi(): void
    {
        $this->artisan('gateway:seed-baseline')->assertExitCode(0);
        $this->assertSame(['agy', 'claude', 'codex', 'grok'], Provider::orderBy('code')->pluck('code')->all());
        $this->assertTrue(Capability::where('code', 'web_search')->first()->enabled);
        $this->assertFalse(Capability::where('code', 'image_out')->first()->enabled);
        Capability::where('code', 'image_in')->update(['enabled' => true, 'name' => 'Ảnh (đã sửa)']);
        $this->artisan('gateway:seed-baseline')->assertExitCode(0);
        $this->assertSame('Ảnh (đã sửa)', Capability::where('code', 'image_in')->first()->name);
        $this->assertSame(4, Capability::count());
    }

    public function test_tao_admin_voi_mat_khau_nhap_tai_cho(): void
    {
        $this->artisan('gateway:create-admin', ['email' => 'Boss@Example.test'])
            ->expectsQuestion('Mật khẩu (tối thiểu 12 ký tự)', 'mat-khau-rat-dai-123')
            ->expectsQuestion('Nhập lại mật khẩu', 'mat-khau-rat-dai-123')->assertExitCode(0);
        $u = User::where('email', 'boss@example.test')->firstOrFail();
        $this->assertSame(Role::Admin, $u->role);
        $this->assertNotSame('mat-khau-rat-dai-123', $u->password);
        $this->assertTrue(\Hash::check('mat-khau-rat-dai-123', $u->password));
    }

    public function test_tao_admin_tu_choi_mat_khau_ngan_hoac_khong_khop(): void
    {
        $this->artisan('gateway:create-admin', ['email' => 'a@b.test'])->expectsQuestion('Mật khẩu (tối thiểu 12 ký tự)', 'ngan')->expectsQuestion('Nhập lại mật khẩu', 'ngan')->assertExitCode(1);
        $this->artisan('gateway:create-admin', ['email' => 'a@b.test'])->expectsQuestion('Mật khẩu (tối thiểu 12 ký tự)', 'mat-khau-rat-dai-123')->expectsQuestion('Nhập lại mật khẩu', 'khac-hoan-toan-123')->assertExitCode(1);
        $this->artisan('gateway:create-admin', ['email' => 'khong-phai-email'])->assertExitCode(1);
        $this->assertSame(0, User::count());
    }

    public function test_generate_in_mot_lan_mat_khau_du_dai(): void
    {
        $this->artisan('gateway:create-admin', ['email' => 'x@y.test', '--generate' => true])->expectsOutputToContain('Mật khẩu (chỉ hiện một lần')->assertExitCode(0);
        $this->assertSame(1, User::count());
    }
}
