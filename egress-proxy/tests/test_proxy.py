import os
import socket
import sys
import threading

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))
import proxy as P  # noqa: E402

ALLOW = P.load_allowlist("""
# hãng AI (ví dụ)
api.anthropic.com:443
cli-chat-proxy.grok.com
*.googleapis.com:443
""")


def public(host, port, **kw):
    return [(socket.AF_INET, socket.SOCK_STREAM, 6, "", ("93.184.216.34", port))]


def test_load_allowlist_bo_chu_thich_va_mac_dinh_443():
    assert ("api.anthropic.com", 443) in ALLOW and ("cli-chat-proxy.grok.com", 443) in ALLOW and len(ALLOW) == 3


@pytest.mark.parametrize("host,port,ok", [
    ("api.anthropic.com", 443, True), ("API.Anthropic.com.", 443, True), ("api.anthropic.com", 80, False),
    ("anthropic.com", 443, False), ("evil-api.anthropic.com.evil.com", 443, False), ("x.api.anthropic.com", 443, False),
    ("a.googleapis.com", 443, True), ("a.b.googleapis.com", 443, True), ("googleapis.com", 443, False), ("notgoogleapis.com", 443, False),
    ("metadata.google.internal", 443, False), ("", 443, False)])
def test_so_khop_allowlist(host, port, ok):
    assert P.host_allowed(host, port, ALLOW) is ok


@pytest.mark.parametrize("ip", ["127.0.0.1", "10.1.2.3", "172.16.0.9", "172.31.255.255", "192.168.1.1", "169.254.169.254", "100.64.0.1",
                                "0.0.0.0", "::1", "fe80::1", "fc00::1", "::ffff:10.0.0.1", "::ffff:169.254.169.254", "224.0.0.1"])
def test_dia_chi_noi_bo_bi_chan(ip):
    assert P.blocked_address(ip) is True


@pytest.mark.parametrize("ip", ["93.184.216.34", "8.8.8.8", "172.32.0.1", "2606:4700::1"])
def test_dia_chi_cong_khai_khong_bi_chan(ip):
    assert P.blocked_address(ip) is False


def test_ip_tran_bi_tu_choi_ke_ca_khi_dang_trong_allowlist():
    px = P.Proxy(P.load_allowlist("127.0.0.1:443\n10.0.0.5:443"), resolver=public)
    assert px.decide("127.0.0.1", 443) == (False, "ip_tran", None)
    assert px.decide("[::1]", 443)[1] == "ip_tran"


def test_dns_tro_ve_noi_bo_bi_chan_ke_ca_khi_chi_co_mot_dia_chi_xau():
    mixed = lambda h, p, **k: public(h, p) + [(socket.AF_INET, socket.SOCK_STREAM, 6, "", ("169.254.169.254", p))]
    assert P.Proxy(ALLOW, resolver=mixed).decide("api.anthropic.com", 443)[:2] == (False, "dia_chi_noi_bo")
    internal = lambda h, p, **k: [(socket.AF_INET, socket.SOCK_STREAM, 6, "", ("10.0.0.5", p))]
    assert P.Proxy(ALLOW, resolver=internal).decide("api.anthropic.com", 443)[:2] == (False, "dia_chi_noi_bo")


def test_ten_khong_phan_giai_duoc():
    def boom(h, p, **k):
        raise socket.gaierror("x")
    assert P.Proxy(ALLOW, resolver=boom).decide("api.anthropic.com", 443)[:2] == (False, "khong_phan_giai_duoc")


@pytest.mark.parametrize("head,ok", [(b"CONNECT a.com:443 HTTP/1.1\r\n\r\n", True), (b"GET / HTTP/1.1\r\n\r\n", False), (b"CONNECT a.com HTTP/1.1\r\n\r\n", False),
                                     (b"CONNECT a.com:xx HTTP/1.1\r\n\r\n", False), (b"CONNECT :443 HTTP/1.1\r\n\r\n", False), (b"CONNECT a.com:443\r\n\r\n", False)])
def test_chi_nhan_connect_dung_cu_phap(head, ok):
    assert (P.parse_connect(head) is not None) is ok


def _request(px, raw: bytes) -> bytes:
    a, b = socket.socketpair()
    t = threading.Thread(target=px.handle, args=(b,), daemon=True); t.start()
    a.sendall(raw); a.settimeout(5)
    out = b""
    try:
        out = a.recv(200)
    except OSError:
        pass
    a.close(); t.join(5)
    return out


def test_tu_choi_khong_ghi_noi_dung_va_tra_403():
    logs = []
    px = P.Proxy(ALLOW, resolver=public, log=logs.append)
    assert _request(px, b"CONNECT evil.com:443 HTTP/1.1\r\n\r\n").startswith(b"HTTP/1.1 403")
    assert _request(px, b"CONNECT 169.254.169.254:80 HTTP/1.1\r\n\r\n").startswith(b"HTTP/1.1 403")
    assert _request(px, b"GET http://api.anthropic.com/ HTTP/1.1\r\n\r\n").startswith(b"HTTP/1.1 405")
    assert any("evil.com:443 khong_trong_allowlist" in l for l in logs) and any("ip_tran" in l for l in logs)


def test_cho_phep_chuyen_tiep_byte_den_dung_dia_chi_da_kiem_tra():
    """Đích giả cục bộ: bỏ kiểm tra địa chỉ riêng chỉ trong test để thử việc chuyển tiếp thật."""
    srv = socket.socket(); srv.bind(("127.0.0.1", 0)); srv.listen(1); port = srv.getsockname()[1]
    got = []

    def echo():
        c, _ = srv.accept(); got.append(c.recv(100)); c.sendall(b"PONG"); c.close()
    threading.Thread(target=echo, daemon=True).start()
    loop = lambda h, p, **k: [(socket.AF_INET, socket.SOCK_STREAM, 6, "", ("127.0.0.1", port))]
    px = P.Proxy(P.load_allowlist(f"api.anthropic.com:443"), resolver=loop)
    px_orig = P.blocked_address
    P.blocked_address = lambda a: False   # chỉ trong test này
    try:
        a, b = socket.socketpair()
        threading.Thread(target=px.handle, args=(b,), daemon=True).start()
        a.sendall(b"CONNECT api.anthropic.com:443 HTTP/1.1\r\n\r\n"); a.settimeout(5)
        assert a.recv(100).startswith(b"HTTP/1.1 200")
        a.sendall(b"PING"); assert a.recv(10) == b"PONG" and got == [b"PING"]
    finally:
        P.blocked_address = px_orig


def test_het_cho_ket_noi_tra_503():
    px = P.Proxy(ALLOW, resolver=public, max_conns=1)
    px.sem.acquire()
    assert _request(px, b"CONNECT api.anthropic.com:443 HTTP/1.1\r\n\r\n").startswith(b"HTTP/1.1 503")
