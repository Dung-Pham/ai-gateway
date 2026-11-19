#!/bin/sh
# Khôi phục CSDL từ bản sao lưu .sql.gz. DỪNG gateway/giao diện trong lúc nạp. GHI ĐÈ dữ liệu hiện có (hỏi xác nhận).
set -eu
HERE="$(cd "$(dirname "$0")" && pwd)"
F="${1:?Dùng: restore.sh <tệp .sql.gz>}"
[ -f "$F" ] || { echo "Không thấy $F" >&2; exit 1; }
printf 'Sẽ XÓA toàn bộ dữ liệu hiện tại của CSDL aigw và nạp lại từ %s. Gõ KHOI PHUC để tiếp tục: ' "$F"
read -r a; [ "$a" = "KHOI PHUC" ] || { echo "Đã hủy."; exit 1; }
"$HERE/aigw" stop gateway control scheduler web
"$HERE/aigw" exec -T db psql -U gateway -d postgres -c "DROP DATABASE IF EXISTS aigw WITH (FORCE)" -c "CREATE DATABASE aigw OWNER gateway"
gunzip -c "$F" | "$HERE/aigw" exec -T db psql -U gateway -d aigw -v ON_ERROR_STOP=1 -q
"$HERE/aigw" up
echo "Đã khôi phục. Chạy: scripts/aigw health"
