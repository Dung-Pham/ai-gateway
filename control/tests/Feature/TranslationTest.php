<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Giao diện chỉ có tiếng Việt: không được hiện khóa dịch thô như "filament-breezy::default.profile.subheading". */
class TranslationTest extends TestCase
{
    public function test_moi_khoa_cua_filament_breezy_co_ban_dich_tieng_viet(): void
    {
        app()->setLocale('vi');
        $en = require base_path('vendor/jeffgreco13/filament-breezy/resources/lang/en/default.php');
        $flat = function (array $a, string $p = '') use (&$flat): array {
            $o = [];
            foreach ($a as $k => $v) {
                $n = $p === '' ? $k : "$p.$k";
                $o = is_array($v) ? $o + $flat($v, $n) : $o + [$n => $v];
            }

            return $o;
        };
        $thieu = [];
        foreach ($flat($en) as $key => $english) {
            $vi = __("filament-breezy::default.$key", [], 'vi');
            if ($vi === "filament-breezy::default.$key" || $vi === $english) {
                $thieu[] = $key;
            }
        }
        // Một số chuỗi giống nhau ở mọi ngôn ngữ (ví dụ "Email", "OK") nên chỉ báo khóa HOÀN TOÀN thiếu.
        $thoNhat = array_filter($thieu, fn ($k) => __("filament-breezy::default.$k", [], 'vi') === "filament-breezy::default.$k");
        $this->assertSame([], array_values($thoNhat), 'Khóa dịch còn hiện chữ thô: '.implode(', ', $thoNhat));
    }

    public function test_hai_cau_hien_tren_trang_bat_2fa(): void
    {
        app()->setLocale('vi');
        $this->assertSame('Bạn phải bật xác thực hai yếu tố mới dùng được ứng dụng này.', __('filament-breezy::default.profile.2fa.must_enable'));
        $this->assertSame('Quản lý hồ sơ người dùng của bạn tại đây.', __('filament-breezy::default.profile.subheading'));
    }
}
