#!/bin/sh
# Khôi phục CSDL từ bản sao lưu .sql.gz. DỪNG gateway/giao diện trong lúc nạp. GHI ĐÈ dữ liệu hiện có (hỏi xác nhận).
set -eu
HERE="$(cd "$(dirname "$0")" && pwd)"
F="${1:?Dùng: restore.sh <tệp .sql.gz>}"
[ -f "$F" ] || { echo "Không thấy $F" >&2; exit 1; }
printf 'Sẽ XÓA toàn bộ dữ liệu hiện tại của CSDL aigw và nạp lại từ %s. Gõ KHOI PHUC để tiếp tục: ' "$F"
read -r a; [ "$a" = "KHOI PHUC" ] || { echo "Đã hủy."; exit 1; }
"$HERE/aigw" stop gateway control scheduler web
"$HERE/aigw" exec -T db sh -c 'mariadb -uroot -p"$(cat /run/secrets/db_root_password)" -e "DROP DATABASE IF EXISTS aigw; CREATE DATABASE aigw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON aigw.* TO gateway@\"%\""'
gunzip -c "$F" | "$HERE/aigw" exec -T db sh -c 'mariadb -ugateway -p"$(cat /run/secrets/db_password)" --default-character-set=utf8mb4 aigw'
"$HERE/aigw" up
echo "Đã khôi phục. Chạy: scripts/aigw health"
