import io
import json
import sys
import threading
import urllib.request
from urllib.error import HTTPError

import pytest

from conftest import SECRET
from runner.server import serve

BODY = {"request_id": "r1", "capability": "text", "model": "haiku", "system": "", "user": "Chào"}


@pytest.fixture
def http(make_service):
    svc = make_service("claude")
    srv = serve(svc)
    threading.Thread(target=srv.serve_forever, daemon=True).start()
    base = f"http://127.0.0.1:{srv.server_address[1]}"

    def call(method, path, body=None, token=SECRET, raw=None, headers=None):
        data = raw if raw is not None else (json.dumps(body).encode() if body is not None else None)
        req = urllib.request.Request(base + path, data=data, method=method)
        if token:
            req.add_header("Authorization", "Bearer " + token)
        for k, v in (headers or {}).items():
            req.add_header(k, v)
        try:
            with urllib.request.urlopen(req, timeout=20) as r:
                return r.status, json.loads(r.read())
        except HTTPError as e:
            return e.code, json.loads(e.read() or b"{}")
    yield svc, call
    srv.shutdown()


def test_healthz_cong_khai_nhung_khong_lo_gi(http):
    svc, call = http
    code, b = call("GET", "/healthz", token=None)
    assert code == 200 and b == {"ok": True, "provider": "claude", "tripped": False, "inflight": 0}


@pytest.mark.parametrize("tok", [None, "sai", SECRET[:-1], SECRET + "x", ""])
def test_thieu_hoac_sai_bi_mat_bi_tu_choi(http, tok):
    _, call = http
    assert call("POST", "/v1/run", BODY, token=tok)[0] == 401
    assert call("GET", "/v1/info", token=tok)[0] == 401


def test_chay_thanh_cong_qua_http(http):
    _, call = http
    code, b = call("POST", "/v1/run", BODY)
    assert code == 200 and b["ok"] and b["text"] == "xin chào"


def test_thân_qua_lon_bi_413(http):
    svc, call = http
    code, b = call("POST", "/v1/run", raw=b"x" * (svc.cfg.max_body + 10))
    assert code == 413


def test_json_hong_va_duong_dan_la(http):
    _, call = http
    assert call("POST", "/v1/run", raw=b"{khong phai json")[0] == 400
    assert call("POST", "/v1/run", raw=b"[1,2]")[0] == 400
    assert call("POST", "/v1/khac", BODY)[0] == 404
    assert call("GET", "/v1/info")[0] == 200


def test_het_cho_song_song_tra_503_busy(make_service):
    svc = make_service("claude", max_concurrency=1)
    assert svc.sem.acquire(blocking=False)
    code, b = svc.execute({**BODY})
    assert code == 503 and b["error_class"] == "busy"
    svc.sem.release()
    assert svc.execute({**BODY})[0] == 200


def test_log_khong_chua_noi_dung(http, capfd):
    _, call = http
    call("POST", "/v1/run", {**BODY, "user": "CHUOI-RIENG-TU-KHONG-DUOC-LOG-9931"})
    out, err = capfd.readouterr()
    assert "CHUOI-RIENG-TU-KHONG-DUOC-LOG-9931" not in out + err
