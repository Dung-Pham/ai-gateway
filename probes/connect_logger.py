#!/usr/bin/env python3
"""Proxy CONNECT chỉ-ghi-tên-miền, dùng cho bước thăm dò P9.

Không giải mã TLS, không ghi nội dung: chỉ ghi "host:port" mà CLI xin kết nối rồi chuyển tiếp byte nguyên trạng.
Mục đích: biết chính xác CLI cần ra những tên miền nào để dựng danh sách cho phép (allowlist) cho runner.

Dùng:
  python3 probes/connect_logger.py --port 8899 --log probe-results/p9-hosts.txt
  HTTPS_PROXY=http://127.0.0.1:8899 HTTP_PROXY=http://127.0.0.1:8899 <chạy CLI>
Chỉ lắng nghe 127.0.0.1. Dừng bằng Ctrl+C.
"""
import argparse
import select
import socket
import threading
import time

LOCK = threading.Lock()


def log(path: str, line: str) -> None:
    with LOCK, open(path, "a", encoding="utf-8") as f:
        f.write(f"{time.strftime('%Y-%m-%dT%H:%M:%S')} {line}\n")


def pipe(a: socket.socket, b: socket.socket) -> None:
    socks = [a, b]
    try:
        while True:
            readable, _, _ = select.select(socks, [], [], 60)
            if not readable:
                return
            for s in readable:
                data = s.recv(65536)
                if not data:
                    return
                (b if s is a else a).sendall(data)
    except OSError:
        return


def handle(client: socket.socket, path: str) -> None:
    try:
        client.settimeout(15)
        head = b""
        while b"\r\n\r\n" not in head and len(head) < 8192:
            chunk = client.recv(4096)
            if not chunk:
                return
            head += chunk
        first = head.split(b"\r\n", 1)[0].decode("latin-1")
        parts = first.split()
        if len(parts) < 2 or parts[0].upper() != "CONNECT":
            log(path, f"BỎ_QUA (không phải CONNECT): {parts[0] if parts else '?'}")
            client.sendall(b"HTTP/1.1 405 Method Not Allowed\r\n\r\n")
            return
        host, _, port = parts[1].rpartition(":")
        log(path, f"CONNECT {host}:{port}")
        upstream = socket.create_connection((host, int(port)), timeout=15)
        client.sendall(b"HTTP/1.1 200 Connection Established\r\n\r\n")
        client.settimeout(None)
        pipe(client, upstream)
        upstream.close()
    except (OSError, ValueError) as exc:
        log(path, f"LỖI {type(exc).__name__}")
    finally:
        client.close()


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--port", type=int, default=8899)
    ap.add_argument("--log", default="probe-results/p9-hosts.txt")
    args = ap.parse_args()
    srv = socket.socket()
    srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    srv.bind(("127.0.0.1", args.port))
    srv.listen(64)
    print(f"Đang ghi tên miền vào {args.log} (127.0.0.1:{args.port}). Ctrl+C để dừng.")
    while True:
        c, _ = srv.accept()
        threading.Thread(target=handle, args=(c, args.log), daemon=True).start()


if __name__ == "__main__":
    main()
