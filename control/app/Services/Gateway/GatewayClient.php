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

    public function reset(string $label): bool
    {
        try {
            return (bool) $this->http()?->post('/internal/accounts/'.rawurlencode($label).'/reset')->ok();
        } catch (\Throwable) {
            return false;
        }
    }
}
