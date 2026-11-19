<?php

return [
    // Khi bật, giao diện hiện nhãn "Dữ liệu mẫu" ở dải trạng thái. Chỉ dùng trong giai đoạn dựng khung.
    'demo' => (bool) env('GATEWAY_DEMO', false),

    // Múi giờ hiển thị số liệu theo ngày (đầu ngày, biểu đồ 24 giờ).
    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),
];
