<?php

return [
    // Khi bật, giao diện hiện nhãn "Dữ liệu mẫu" ở dải trạng thái. Chỉ dùng trong giai đoạn dựng khung.
    'demo' => (bool) env('GATEWAY_DEMO', false),

    // Múi giờ hiển thị số liệu theo ngày (đầu ngày, biểu đồ 24 giờ).
    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),

    // Dải mạng được vào giao diện quản trị (CIDR, cách nhau bằng dấu phẩy). Rỗng ở production = khóa hết.
    'admin_allowed_cidrs' => array_filter(array_map('trim', explode(',', (string) env('ADMIN_ALLOWED_CIDRS', '')))),

    // Bắt buộc mọi người dùng bật 2FA (TOTP) mới dùng được giao diện. Mặc định tắt cho dev; BẬT ở production.
    'require_2fa' => (bool) env('REQUIRE_2FA', false),
];
