<?php

namespace App\Services\Gateway;

use App\Models\Account;

/** Hướng dẫn đăng nhập từng hãng. Giao diện KHÔNG nhận mật khẩu, mã hay token: người sở hữu đăng nhập trong terminal trên máy AI. */
class LoginGuide
{
    public static function command(Account $a): string
    {
        $dir = (string) config('gateway.install_dir', '/opt/aigw/ai-gateway');

        return 'cd '.$dir.' && scripts/aigw login '.($a->provider?->code ?? 'claude').' '.$a->label;
    }

    /** @return list<string> */
    public static function steps(Account $a): array
    {
        $code = $a->provider?->code ?? 'claude';
        $enter = match ($code) {
            'grok', 'codex' => 'CLI in một đường dẫn và một MÃ THIẾT BỊ. Mở đường dẫn trên trình duyệt ở máy của bạn, đăng nhập, rồi nhập mã thiết bị vào trang đó (không phải dán mã ngược lại vào terminal).',
            'agy' => 'Chọn "Google OAuth". CLI in một đường dẫn: mở trên trình duyệt ở máy của bạn, đăng nhập, rồi dán mã vào terminal. Đăng nhập xong, thoát CLI (Ctrl+C hoặc /quit) để token được ghi.',
            default => 'CLI in một đường dẫn đăng nhập. Mở đường dẫn trên trình duyệt ở máy của bạn, đăng nhập (có 2FA), rồi sao chép mã hiện ra và dán vào terminal.',
        };

        return [
            'Dùng tài khoản AI RIÊNG cho Cổng AI (không dùng tài khoản lập trình hằng ngày của bạn).',
            'SSH vào máy AI có terminal: ssh -t <người-dùng>@<máy-AI> (cờ -t bắt buộc). Nên chạy trong tmux để đường dẫn dài không bị rớt.',
            'Chạy lệnh ở khung bên dưới.',
            $enter,
            'Khi thấy "ĐÃ LƯU token", quay lại đây bấm "Tôi đã đăng nhập xong: kiểm tra".',
        ];
    }
}
