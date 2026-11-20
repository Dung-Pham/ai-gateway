#!/usr/bin/env python3
"""Công cụ vận hành (chạy trên máy AI bởi NGƯỜI VẬN HÀNH, không phải từ giao diện web). Chỉ cần Python 3, không cần cài thêm gì.

  gwctl.py init <thư_mục_bí_mật>                       # tạo MỌI bí mật một lần: app_key, db_password, db_root_password, database_url, admin_token, runner_secrets.json
  gwctl.py new-runner <thư_mục_bí_mật> <claude|grok|agy|codex> <nhãn>   # tạo auth/ + bí mật runner + deploy/runners.d/<nhãn>.yml (đoạn Compose tự sinh)
  gwctl.py list-runners                                  # liệt kê runner đã khai báo

Bí mật không bao giờ bị ghi đè (O_EXCL). Mất app_key = mọi khóa API đã cấp vô hiệu: PHẢI sao lưu thư mục bí mật (scripts/backup.sh).
"""
import base64
import json
import os
import re
import secrets
import sys

LABEL_RE = re.compile(r"^[a-z0-9]+(-[a-z0-9]+)*$")
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RUNNERS_D = os.path.join(ROOT, "deploy", "runners.d")

PROVIDER_TEMPLATE = {
    "claude": {"env": "CLI_CLAUDE", "sha": "CLAUDE_SHA256", "bin": "claude", "extra_env": "", "extra_vol": ""},
    "grok": {"env": "CLI_GROK", "sha": "GROK_SHA256", "bin": "grok",
             "extra_env": "      RUNNER_GROK_DENY_FILE: /pins/deny.txt\n",
             "extra_vol": "      - ../runner/pins/grok-1.0.70.deny.txt:/pins/deny.txt:ro\n"},
    "agy": {"env": "CLI_AGY", "sha": "AGY_SHA256", "bin": "agy", "extra_env": '      RUNNER_TIMEOUT_S: "200"\n', "extra_vol": ""},
    "codex": {"env": "CLI_CODEX", "sha": "CODEX_SHA256", "bin": "codex", "extra_env": "", "extra_vol": ""},
}


def write_secret(path, text):
    os.makedirs(os.path.dirname(path), mode=0o700, exist_ok=True)
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)   # O_EXCL: không ghi đè bí mật có sẵn
    with os.fdopen(fd, "w") as f:
        f.write(text)


def init(root):
    os.makedirs(root, mode=0o700, exist_ok=True)
    if os.path.exists(os.path.join(root, "app_key")):
        sys.exit(f"{root}/app_key đã tồn tại: KHÔNG chạy lại init (sẽ làm mất khóa API). Xóa tay nếu thật sự muốn tạo mới.")
    app_key = "base64:" + base64.b64encode(secrets.token_bytes(32)).decode()      # Laravel APP_KEY, đồng thời là "tiêu" băm khóa API ở gateway
    dbpw = secrets.token_urlsafe(32)
    write_secret(os.path.join(root, "app_key"), app_key)
    write_secret(os.path.join(root, "db_password"), dbpw)
    write_secret(os.path.join(root, "db_root_password"), secrets.token_urlsafe(32))      # root MariaDB: chỉ dùng cho khôi phục/bảo trì
    write_secret(os.path.join(root, "database_url"), f"mysql+pymysql://gateway:{dbpw}@db:3306/aigw?charset=utf8mb4")
    write_secret(os.path.join(root, "admin_token"), secrets.token_hex(24))
    write_secret(os.path.join(root, "runner_secrets.json"), "{}")
    print(f"Đã tạo app_key, db_password, db_root_password, database_url, admin_token, runner_secrets.json trong {root} (quyền 0600/0700).")
    print("Việc tiếp theo: SAO LƯU thư mục này ra nơi an toàn (xem docs/BAN_GIAO_TRIEN_KHAI.md, bước sao lưu).")


