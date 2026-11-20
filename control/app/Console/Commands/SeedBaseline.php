<?php

namespace App\Console\Commands;

use App\Models\Capability;
use App\Models\ModelWeight;
use App\Models\Provider;
use Illuminate\Console\Command;

/** Dữ liệu tham chiếu tối thiểu để dùng thật (khác DemoDataSeeder). Chạy lại nhiều lần an toàn: không ghi đè cấu hình người dùng đã sửa. */
class SeedBaseline extends Command
{
    protected $signature = 'gateway:seed-baseline';

    protected $description = 'Nạp hãng AI, khả năng và trọng số model mặc định (idempotent)';

    public function handle(): int
    {
        foreach ([['claude', 'Claude', '#6D5BD0'], ['grok', 'Grok', '#B0409A'], ['agy', 'Antigravity', '#0C7692'], ['codex', 'Codex', '#10A37F']] as [$code, $name, $color]) {
            Provider::firstOrCreate(['code' => $code], ['name' => $name, 'color' => $color]);
        }
        // Khả năng gateway ĐÃ làm được: text, web_search (mức nhẹ). Ảnh chưa làm nên để tắt, bật khi gateway hỗ trợ thật.
        foreach ([
            ['text', 'Văn bản', 'Trò chuyện, viết, biên tập, phân loại. Mọi công cụ của CLI bị tắt.', 'low', 'runner', true],
            ['web_search', 'Tìm web', 'Tìm kiếm phía hãng (chỉ Claude), không tải trang. Cần cấp quyền chủ động.', 'high', 'runner-search', true],
            ['image_in', 'Phân tích ảnh', 'Chưa hỗ trợ trong gateway hiện tại.', 'medium', 'runner', false],
            ['image_out', 'Sinh ảnh', 'Chưa hỗ trợ trong gateway hiện tại.', 'medium', 'runner', false],
        ] as [$code, $name, $desc, $risk, $handler, $on]) {
            Capability::firstOrCreate(['code' => $code], ['name' => $name, 'description' => $desc, 'risk_level' => $risk, 'handler' => $handler, 'enabled' => $on]);
        }
        foreach ([['opus', 5, 'Tốn hạn mức nhất'], ['sonnet', 1, 'Mức chuẩn'], ['haiku', 0.25, 'Nhẹ'], ['grok-4.7', 1, null], ['gemini-4-neon-high', 1, null], ['gpt-6-luna', 1, null]] as [$m, $w, $n]) {
            ModelWeight::firstOrCreate(['model' => $m], ['weight' => $w, 'note' => $n]);
        }
        $this->info('Đã nạp dữ liệu tham chiếu: '.Provider::count().' hãng, '.Capability::count().' khả năng, '.ModelWeight::count().' trọng số.');

        return self::SUCCESS;
    }
}
