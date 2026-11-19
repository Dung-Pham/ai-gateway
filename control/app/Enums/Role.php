<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Vai trò quản trị. Ma trận quyền nằm ở một chỗ duy nhất (ABILITIES) để dễ rà soát.
 * Mặc định từ chối: khả năng không có trong danh sách của vai trò thì không được làm.
 */
enum Role: string implements HasLabel, HasColor
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';
    case AppOwner = 'app_owner';

    private const ABILITIES = [
        'admin' => ['*'],
        'operator' => [
            'dashboard', 'accounts.view', 'accounts.manage', 'apps.view', 'apps.manage', 'pools.view', 'pools.manage',
            'logs.view', 'alerts.view', 'alerts.manage', 'capabilities.view', 'weights.view', 'emergency',
        ],
        'viewer' => ['dashboard', 'accounts.view', 'apps.view', 'pools.view', 'logs.view', 'alerts.view', 'capabilities.view', 'weights.view'],
        // Chỉ thấy ứng dụng và nhật ký của chính mình (lọc theo bảng app_user ở tầng truy vấn).
        'app_owner' => ['apps.view', 'logs.view'],
    ];

    public function can(string $ability): bool
    {
        $list = self::ABILITIES[$this->value];

        return in_array('*', $list, true) || in_array($ability, $list, true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị viên',
            self::Operator => 'Vận hành',
            self::Viewer => 'Chỉ xem',
            self::AppOwner => 'Chủ ứng dụng',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'primary',
            self::Operator => 'info',
            self::Viewer => 'gray',
            self::AppOwner => 'warning',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Toàn quyền, kể cả người dùng, cài đặt, hệ số model và kiểm toán.',
            self::Operator => 'Quản lý tài khoản AI, ứng dụng, pool, cảnh báo và dừng khẩn cấp. Không quản lý người dùng, cài đặt, kiểm toán.',
            self::Viewer => 'Chỉ xem bảng điều khiển, tài khoản, ứng dụng, nhật ký. Không thao tác được.',
            self::AppOwner => 'Chỉ xem mức dùng, khóa (tiền tố) và nhật ký của ứng dụng được gán. Không thấy tài khoản AI hay ứng dụng khác.',
        };
    }
}
