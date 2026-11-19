<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Alert;
use App\Models\AppKey;
use App\Models\AuditLog;
use App\Models\Capability;
use App\Models\ClientApp;
use App\Models\Pool;
use App\Models\Provider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DỮ LIỆU MẪU GIẢ để dựng và xem khung giao diện. Không phải dữ liệu thật:
 * không có tài khoản AI, khóa hay lượt gọi nào tồn tại ngoài cơ sở dữ liệu này.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(20261119);

        $providers = collect([
            ['claude', 'Claude', '#6D5BD0'],
            ['gemini', 'Gemini', '#0C7692'],
            ['grok', 'Grok', '#B0409A'],
        ])->mapWithKeys(fn ($p) => [$p[0] => Provider::create(['code' => $p[0], 'name' => $p[1], 'color' => $p[2]])]);

        $now = now();
        $acc = [];
        foreach ([
            // label, provider, plan, status, cooldown, max, current, 5h, week, source
            ['claude-runner-01', 'claude', 'Gói Max', 'active', null, 2, 1, 62.0, 41.0, 'cli'],
            ['claude-runner-02', 'claude', 'Gói Max', 'cooldown', $now->copy()->addMinutes(78), 2, 0, 100.0, 78.0, 'cli'],
            ['claude-writer-03', 'claude', 'Gói Pro', 'active', null, 2, 0, 18.0, 22.0, 'internal'],
            ['gemini-01', 'gemini', 'Gói Pro', 'active', null, 3, 2, 35.0, 12.0, 'estimate'],
            ['gemini-02', 'gemini', 'Gói Pro', 'auth_expired', null, 3, 0, null, null, null],
            ['grok-01', 'grok', 'Gói trả phí', 'active', null, 2, 1, null, null, null],
        ] as [$label, $prov, $plan, $status, $cool, $max, $cur, $p5, $pw, $src]) {
            $acc[$label] = Account::create([
                'provider_id' => $providers[$prov]->id, 'label' => $label, 'plan' => $plan, 'status' => $status,
                'cooldown_until' => $cool, 'max_concurrency' => $max, 'current_concurrency' => $cur,
                'runner_url' => "http://runner-{$label}:8800", 'pct_5h' => $p5, 'pct_week' => $pw, 'quota_source' => $src,
                'quota_updated_at' => $src ? $now->copy()->subSeconds(mt_rand(20, 240)) : null,
                'last_auth_check_at' => $now->copy()->subMinutes($status === 'auth_expired' ? 360 : mt_rand(1, 9)),
                'notes' => $status === 'auth_expired' ? 'Phiên đăng nhập đã hết. Cần đăng nhập lại qua SSH.' : null,
            ]);
        }

        $pool = [];
        foreach ([
            ['Viết bài', 'least_used', 'Tài khoản Claude dùng cho việc viết và biên tập.', ['claude-runner-01' => 1, 'claude-runner-02' => 1, 'claude-writer-03' => 2]],
            ['Hội thoại và phân loại', 'priority', 'Việc nhẹ, nhiều lượt: Gemini trước, Grok dự phòng.', ['gemini-01' => 1, 'gemini-02' => 2, 'grok-01' => 3]],
            ['Dự phòng chung', 'round_robin', 'Dùng khi pool chính không còn tài khoản sẵn sàng.', ['claude-writer-03' => 1, 'grok-01' => 1, 'gemini-01' => 1]],
        ] as [$name, $strategy, $desc, $members]) {
            $p = Pool::create(['name' => $name, 'strategy' => $strategy, 'description' => $desc]);
            foreach ($members as $label => $prio) {
                $p->members()->create(['account_id' => $acc[$label]->id, 'priority' => $prio, 'weight' => 1]);
            }
            $pool[$name] = $p;
        }

        $cap = [];
        foreach ([
            ['text', 'Văn bản', 'Trò chuyện, viết, biên tập, phân loại. Công cụ của CLI tắt hoàn toàn.', 'low'],
            ['image_in', 'Phân tích ảnh', 'Nhận ảnh làm đầu vào. Ảnh được kiểm tra loại, kích thước và mã hóa lại.', 'medium'],
            ['image_out', 'Sinh ảnh', 'Trả ảnh do AI tạo. Chỉ bật khi thăm dò xác nhận hãng hỗ trợ.', 'medium'],
            ['web_search', 'Tìm web', 'Chạy ở runner riêng, chặn dải IP nội bộ. Cần cấp quyền chủ động.', 'high'],
        ] as [$code, $name, $desc, $risk]) {
            $cap[$code] = Capability::create(['code' => $code, 'name' => $name, 'description' => $desc, 'risk_level' => $risk, 'handler' => $code === 'web_search' ? 'runner-search' : 'runner', 'enabled' => $code !== 'image_out']);
        }

        $apps = [];
        foreach ([
            ['SEO Studio', 'seo-studio', 'Nhóm Nội dung', 'active', 'Sinh và biên tập bài SEO, tìm tài liệu tham khảo.', [['text', 'Viết bài', ['opus', 'sonnet', 'haiku']], ['image_in', 'Hội thoại và phân loại', ['gemini-pro', 'gemini-flash']], ['web_search', 'Viết bài', ['haiku']]]],
            ['Trợ lý chăm sóc khách hàng', 'tro-ly-cskh', 'Bộ phận CSKH', 'active', 'Soạn câu trả lời gợi ý cho nhân viên CSKH.', [['text', 'Hội thoại và phân loại', ['gemini-flash', 'grok-default']], ['image_in', 'Hội thoại và phân loại', ['gemini-flash']]]],
            ['Phân loại tài liệu nội bộ', 'phan-loai-tai-lieu', 'Phòng Hành chính', 'active', 'Gắn nhãn và tóm tắt tài liệu nội bộ.', [['text', 'Hội thoại và phân loại', ['gemini-flash', 'grok-default']]]],
            ['Công cụ dịch thuật (thử nghiệm)', 'dich-thuat', 'Nhóm Sản phẩm', 'suspended', 'Đang tạm ngưng để rà soát hạn mức.', [['text', 'Dự phòng chung', ['sonnet']]]],
        ] as [$name, $slug, $owner, $status, $desc, $policies]) {
            $app = ClientApp::create(compact('name', 'slug', 'owner', 'status') + ['description' => $desc]);
            foreach ($policies as [$code, $poolName, $models]) {
                $app->policies()->create(['capability_id' => $cap[$code]->id, 'allowed_models' => $models, 'pool_id' => $pool[$poolName]->id, 'fallback_pool_id' => $poolName === 'Dự phòng chung' ? null : $pool['Dự phòng chung']->id]);
            }
            // Khóa mẫu: chỉ có tiền tố và bản băm ngẫu nhiên, KHÔNG phải khóa dùng được.
            $app->keys()->create(['name' => 'Khóa chính', 'key_prefix' => 'gw_' . Str::lower(Str::random(8)), 'key_hash' => hash('sha256', Str::random(40)), 'last_used_at' => $status === 'active' ? $now->copy()->subSeconds(mt_rand(5, 600)) : $now->copy()->subDays(3)]);
            $apps[$slug] = $app;
        }
        AppKey::query()->where('app_id', $apps['seo-studio']->id)->first()?->replicate()->fill(['name' => 'Khóa cũ (sắp thu hồi)', 'key_prefix' => 'gw_' . Str::lower(Str::random(8)), 'key_hash' => hash('sha256', Str::random(40)), 'expires_at' => $now->copy()->addDays(6)])->save();

        $this->usage($apps, $acc, $now);
        $this->alerts($apps, $acc, $now);

        foreach ([
            ['quản trị', 'app.suspend', 'Công cụ dịch thuật (thử nghiệm)', ['lý do' => 'rà soát hạn mức'], $now->copy()->subHours(5)],
            ['quản trị', 'account.cooldown', 'claude-runner-02', ['nguồn' => 'tự động', 'chạm giới hạn' => '5h'], $now->copy()->subMinutes(42)],
            ['quản trị', 'key.rotate', 'SEO Studio', ['khóa mới' => 'Khóa chính'], $now->copy()->subDays(1)],
        ] as [$actor, $action, $subject, $details, $at]) {
            AuditLog::create(['actor' => $actor, 'action' => $action, 'subject' => $subject, 'details' => $details, 'ip' => '10.0.0.5', 'created_at' => $at]);
        }
    }

    private function usage(array $apps, array $acc, $now): void
    {
        $weights = ['opus' => 5.0, 'sonnet' => 1.0, 'haiku' => 0.25, 'gemini-pro' => 1.0, 'gemini-flash' => 0.2, 'grok-default' => 1.0];
        // app => [tần suất/giờ giờ cao điểm, [[tài khoản, trọng số]], [[khả năng, trọng số]]]
        $plan = [
            'seo-studio' => [16, [['claude-runner-01', 5], ['claude-runner-02', 3], ['claude-writer-03', 2]], [['text', 8], ['web_search', 1], ['image_in', 1]]],
            'tro-ly-cskh' => [10, [['gemini-01', 6], ['gemini-02', 2], ['grok-01', 2]], [['text', 9], ['image_in', 1]]],
            'phan-loai-tai-lieu' => [7, [['grok-01', 5], ['gemini-01', 4]], [['text', 1]]],
            'dich-thuat' => [2, [['claude-writer-03', 1]], [['text', 1]]],
        ];
        $modelsFor = ['claude' => ['opus', 'sonnet', 'sonnet', 'haiku'], 'gemini' => ['gemini-pro', 'gemini-flash', 'gemini-flash'], 'grok' => ['grok-default']];
        $rows = [];
        $n = 0;
        $start = $now->copy()->subDays(7)->startOfHour();

        for ($t = $start->copy(); $t < $now; $t->addHour()) {
            $local = $t->copy()->setTimezone(config('gateway.timezone'));
            $factor = 0.12 + 0.88 * exp(-pow(($local->hour + $local->minute / 60 - 14) / 5, 2));
            foreach ($plan as $slug => [$peak, $accounts, $caps]) {
                if ($slug === 'dich-thuat' && $t > $now->copy()->subHours(5)) {
                    continue; // đã tạm ngưng
                }
                $count = (int) round($peak * $factor * (0.7 + mt_rand(0, 60) / 100));
                for ($i = 0; $i < $count; $i++) {
                    $ts = $t->copy()->addSeconds(mt_rand(0, 3599));
                    if ($ts > $now) {
                        continue;
                    }
                    $label = $this->pick($accounts);
                    $account = $acc[$label];
                    $status = 'ok';
                    $error = null;
                    if ($label === 'claude-runner-02' && $ts > $now->copy()->subMinutes(100)) {
                        if ($ts > $now->copy()->subMinutes(42)) {
                            continue; // tài khoản đã nghỉ, không nhận thêm
                        }
                        $status = 'rate_limited';
                        $error = 'usage_limit_5h';
                    } elseif ($label === 'gemini-02' && $ts > $now->copy()->subHours(6)) {
                        if (mt_rand(0, 9) > 1) {
                            continue;
                        }
                        $status = 'error';
                        $error = 'auth_expired';
                    } elseif (($r = mt_rand(1, 100)) <= 2) {
                        $status = 'error';
                        $error = 'upstream_timeout';
                    } elseif ($r === 3) {
                        $status = 'denied';
                        $error = 'limit_exceeded';
                    }
                    $capability = $this->pick($caps);
                    $model = $modelsFor[$account->provider->code][array_rand($modelsFor[$account->provider->code])];
                    $in = mt_rand(200, 6000);
                    $out = $capability === 'image_in' ? mt_rand(60, 400) : mt_rand(80, 3200);
                    $ok = $status === 'ok';
                    $rows[] = [
                        'ts' => $ts, 'app_id' => $apps[$slug]->id, 'account_id' => $account->id, 'capability' => $capability, 'model' => $model,
                        'input_units' => $ok ? $in : 0, 'output_units' => $ok ? $out : 0,
                        'weighted_units' => $ok ? (int) round(($in + $out * 3) / 100 * $weights[$model]) : 0,
                        'latency_ms' => $ok ? (int) (1500 + mt_rand(0, 9000) * ($model === 'opus' ? 2.2 : 1) + $out * 4) : mt_rand(300, 2500),
                        'status' => $status, 'error_class' => $error, 'request_id' => 'req_' . substr(md5('r' . $n++), 0, 12),
                        'estimated' => $account->provider->code !== 'claude',
                    ];
                }
            }
        }
        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('usage_events')->insert($chunk);
        }

        // Hạn mức chọn sao cho mức dùng hôm nay có độ phủ khác nhau, để màn hình có đủ trạng thái để xem.
        $today = DB::table('usage_events')->where('ts', '>=', now()->startOfDay())->where('status', 'ok')
            ->selectRaw('app_id, SUM(weighted_units) as u')->groupBy('app_id')->pluck('u', 'app_id');
        foreach ([['seo-studio', 0.47, 60, 4], ['tro-ly-cskh', 0.31, 40, 3], ['phan-loai-tai-lieu', 0.86, 20, 2], ['dich-thuat', 0.05, 10, 1]] as [$slug, $ratio, $rpm, $conc]) {
            $used = max(1, (int) ($today[$apps[$slug]->id] ?? 1));
            $daily = (int) (ceil($used / $ratio / 50) * 50);
            $apps[$slug]->limits()->create(['rpm' => $rpm, 'max_concurrency' => $conc, 'units_per_day' => $daily, 'units_per_month' => $daily * 24, 'mode' => $slug === 'phan-loai-tai-lieu' ? 'soft' : 'hard']);
        }
    }

    private function pick(array $weighted): string
    {
        $sum = array_sum(array_column($weighted, 1));
        $r = mt_rand(1, (int) $sum);
        foreach ($weighted as [$value, $w]) {
            if (($r -= $w) <= 0) {
                return $value;
            }
        }

        return $weighted[0][0];
    }

    private function alerts(array $apps, array $acc, $now): void
    {
        $cls = $apps['phan-loai-tai-lieu'];
        $clsPct = (int) round($cls->unitsToday() / max(1, (int) $cls->dailyLimit()) * 100);
        $until = $acc['claude-runner-02']->cooldown_until->copy()->setTimezone(config('gateway.timezone'))->format('H:i');
        foreach ([
            ['critical', 'account.auth_expired', 'gemini-02 hết phiên đăng nhập', 'Tài khoản đã bị loại khỏi pool. Cần đăng nhập lại qua SSH rồi bấm "Kiểm tra ngay".', 'account', $acc['gemini-02']->id, $now->copy()->subHours(6), null, null],
            ['warning', 'account.cooldown', "claude-runner-02 đang nghỉ đến {$until}", 'Chạm giới hạn phiên 5 giờ. Yêu cầu tự chuyển sang tài khoản khác trong pool "Viết bài".', 'account', $acc['claude-runner-02']->id, $now->copy()->subMinutes(42), null, null],
            ['warning', 'app.quota_80', "Phân loại tài liệu nội bộ đã dùng {$clsPct}% hạn mức ngày", 'Hạn mức ở chế độ mềm: chưa chặn, chỉ cảnh báo.', 'app', $apps['phan-loai-tai-lieu']->id, $now->copy()->subMinutes(25), $now->copy()->subMinutes(10), null],
            ['info', 'account.no_quota_signal', 'grok-01 chưa đo được mức dùng', 'Chưa có cách đọc mức dùng bằng máy cho hãng này. Chỉ có số đếm nội bộ theo ứng dụng.', 'account', $acc['grok-01']->id, $now->copy()->subDays(1), null, null],
            ['info', 'account.recovered', 'grok-01 đã phục hồi sau lỗi mạng', null, 'account', $acc['grok-01']->id, $now->copy()->subDays(2), $now->copy()->subDays(2)->addMinutes(3), $now->copy()->subDays(2)->addMinutes(9)],
        ] as [$sev, $type, $title, $body, $st, $sid, $opened, $ack, $res]) {
            Alert::create(['severity' => $sev, 'type' => $type, 'title' => $title, 'body' => $body, 'subject_type' => $st, 'subject_id' => $sid, 'opened_at' => $opened, 'acknowledged_at' => $ack, 'resolved_at' => $res]);
        }
    }
}
