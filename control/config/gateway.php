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

    // Nối với gateway (tiến trình FastAPI). Giao diện chỉ gọi các đường /internal/* bằng mã quản trị; không bao giờ đi qua mạng công cộng.
    'url' => rtrim((string) env('GATEWAY_URL', 'http://gateway:8000'), '/'),
    // Thư mục cài đặt trên máy AI, chỉ để hiện đúng lệnh `scripts/aigw login ...` trong hướng dẫn đăng nhập.
    'install_dir' => env('AIGW_INSTALL_DIR', '/opt/aigw/ai-gateway'),
    // Mã quản trị: ưu tiên tệp (Docker secret), nếu không có thì đọc biến GATEWAY_ADMIN_TOKEN.
    'admin_token_file' => env('GATEWAY_ADMIN_TOKEN_FILE'),
    'admin_token' => env('GATEWAY_ADMIN_TOKEN'),
    'timeout' => (int) env('GATEWAY_TIMEOUT', 5),
    // "Kiểm tra ngay" gọi một lượt thật qua runner nên có thể chậm hơn.
    'check_timeout' => (int) env('GATEWAY_CHECK_TIMEOUT', 90),
];
