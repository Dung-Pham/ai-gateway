<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\SafeUpdatePassword;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lỗi thật gặp khi diễn tập 21/11/2026: đổi mật khẩu ở Hồ sơ thành công nhưng giao diện hiện "419 Phiên làm việc đã hết hạn",
 * vì bản gốc cấp lại mã phiên trong khi trang gửi nhiều yêu cầu Livewire song song. Bản thay thế phải giữ nguyên mã phiên.
 */
class SafeUpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_doi_mat_khau_thanh_cong_va_khong_doi_ma_phien(): void
    {
        $u = User::factory()->create(['role' => Role::Admin, 'password' => Hash::make('Mat-khau-cu-12345')]);
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $before = session()->getId();

        Livewire::test(SafeUpdatePassword::class)
            ->set('data.current_password', 'Mat-khau-cu-12345')
            ->set('data.new_password', 'Mat-khau-moi-67890')
            ->set('data.new_password_confirmation', 'Mat-khau-moi-67890')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('Mat-khau-moi-67890', $u->fresh()->password));
        $this->assertSame($before, session()->getId(), 'mã phiên không được đổi');
        $this->assertSame($u->fresh()->getAuthPassword(), session('password_hash_'.Filament::getCurrentPanel()->getAuthGuard()));
    }
}
