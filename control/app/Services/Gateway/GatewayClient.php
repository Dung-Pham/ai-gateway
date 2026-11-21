<?php

namespace App\Services\Gateway;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Gọi các đường quản trị nội bộ của gateway. Không bao giờ ném lỗi mạng ra ngoài: gateway tắt thì trả null/lỗi có chữ,
 * để giao diện vẫn mở được và hiện "mất kết nối gateway" thay vì sập.
 */
class GatewayClient
{
    private function http(?int $timeout = null): ?PendingRequest
    {
        $token = $this->token();
        if ($token === null) {
            return null;
        }

        return Http::baseUrl(config('gateway.url'))->withHeaders(['X-Admin-Token' => $token])->acceptJson()
            ->timeout($timeout ?? config('gateway.timeout'));
    }

    public function token(): ?string
    {
        $file = config('gateway.admin_token_file');
        $t = $file && is_readable($file) ? trim((string) file_get_contents($file)) : trim((string) config('gateway.admin_token'));

        return $t !== '' ? $t : null;
    }

    public function configured(): bool
    {
        return $this->token() !== null;
    }

    /** Trạng thái động của mọi tài khoản; null nếu không gọi được. */
    public function state(): ?array
    {
        try {
            $r = $this->http()?->get('/internal/state');

            return $r && $r->ok() && is_array($r->json('accounts')) ? $r->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{ok:bool, error_class?:string, message?:string, latency_ms?:int, quota?:array} */
    public function check(string $label): array
    {
        try {
            $http = $this->http(config('gateway.check_timeout'));
            if (! $http) {
                return ['ok' => false, 'error_class' => 'no_admin_token', 'message' => 'Giao diện chưa có mã quản trị để gọi gateway.'];
            }
            $r = $http->post('/internal/accounts/'.rawurlencode($label).'/check');
            if (! $r->ok() || ! is_array($r->json())) {
                return ['ok' => false, 'error_class' => 'gateway_error', 'message' => 'Gateway trả lỗi ('.$r->status().').'];
            }

            return $r->json();
        } catch (\Throwable) {
            return ['ok' => false, 'error_class' => 'unreachable', 'message' => 'Không kết nối được gateway.'];
        }
    }

    /**
     * Đăng nhập tài khoản từ giao diện. Thân yêu cầu có thể chứa MÃ XÁC NHẬN MỘT LẦN của người dùng: không ghi log, không lưu, không trả lại.
     *
     * @return array<string, mixed> luôn có 'ok'; lỗi mạng trả thông điệp chung (không kèm nội dung yêu cầu)
     */
    private function loginCall(string $method, string $label, string $action, array $body = [], array $query = []): array
    {
        try {
            $http = $this->http(35);
            if (! $http) {
                return ['ok' => false, 'error_class' => 'no_admin_token', 'message' => 'Giao diện chưa có mã quản trị để gọi gateway.'];
            }
            $url = '/internal/accounts/'.rawurlencode($label).'/login/'.$action;
            $r = $method === 'get' ? $http->get($url, $query) : $http->post($url, $body);
            $j = $r->json();

            return is_array($j) && array_key_exists('ok', $j) ? $j : ['ok' => false, 'error_class' => 'gateway_error', 'message' => 'Gateway trả lỗi ('.$r->status().').'];
        } catch (\Throwable) {
            return ['ok' => false, 'error_class' => 'unreachable', 'message' => 'Không kết nối được gateway.'];
        }
    }

    public function loginStart(string $label): array
    {
        return $this->loginCall('post', $label, 'start');
    }

    public function loginStatus(string $label, string $sessionId): array
    {
        return $this->loginCall('get', $label, 'status', query: ['session_id' => $sessionId]);
    }

    public function loginCode(string $label, string $sessionId, string $code): array
    {
        return $this->loginCall('post', $label, 'code', ['session_id' => $sessionId, 'code' => $code]);
    }

    public function loginCancel(string $label, string $sessionId): array
    {
        return $this->loginCall('post', $label, 'cancel', ['session_id' => $sessionId]);
    }

    public function reset(string $label): bool
    {
        try {
            return (bool) $this->http()?->post('/internal/accounts/'.rawurlencode($label).'/reset')->ok();
        } catch (\Throwable) {
            return false;
        }
    }
}
