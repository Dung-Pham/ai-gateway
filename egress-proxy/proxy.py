#!/usr/bin/env python3
"""Proxy CONNECT với DANH SÁCH CHO PHÉP tên miền: lớp chặn rò dữ liệu của runner (docs/CACH_LY_MAY_CHU.md, L2).

- Chỉ nhận CONNECT host:port nằm trong allowlist (khớp đúng hoặc "*.miền"); mọi thứ khác bị từ chối và ghi lại.
- Từ chối IP trần; sau khi phân giải tên, từ chối nếu BẤT KỲ địa chỉ nào là riêng/loopback/link-local/metadata (chống SSRF, DNS rebinding).
- Kết nối tới địa chỉ ĐÃ phân giải và kiểm tra (không phân giải lại), có hạn thời gian và số kết nối.
- Không giải mã TLS, không ghi nội dung; chỉ ghi "host:port" và kết quả.
"""
import ipaddress
import os
import select
import socket
import sys
import threading
import time

BLOCKED_NETS = [ipaddress.ip_network(n) for n in (
    "0.0.0.0/8", "10.0.0.0/8", "100.64.0.0/10", "127.0.0.0/8", "169.254.0.0/16", "172.16.0.0/12", "192.0.0.0/24",
    "192.168.0.0/16", "198.18.0.0/15", "224.0.0.0/4", "240.0.0.0/4", "::1/128", "fc00::/7", "fe80::/10", "ff00::/8", "::/128")]


def load_allowlist(text: str) -> list[tuple[str, int]]:
    out = []
    for raw in text.splitlines():
        line = raw.split("#", 1)[0].strip().lower()
        if not line:
            continue
        host, _, port = line.rpartition(":") if ":" in line else (line, "", "443")
        out.append((host, int(port or 443)))
    return out


def host_allowed(host: str, port: int, allow: list[tuple[str, int]]) -> bool:
    host = host.lower().rstrip(".")
    for h, p in allow:
        if p != port:
            continue
        if h == host or (h.startswith("*.") and host.endswith(h[1:]) and host != h[2:]):
            return True
    return False


def is_ip_literal(host: str) -> bool:
    try:
        ipaddress.ip_address(host.strip("[]"))
        return True
    except ValueError:
        return False


def blocked_address(addr: str) -> bool:
    ip = ipaddress.ip_address(addr)
    if ip.version == 6 and ip.ipv4_mapped:
        ip = ip.ipv4_mapped
    return any(ip in n for n in BLOCKED_NETS)


def resolve_safe(host: str, port: int, resolver=socket.getaddrinfo):
    """Trả địa chỉ an toàn đầu tiên; ném PermissionError nếu có địa chỉ riêng/nội bộ lẫn trong kết quả."""
    infos = resolver(host, port, type=socket.SOCK_STREAM)
    addrs = [i[4][0] for i in infos]
    if not addrs:
        raise OSError("không phân giải được")
    if any(blocked_address(a) for a in addrs):
        raise PermissionError("tên miền phân giải về địa chỉ nội bộ")
    return infos[0]


def parse_connect(head: bytes):
    line = head.split(b"\r\n", 1)[0].decode("latin-1")
    parts = line.split()
    if len(parts) != 3 or parts[0] != "CONNECT" or not parts[2].startswith("HTTP/1."):
        return None
    host, _, port = parts[1].rpartition(":")
    if not host or not port.isdigit():
        return None
    return host, int(port)


class Proxy:
    def __init__(self, allow, *, resolver=socket.getaddrinfo, max_conns=64, idle_s=120, log=None):
        self.allow, self.resolver, self.idle_s = allow, resolver, idle_s
        self.sem = threading.BoundedSemaphore(max_conns)
        self.log = log or (lambda m: print(m, file=sys.stderr, flush=True))

    def decide(self, host, port):
        """Trả (cho_phép, lý_do, thông_tin_địa_chỉ|None)."""
        if is_ip_literal(host):
            return False, "ip_tran", None
        if not host_allowed(host, port, self.allow):
            return False, "khong_trong_allowlist", None
        try:
            return True, "ok", resolve_safe(host, port, self.resolver)
        except PermissionError:
            return False, "dia_chi_noi_bo", None
        except OSError:
            return False, "khong_phan_giai_duoc", None

    def handle(self, client: socket.socket):
        client.settimeout(15)
        try:
            head = b""
            while b"\r\n\r\n" not in head and len(head) < 8192:
                chunk = client.recv(4096)
                if not chunk:
                    return
                head += chunk
            target = parse_connect(head)
            if target is None:
                client.sendall(b"HTTP/1.1 405 Method Not Allowed\r\n\r\n")
                self.log("TU_CHOI khong_phai_CONNECT")
                return
            host, port = target
            ok, why, info = self.decide(host, port)
            if not ok:
                client.sendall(b"HTTP/1.1 403 Forbidden\r\n\r\n")
                self.log(f"TU_CHOI {host}:{port} {why}")
                return
            if not self.sem.acquire(blocking=False):
                client.sendall(b"HTTP/1.1 503 Service Unavailable\r\n\r\n")
                return
            try:
                fam, typ, proto, _c, addr = info
                up = socket.socket(fam, typ, proto)
                up.settimeout(15)
                up.connect(addr)
                client.sendall(b"HTTP/1.1 200 Connection Established\r\n\r\n")
                self.log(f"CHO_PHEP {host}:{port}")
                self.pipe(client, up)
                up.close()
            except OSError:
                try:
                    client.sendall(b"HTTP/1.1 502 Bad Gateway\r\n\r\n")
                except OSError:
                    pass
            finally:
                self.sem.release()
        except OSError:
            pass
        finally:
            client.close()

    def pipe(self, a, b):
        socks, last = [a, b], time.monotonic()
        a.settimeout(None); b.settimeout(None)
        while time.monotonic() - last < self.idle_s:
            r, _, _ = select.select(socks, [], [], 5)
            for s in r:
                data = s.recv(65536)
                if not data:
                    return
                (b if s is a else a).sendall(data)
                last = time.monotonic()


def serve(proxy: Proxy, host="0.0.0.0", port=3128):
    srv = socket.socket()
    srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    srv.bind((host, port))
    srv.listen(128)
    proxy.log(f"egress-proxy nghe tại {host}:{port}; allowlist: {', '.join(f'{h}:{p}' for h, p in proxy.allow)}")
    while True:
        c, _ = srv.accept()
        threading.Thread(target=proxy.handle, args=(c,), daemon=True).start()


if __name__ == "__main__":
    allow = load_allowlist(open(os.environ["EGRESS_ALLOWLIST_FILE"], encoding="utf-8").read())
    if not allow:
        sys.exit("Allowlist rỗng: từ chối khởi động.")
    serve(Proxy(allow), port=int(os.environ.get("EGRESS_PORT", "3128")))