def new_runner(root, provider, label):
    if provider not in PROVIDER_TEMPLATE or not LABEL_RE.match(label):
        sys.exit("Hãng phải là claude|grok|agy|codex; nhãn chỉ gồm chữ thường, số và gạch ngang.")
    frag = os.path.join(RUNNERS_D, f"{label}.yml")
    if os.path.exists(frag):
        sys.exit(f"Đã có {frag}.")
    d = os.path.join(root, label)
    os.makedirs(os.path.join(d, "auth"), mode=0o700, exist_ok=False)
    sec = secrets.token_hex(24)
    write_secret(os.path.join(d, "runner.secret"), sec)
    path = os.path.join(root, "runner_secrets.json")
    data = json.load(open(path))
    data[label] = sec
    tmp = path + ".new"
    with open(tmp, "w") as f:
        json.dump(data, f)
    os.chmod(tmp, 0o600)
    os.replace(tmp, path)
    t, key = PROVIDER_TEMPLATE[provider], label.replace("-", "_")
    os.makedirs(RUNNERS_D, exist_ok=True)
    with open(frag, "w", encoding="utf-8") as f:
        f.write(f"""# Tự sinh bởi gwctl.py new-runner ({provider}). Chỉnh tay được; KHÔNG chứa bí mật.
# Cứng hóa giống mọi runner: hệ tệp chỉ đọc, bỏ mọi capability, không leo quyền, giới hạn tiến trình/RAM/CPU, chỉ ở mạng ai_internal (không internet).
services:
  runner-{label}:
    image: ai-gateway-runner:${{AIGW_VERSION:-0.2.0}}
    build: {{context: ../runner}}
    user: "${{RUNNER_UID:-10001}}:${{RUNNER_GID:-10001}}"
    read_only: true
    cap_drop: [ALL]
    security_opt: ["no-new-privileges:true"]
    restart: unless-stopped
    logging: {{driver: json-file, options: {{max-size: "20m", max-file: "5"}}}}
    pids_limit: 256
    mem_limit: 1g
    cpus: 2
    tmpfs: ["/tmp:rw,size=256m,mode=1777"]
    networks: [ai_internal]
    depends_on: [egress-proxy]
    environment:
      HTTPS_PROXY: http://egress-proxy:3128
      HTTP_PROXY: http://egress-proxy:3128
      NO_PROXY: localhost,127.0.0.1
      RUNNER_AUTH_DIR: /auth
      RUNNER_WORK_ROOT: /tmp/runner
      RUNNER_SECRET_FILE: /run/secrets/runner_secret
      RUNNER_PROVIDER: {provider}
      RUNNER_CLI_BIN: /opt/cli/{t['bin']}
      RUNNER_CLI_SHA256: ${{{t['sha']}}}
{t['extra_env']}    volumes:
      - ${{{t['env']}}}:/opt/cli/{t['bin']}:ro
{t['extra_vol']}      - ${{SECRETS_DIR}}/{label}/auth:/auth
    secrets: [{{source: runner_secret_{key}, target: runner_secret}}]

secrets:
  runner_secret_{key}: {{file: "${{SECRETS_DIR}}/{label}/runner.secret"}}
""")
    uid = os.environ.get("RUNNER_UID", "10001")
    print(f"""Đã tạo:
  {d}/auth        (thư mục lưu phiên đăng nhập của tài khoản; PHẢI thuộc uid {uid}: sudo chown -R {uid}:{os.environ.get('RUNNER_GID', '10001')} {d}/auth)
  {d}/runner.secret và mục `{label}` trong runner_secrets.json
  {frag}      (đoạn Compose; `scripts/aigw up` tự nhận)

Việc tiếp theo, theo thứ tự:
  1. NGƯỜI SỞ HỮU TÀI KHOẢN đăng nhập CLI vào thư mục auth trên (xem docs/BAN_GIAO_TRIEN_KHAI.md mục "Đăng nhập tài khoản AI").
  2. scripts/aigw up                    # khởi động runner-{label}
  3. Giao diện > Tài khoản AI > Thêm: nhãn `{label}`, hãng {provider}, địa chỉ runner http://runner-{label}:8800
  4. Bấm "Kiểm tra ngay" ở trang tài khoản.""")


def list_runners():
    if not os.path.isdir(RUNNERS_D):
        print("(chưa có runner nào; dùng new-runner)")
        return
    for f in sorted(os.listdir(RUNNERS_D)):
        if f.endswith(".yml"):
            print(f[:-4])


if __name__ == "__main__":
    a = sys.argv[1:]
    if a[:1] == ["init"] and len(a) == 2:
        init(a[1])
    elif a[:1] == ["new-runner"] and len(a) == 4:
        new_runner(a[1], a[2], a[3])
    elif a == ["list-runners"]:
        list_runners()
    else:
        sys.exit(__doc__)
