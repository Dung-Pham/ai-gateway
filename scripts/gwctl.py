#!/usr/bin/env python3
"""Công cụ vận hành (chạy trên máy AI bởi NGƯỜI VẬN HÀNH, không phải từ giao diện web).

  gwctl.py init <thư_mục_bí_mật> --pepper-file <tệp_chứa_APP_KEY>   # tạo pepper, admin_token, runner_secrets.json
  gwctl.py new-runner <thư_mục_bí_mật> <claude|grok|agy> <nhãn>      # tạo thư mục auth + bí mật runner, in đoạn Compose cần thêm
"""
import json
import os
import re
import secrets
import sys

LABEL_RE = re.compile(r"^[a-z0-9]+(-[a-z0-9]+)*$")


def write_secret(path, text):
    os.makedirs(os.path.dirname(path), mode=0o700, exist_ok=True)
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)   # O_EXCL: không ghi đè bí mật có sẵn
    with os.fdopen(fd, "w") as f:
        f.write(text)


def init(root, pepper_file):
    os.makedirs(root, mode=0o700, exist_ok=True)
    pepper = open(pepper_file, encoding="utf-8").read().strip()
    if len(pepper) < 20:
        sys.exit("Tệp APP_KEY quá ngắn.")
    write_secret(os.path.join(root, "pepper"), pepper)
    write_secret(os.path.join(root, "admin_token"), secrets.token_hex(24))
    write_secret(os.path.join(root, "runner_secrets.json"), "{}")
    print("đã tạo pepper, admin_token, runner_secrets.json trong", root)


def new_runner(root, provider, label):
    if provider not in ("claude", "grok", "agy") or not LABEL_RE.match(label):
        sys.exit("Hãng phải là claude|grok|agy; nhãn chỉ gồm chữ thường, số và gạch ngang.")
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
    key = label.replace("-", "_")
    print(f"Đã tạo {d}/auth (quyền 0700) và bí mật runner.\nThêm vào deploy/docker-compose.yml: dịch vụ `runner-{label}` (sao từ mẫu {provider}), volume ${{SECRETS_DIR}}/{label}/auth:/auth,"
          f" secret `runner_secret_{key}` -> file ${{SECRETS_DIR}}/{label}/runner.secret.\n"
          f"Bước tiếp theo (làm TAY): đăng nhập tài khoản vào thư mục auth (xem docs), rồi tạo bản ghi tài khoản trong giao diện điều khiển với nhãn `{label}` và địa chỉ runner http://runner-{label}:8800.")


if __name__ == "__main__":
    a = sys.argv[1:]
    if a[:1] == ["init"] and len(a) == 4 and a[2] == "--pepper-file":
        init(a[1], a[3])
    elif a[:1] == ["new-runner"] and len(a) == 4:
        new_runner(a[1], a[2], a[3])
    else:
        sys.exit(__doc__)
