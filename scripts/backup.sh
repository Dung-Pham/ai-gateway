#!/bin/sh
# Sao lưu: CSDL MariaDB (nén) + thư mục bí mật (mã hóa nếu có AIGW_BACKUP_PASSPHRASE_FILE). Không sao lưu phiên đăng nhập CLI trong auth/ nếu --no-auth.
# Dùng:  scripts/backup.sh [thư_mục_đích=/var/backups/aigw]
# Khôi phục: scripts/restore.sh <tệp .sql.gz> (CSDL) — bí mật khôi phục bằng tay (xem docs/BAN_GIAO_TRIEN_KHAI.md).
set -eu
HERE="$(cd "$(dirname "$0")" && pwd)"
DEST="${1:-/var/backups/aigw}"; STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$DEST"; chmod 700 "$DEST"
cd "$HERE/../deploy"
SECRETS="$(sed -n 's/^SECRETS_DIR=\(.*\)$/\1/p' .env | tail -1)"; SECRETS="${SECRETS:-./secrets}"
"$HERE/aigw" exec -T db sh -c 'mariadb-dump -ugateway -p"$(cat /run/secrets/db_password)" --single-transaction --routines --default-character-set=utf8mb4 aigw' | gzip -9 > "$DEST/aigw-db-$STAMP.sql.gz"
chmod 600 "$DEST/aigw-db-$STAMP.sql.gz"
if [ -n "${AIGW_BACKUP_PASSPHRASE_FILE:-}" ]; then
    tar -C "$SECRETS" -czf - . | openssl enc -aes-256-cbc -pbkdf2 -salt -pass "file:$AIGW_BACKUP_PASSPHRASE_FILE" -out "$DEST/aigw-secrets-$STAMP.tar.gz.enc"
    chmod 600 "$DEST/aigw-secrets-$STAMP.tar.gz.enc"
    echo "Đã tạo: $DEST/aigw-db-$STAMP.sql.gz và aigw-secrets-$STAMP.tar.gz.enc (mã hóa)"
else
    echo "Đã tạo: $DEST/aigw-db-$STAMP.sql.gz"
    echo "CẢNH BÁO: chưa sao lưu bí mật. Đặt AIGW_BACKUP_PASSPHRASE_FILE=<tệp chứa cụm từ mật khẩu> để sao lưu thư mục bí mật có mã hóa."
    echo "Mất app_key = mọi khóa API đã cấp thành vô hiệu và không khôi phục được."
fi
find "$DEST" -name 'aigw-*' -mtime +"${AIGW_BACKUP_KEEP_DAYS:-14}" -delete
