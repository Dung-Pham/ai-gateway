<?php

use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Làm mới dữ liệu mẫu theo giờ hiện tại (giữ nguyên người dùng đăng nhập). Chỉ chạy khi GATEWAY_DEMO=true.
Artisan::command('gateway:demo-reset', function () {
    if (! config('gateway.demo')) {
        $this->error('GATEWAY_DEMO chưa bật: từ chối xóa dữ liệu.');

        return 1;
    }
    foreach (['audit_logs', 'alerts', 'usage_events', 'model_weights', 'app_limits', 'app_policies', 'app_keys', 'apps', 'capabilities', 'pool_members', 'pools', 'accounts', 'providers'] as $table) {
        DB::table($table)->delete();
    }
    (new DemoDataSeeder)->run();
    // Gán lại ứng dụng cho chủ ứng dụng mẫu (bảng liên kết bị xóa cùng ứng dụng).
    \App\Models\User::where('email', 'owner@gateway.test')->first()?->apps()->sync(\App\Models\ClientApp::where('slug', 'seo-studio')->pluck('id'));
    $this->info('Đã nạp lại dữ liệu mẫu theo thời điểm hiện tại.');
})->purpose('Nạp lại dữ liệu mẫu giả (chỉ dùng khi dựng khung giao diện)');
