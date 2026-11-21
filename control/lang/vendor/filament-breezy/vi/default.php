<?php

/**
 * Bổ sung các câu tiếng Việt còn thiếu của gói filament-breezy (gói chỉ có 85/105 khóa nên hiện chữ thô như
 * "filament-breezy::default.profile.2fa.must_enable"). Laravel gộp tệp này vào bản dịch của gói.
 */
return [
    'profile' => [
        'subheading' => 'Quản lý hồ sơ người dùng của bạn tại đây.',
        'password' => [
            'subheading' => 'Tối thiểu 12 ký tự.',   // khớp chính sách mật khẩu trong AdminPanelProvider (gói ghi sai là 8)
            'notify' => 'Đã cập nhật mật khẩu.',
        ],
        '2fa' => [
            'must_enable' => 'Bạn phải bật xác thực hai yếu tố mới dùng được ứng dụng này.',
            'enabled' => ['notify' => 'Đã bật xác thực hai yếu tố.'],
            'disabling' => ['notify' => 'Đã tắt xác thực hai yếu tố.'],
            'regenerate_codes' => ['notify' => 'Đã tạo mã khôi phục mới. Hãy lưu lại ở nơi an toàn.'],
        ],
        'sanctum' => [
            'create' => ['message' => 'Mã truy cập chỉ hiện một lần lúc tạo. Nếu mất, bạn phải xóa và tạo mã mới.'],
            'update' => ['submit' => ['label' => 'Cập nhật']],
            'copied' => ['label' => 'Tôi đã sao chép mã'],
        ],
        'browser_sessions' => [
            'heading' => 'Phiên trình duyệt',
            'subheading' => 'Quản lý các phiên đăng nhập đang hoạt động.',
            'label' => 'Phiên trình duyệt',
            'content' => 'Nếu cần, bạn có thể đăng xuất mọi phiên trình duyệt khác trên tất cả thiết bị. Một số phiên gần đây được liệt kê bên dưới (danh sách có thể chưa đầy đủ). Nếu nghi ngờ tài khoản bị xâm nhập, hãy đổi mật khẩu.',
            'device' => 'Thiết bị này',
            'last_active' => 'Hoạt động gần nhất',
            'logout_other_sessions' => 'Đăng xuất các phiên trình duyệt khác',
            'logout_heading' => 'Đăng xuất các phiên trình duyệt khác',
            'logout_description' => 'Nhập mật khẩu để xác nhận bạn muốn đăng xuất các phiên trình duyệt khác trên mọi thiết bị.',
            'logout_action' => 'Đăng xuất các phiên trình duyệt khác',
            'incorrect_password' => 'Mật khẩu không đúng. Hãy thử lại.',
            'logout_success' => 'Đã đăng xuất mọi phiên trình duyệt khác.',
        ],
    ],
    'fields' => [
        'avatar' => 'Ảnh đại diện',
        'token_expiry' => 'Hạn của mã',
    ],
];
