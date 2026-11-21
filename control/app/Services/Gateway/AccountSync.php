<?php

namespace App\Services\Gateway;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\Alert;
use Illuminate\Support\Facades\Cache;

/**
 * Chép trạng thái ĐỘNG từ gateway vào bảng accounts để mọi màn hình đọc một chỗ.
 * Quy tắc: giao diện sở hữu trạng thái; sync chỉ đổi giữa Sẵn sàng <-> Đang nghỉ, và đánh dấu Hết phiên.
 * Hết phiên / Chờ đăng nhập chỉ được gỡ bởi "Kiểm tra ngay" thành công (gateway khởi động lại làm mất cờ, không được tự coi là đã đăng nhập).
 */
class AccountSync
{
    public const CACHE_KEY = 'gateway.sync';

    public function __construct(private GatewayClient $client) {}

    /** @return array{ok:bool, at:string, accounts:int, message?:string} */
    public function run(): array
    {
        $state = $this->client->state();
        $now = now();
        if ($state === null) {
            $this->alert('gateway_unreachable', 'critical', 'Không kết nối được gateway', 'Giao diện không đọc được trạng thái từ gateway. Kiểm tra dịch vụ gateway và mã quản trị.');
            $res = ['ok' => false, 'at' => $now->toIso8601String(), 'accounts' => 0, 'message' => $this->client->configured() ? 'Gateway không trả lời.' : 'Chưa cấu hình mã quản trị gateway.'];
            Cache::put(self::CACHE_KEY, $res, 3600);

            return $res;
        }
        $this->resolve('gateway_unreachable', null);

        $n = 0;
        foreach (Account::all() as $a) {
            $s = $state['accounts'][$a->label] ?? null;
            $n++;
            $upd = ['current_concurrency' => (int) ($s['inflight'] ?? 0)];
            $flag = $s['flag'] ?? null;
            $cool = (int) ($s['cooldown_s'] ?? 0);
            if ($flag === 'auth_expired' && ! in_array($a->status, [AccountStatus::Paused, AccountStatus::Draining], true)) {
                $upd['status'] = AccountStatus::AuthExpired;
            } elseif ($cool > 0 && $a->status === AccountStatus::Active) {
                $upd['status'] = AccountStatus::Cooldown;
                $upd['cooldown_until'] = $now->copy()->addSeconds($cool);
            } elseif ($cool === 0 && $a->status === AccountStatus::Cooldown) {
                $upd['status'] = AccountStatus::Active;
                $upd['cooldown_until'] = null;
            }
            $upd += $this->quotaColumns($s['quota'] ?? null, $now);
            $upd += $this->loginColumns($a, $s['auth'] ?? null, $now);
            if (! empty($s['checked_at'])) {
                $upd['last_auth_check_at'] = \Carbon\Carbon::createFromTimestamp((int) $s['checked_at']);
            }
            $a->update($upd);

            $this->flagAlert($a, $flag, $s['last_error'] ?? null);
            LoginReminder::evaluate($a->refresh());
        }
        $res = ['ok' => true, 'at' => $now->toIso8601String(), 'accounts' => $n];
        Cache::put(self::CACHE_KEY, $res, 3600);

        return $res;
    }

    /** Cập nhật cột hạn mức từ kết quả gateway (utilization là PHÂN SỐ 0..1 theo CLI Claude). */
    public function quotaColumns(?array $quota, $now): array
    {
        if (! $quota) {
            return [];
        }
        $pct = fn ($w) => isset($quota[$w]['utilization']) && is_numeric($quota[$w]['utilization']) ? round(min(1, max(0, (float) $quota[$w]['utilization'])) * 100, 1) : null;
        $out = ['pct_5h' => $pct('five_hour'), 'pct_week' => $pct('seven_day'), 'quota_source' => 'cli', 'quota_updated_at' => $now];

        return $out;
    }

