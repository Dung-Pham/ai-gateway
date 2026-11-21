<?php

namespace App\Livewire;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Jeffgreco13\FilamentBreezy\Livewire\UpdatePassword;

/**
 * Đổi mật khẩu ở Hồ sơ mà KHÔNG đổi mã phiên.
 *
 * Bản gốc của filament-breezy gọi Filament::auth()->login() sau khi đổi mật khẩu; lệnh đó cấp lại mã phiên và huỷ mã cũ.
 * Trang Hồ sơ gửi nhiều yêu cầu Livewire song song, nên các yêu cầu còn lại (mang mã phiên cũ) nhận lỗi 419
 * "Phiên làm việc đã hết hạn" dù mật khẩu đã đổi thành công. Ở đây chỉ cập nhật mật khẩu và dấu băm lưu trong phiên
 * (để AuthenticateSession không đăng xuất chính người đang đổi), giữ nguyên mã phiên.
 */
class SafeUpdatePassword extends UpdatePassword
{
    public function submit()
    {
        $data = collect($this->form->getState())->only('new_password')->all();
        $this->user->update([
            'password' => Hash::make($data['new_password']),
        ]);
        session()->put('password_hash_'.Filament::getCurrentPanel()->getAuthGuard(), $this->user->getAuthPassword());
        $this->reset(['data']);
        Notification::make()
            ->success()
            ->title(__('filament-breezy::default.profile.password.notify'))
            ->send();
    }
}
