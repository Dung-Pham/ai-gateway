<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chỉ cho giao diện quản trị truy cập từ các dải mạng đã cấu hình (ADMIN_ALLOWED_CIDRS, cách nhau bằng dấu phẩy).
 * - Có cấu hình: ngoài dải thì 403.
 * - Chưa cấu hình: ở môi trường production thì TỪ CHỐI tất cả (mặc định an toàn); ở môi trường khác thì cho qua để dev.
 * Khi đứng sau reverse proxy phải đặt TRUSTED_PROXIES, nếu không địa chỉ thấy được sẽ là của proxy.
 */
class RestrictAdminNetwork
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = array_values(array_filter(array_map('trim', (array) config('gateway.admin_allowed_cidrs'))));

        if ($allowed === []) {
            if (app()->environment('production')) {
                abort(403, 'Chưa cấu hình ADMIN_ALLOWED_CIDRS: giao diện quản trị bị khóa.');
            }

            return $next($request);
        }

        if (! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(403, 'Địa chỉ mạng này không được phép truy cập giao diện quản trị.');
        }

        return $next($request);
    }
}
