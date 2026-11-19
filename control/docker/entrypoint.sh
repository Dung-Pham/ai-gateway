#!/bin/sh
# Nạp bí mật từ Docker secrets vào biến môi trường (không bao giờ ghi vào ảnh hay tệp .env), rồi chạy lệnh.
set -eu
load() { # load BIẾN /đường/dẫn/tệp
    if [ -z "$(printenv "$1" 2>/dev/null || true)" ] && [ -r "$2" ]; then
        export "$1=$(cat "$2")"
    fi
}
load APP_KEY /run/secrets/app_key
load DB_PASSWORD /run/secrets/db_password
export GATEWAY_ADMIN_TOKEN_FILE="${GATEWAY_ADMIN_TOKEN_FILE:-/run/secrets/admin_token}"
if [ "${APP_ENV:-production}" = "production" ] && [ -z "${APP_KEY:-}" ]; then
    echo "Thiếu APP_KEY (secret app_key). Chạy scripts/gwctl.py init trước." >&2
    exit 1
fi
exec "$@"
