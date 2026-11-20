#!/bin/sh
# Kiểm tra máy trước khi triển khai. Chỉ ĐỌC, không sửa gì. Thoát khác 0 nếu có mục bắt buộc không đạt.
HERE="$(cd "$(dirname "$0")" && pwd)"
cd "$HERE/../deploy" || exit 1
fail=0; warn=0
ok()   { echo "  [ĐẠT]   $1"; }
bad()  { echo "  [LỖI]   $1"; fail=$((fail+1)); }
note() { echo "  [LƯU Ý] $1"; warn=$((warn+1)); }
getenv() { [ -f .env ] && sed -n "s/^$1=\\(.*\\)$/\\1/p" .env | tail -1; }

echo "== Phần mềm"
command -v docker >/dev/null 2>&1 && ok "docker $(docker --version | sed 's/Docker version //;s/,.*//')" || bad "chưa cài docker"
docker compose version >/dev/null 2>&1 && ok "docker compose $(docker compose version --short)" || bad "chưa có docker compose v2"
docker info >/dev/null 2>&1 && ok "người dùng hiện tại gọi được docker" || bad "không gọi được docker (thiếu quyền hoặc daemon chưa chạy)"
command -v python3 >/dev/null 2>&1 && ok "python3 (cho gwctl.py)" || bad "chưa cài python3"
command -v curl >/dev/null 2>&1 && ok "curl" || bad "chưa cài curl"

echo "== Tệp cấu hình"
[ -f .env ] && ok "deploy/.env có" || bad "thiếu deploy/.env (sao từ .env.example)"
S="$(getenv SECRETS_DIR)"; [ -n "$S" ] || S=./secrets
case "$S" in /*) ;; *) S="./$S" ;; esac
for f in app_key db_password db_root_password database_url admin_token runner_secrets.json; do
    [ -s "$S/$f" ] && ok "bí mật $f" || bad "thiếu $S/$f (chạy: python3 scripts/gwctl.py init $S)"
done
AU="$(getenv AIGW_UID)"; AU="${AU:-10001}"
if [ -d "$S" ]; then
    owner=$(stat -c '%u' "$S/app_key" 2>/dev/null)
    [ -n "$owner" ] && { [ "$owner" = "$AU" ] && ok "bí mật thuộc AIGW_UID=$AU (container đọc được)" || bad "bí mật thuộc uid $owner nhưng AIGW_UID=$AU: container không đọc được. Đặt AIGW_UID=$owner trong .env, hoặc sudo chown -R $AU $S"; }
    perm=$(stat -c '%a' "$S" 2>/dev/null); [ "$perm" = "700" ] && ok "thư mục bí mật quyền 700" || note "thư mục bí mật quyền $perm (nên 700)"
fi
[ -d runners.d ] && [ -n "$(ls runners.d/*.yml 2>/dev/null)" ] && ok "có runner: $(ls runners.d/*.yml | sed 's#.*/##;s#\.yml##' | tr '\n' ' ')" || bad "chưa có runner nào (gwctl.py new-runner)"

echo "== CLI của hãng AI (gắn chỉ-đọc vào runner, ghim SHA-256)"
for pair in CLAUDE:CLI_CLAUDE AGY:CLI_AGY GROK:CLI_GROK CODEX:CLI_CODEX; do
    n="${pair%%:*}"; v="${pair##*:}"; p="$(getenv $v)"; h="$(getenv ${n}_SHA256)"
    [ -z "$p" ] && continue
    if [ -x "$p" ]; then
        got="$(sha256sum "$p" | cut -d' ' -f1)"
        if [ -z "$h" ]; then note "$v=$p có nhưng ${n}_SHA256 trống"
        elif [ "$got" = "$h" ]; then ok "$v khớp SHA-256 đã ghim"
        else bad "$v SHA-256 KHÔNG khớp (đang là $got). Đúng phiên bản chưa? Xem runner/pins/versions.env"; fi
    else bad "$v=$p không tồn tại hoặc không chạy được"; fi
done

echo "== Hệ thống"
kb=$(df -Pk /var/lib/docker 2>/dev/null | awk 'NR==2{print $4}'); [ -n "$kb" ] && { [ "$kb" -gt 10485760 ] && ok "dung lượng trống $((kb/1048576)) GB cho Docker" || note "chỉ còn $((kb/1048576)) GB trống cho Docker (khuyến nghị >= 10 GB)"; }
mem=$(awk '/MemAvailable/{print int($2/1024)}' /proc/meminfo 2>/dev/null); [ -n "$mem" ] && { [ "$mem" -gt 3000 ] && ok "RAM khả dụng ${mem} MB" || note "RAM khả dụng ${mem} MB (khuyến nghị >= 4 GB)"; }
timedatectl show -p NTPSynchronized --value 2>/dev/null | grep -q yes && ok "đồng hồ đồng bộ NTP" || note "không xác nhận được đồng bộ NTP (lệch giờ làm sai hạn mức theo ngày)"
for pn in "$(getenv WEB_PORT):8080" "$(getenv GATEWAY_PORT):8771"; do
    p="${pn%%:*}"; [ -z "$p" ] && p="${pn##*:}"
    ss -ltn 2>/dev/null | awk '{print $4}' | grep -q ":$p\$" && note "cổng $p đang có tiến trình khác lắng nghe" || ok "cổng $p trống"
done
wb="$(getenv WEB_BIND)"; gb="$(getenv GATEWAY_BIND)"
[ "${wb:-127.0.0.1}" = "0.0.0.0" ] && note "WEB_BIND=0.0.0.0: giao diện mở ra mọi địa chỉ. Chỉ nên mở cho dải của quản trị (đặt ADMIN_ALLOWED_CIDRS + tường lửa)"
[ "${gb:-127.0.0.1}" = "0.0.0.0" ] && note "GATEWAY_BIND=0.0.0.0: cổng API mở ra mọi địa chỉ. Nên bind IP nội bộ + tường lửa chỉ cho máy chủ SEO Studio"
[ "$(getenv REQUIRE_2FA)" = "false" ] && note "REQUIRE_2FA=false: production nên để true"
[ -z "$(getenv ADMIN_ALLOWED_CIDRS)" ] && note "ADMIN_ALLOWED_CIDRS trống: giao diện quản trị sẽ KHÓA hết (an toàn), đặt dải mạng quản trị để vào được"

echo; echo "Lỗi: $fail, lưu ý: $warn."
[ "$fail" -eq 0 ]