    /** Hạn đăng nhập: ưu tiên số CLI cho biết; hãng không cho biết thì ước tính từ lần đăng nhập gần nhất + hạn cài đặt (nếu có). */
    public function loginColumns(Account $a, ?array $auth, $now): array
    {
        $exp = $auth['login_expires_at'] ?? null;
        if (is_numeric($exp) && $exp > 0) {
            return ['login_expires_at' => \Carbon\Carbon::createFromTimestamp((int) $exp), 'login_expires_source' => 'cli'];
        }
        $life = LoginPolicy::lifetimeDays($a->provider?->code ?? '');
        if ($life !== null && $a->logged_in_at !== null) {
            return ['login_expires_at' => $a->logged_in_at->copy()->addDays($life), 'login_expires_source' => 'estimate'];
        }
        if ($a->login_expires_source === 'estimate') {       // hết cài đặt ước tính: bỏ giá trị ước tính cũ
            return ['login_expires_at' => null, 'login_expires_source' => null];
        }

        return [];     // giữ giá trị CLI cũ nếu lần này runner không trả lời
    }

    /** Áp kết quả "Kiểm tra ngay" lên tài khoản. $recordLogin = người dùng báo "đã đăng nhập xong": ghi nhận thời điểm đăng nhập. */
    public function applyCheck(Account $a, array $res, bool $recordLogin = false): array
    {
        $now = now();
        if ($res['ok'] ?? false) {
            $upd = ['last_auth_check_at' => $now] + $this->quotaColumns($res['quota'] ?? null, $now);
            if (in_array($a->status, [AccountStatus::AuthExpired, AccountStatus::Pending], true)) {
                $upd['status'] = AccountStatus::Active;
                $recordLogin = true;                 // từ hết phiên/chờ đăng nhập sang đạt = vừa đăng nhập lại
            }
            if ($recordLogin) {
                $upd['logged_in_at'] = $now;
                $a->logged_in_at = $now;             // để ước tính bên dưới dùng đúng mốc mới
            }
            $upd += $this->loginColumns($a, $res['auth'] ?? null, $now);
            $a->update($upd);
            LoginReminder::evaluate($a->refresh());
            $this->resolve('account_auth_expired', $a->id);
            $this->resolve('account_tripped', $a->id);

            return ['ok' => true, 'message' => 'Phiên đăng nhập còn hiệu lực ('.(int) ($res['latency_ms'] ?? 0).' ms).'];
        }
        $cls = (string) ($res['error_class'] ?? 'upstream_error');
        $a->update(['last_auth_check_at' => $now]);
        if ($cls === 'auth_expired') {
            $a->update(['status' => AccountStatus::AuthExpired]);
        }
        $human = match ($cls) {
            'auth_expired' => 'Phiên đăng nhập đã hết. Cần đăng nhập lại CLI trên server.',
            'rate_limited' => 'Tài khoản đang hết hạn mức; sẽ tự nghỉ rồi dùng lại.',
            'unreachable', 'gateway_error', 'no_admin_token' => (string) ($res['message'] ?? 'Không gọi được gateway.'),
            'timeout' => 'Runner quá thời gian chờ.',
            'tripped', 'policy_violation' => 'Runner đã ngắt mạch vì CLI vượt quyền. Báo người vận hành.',
            default => 'Kiểm tra không thành công ('.$cls.').',
        };

        return ['ok' => false, 'message' => $human];
    }

    private function flagAlert(Account $a, ?string $flag, ?string $lastError): void
    {
        $type = 'account_auth_expired';
        if ($flag === 'auth_expired') {
            $this->alert($type, 'critical', "{$a->label}: hết phiên đăng nhập", 'Người vận hành cần đăng nhập lại CLI của tài khoản này trên server, rồi bấm "Kiểm tra ngay".', $a->id);
        } else {
            $this->resolve($type, $a->id);
        }
        $type = 'account_tripped';
        if ($flag === 'tripped') {
            $this->alert($type, 'critical', "{$a->label}: bị ngắt mạch", 'Runner phát hiện CLI vượt quyền (công cụ hoặc MCP ngoài danh sách). Dừng dùng tài khoản này và báo người vận hành kiểm tra.', $a->id);
        } else {
            $this->resolve($type, $a->id);
        }
    }

    private function alert(string $type, string $severity, string $title, string $body, ?int $subject = null): void
    {
        $exists = Alert::open()->where('type', $type)->where('subject_id', $subject)->exists();
        if (! $exists) {
            Alert::create(['severity' => $severity, 'type' => $type, 'title' => $title, 'body' => $body,
                'subject_type' => $subject ? 'account' : null, 'subject_id' => $subject, 'opened_at' => now()]);
        }
    }

    private function resolve(string $type, ?int $subject): void
    {
        Alert::open()->where('type', $type)->where('subject_id', $subject)->update(['resolved_at' => now()]);
    }
}
