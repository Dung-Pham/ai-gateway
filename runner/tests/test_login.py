import json
import os
import time

import pytest

from runner.errors import RunnerError


def wait_state(svc, sid, states, timeout=20):
    t = time.time()
    while time.time() - t < timeout:
        v = svc.login.status(sid)
        if v["state"] in states:
            return v
        time.sleep(0.05)
    raise AssertionError(f"không tới trạng thái {states}: {svc.login.status(sid)}")


def auth_file(svc, rel=".credentials.json"):
    return svc.tmp_path / "auth" / rel


# ---------------------------------------------------------------- Claude: dán mã
def test_claude_bat_dau_tra_duong_dan_va_cho_ma(make_service):
    svc = make_service("claude")
    v = svc.login.start()
    assert v["ok"] and v["kind"] == "paste" and v["state"] == "awaiting_code"
    assert v["url"].startswith("https://claude.com/cai/oauth/authorize") and v["device_code"] is None
    svc.login.cancel(v["session_id"])


def test_claude_ma_dung_ghi_token_0600_va_don_thu_muc_tam(make_service):
    svc = make_service("claude")
    v = svc.login.start()
    svc.login.submit(v["session_id"], "GOODCODE-123456")
    done = wait_state(svc, v["session_id"], {"done", "failed"})
    assert done["state"] == "done"
    f = auth_file(svc)
    assert json.loads(f.read_text())["claudeAiOauth"]["accessToken"] == "NEW-ACCESS"
    assert oct(os.stat(f).st_mode & 0o777) == "0o600"
    time.sleep(0.3)
    root = str(svc.cfg.work_root)
    assert [d for d in os.listdir(root) if d.startswith("login-")] == []        # thư mục tạm đã xóa


def test_claude_ma_sai_thi_van_cho_va_cho_thu_lai(make_service):
    svc = make_service("claude")
    v = svc.login.start()
    svc.login.submit(v["session_id"], "mot-ma-sai-hoan-toan-1")
    w = wait_state(svc, v["session_id"], {"awaiting_code", "failed"})
    assert w["state"] == "awaiting_code" and "Mã không đúng" in w["message"]
    svc.login.submit(v["session_id"], "GOODCODE-123456")
    assert wait_state(svc, v["session_id"], {"done", "failed"})["state"] == "done"


def test_ma_dan_khong_hop_le_bi_tu_choi_va_khong_vao_cli(make_service):
    svc = make_service("claude")
    v = svc.login.start()
    for bad in ("", "ngan", "co khoang trang trong ma 12345", "ma\nxuong dong 12345678", "x" * 700, "<script>alert(1)</script>"):
        with pytest.raises(RunnerError) as e:
            svc.login.submit(v["session_id"], bad)
        assert e.value.error_class == "bad_request"
    assert svc.login.status(v["session_id"])["attempts"] == 0                    # chưa lần nào tới CLI
    svc.login.cancel(v["session_id"])


def test_toi_da_5_lan_nhap_sai(make_service):
    svc = make_service("claude")
    v = svc.login.start()
    for i in range(5):
        svc.login.submit(v["session_id"], f"ma-sai-khong-dung-{i}-abcdef")
        wait_state(svc, v["session_id"], {"awaiting_code"})
    with pytest.raises(RunnerError):
        svc.login.submit(v["session_id"], "ma-sai-khong-dung-6-abcdef")
    assert svc.login.status(v["session_id"])["state"] == "failed"


def test_ma_nguoi_dung_khong_lo_ra_phan_hoi_va_ghi_log(make_service, capfd):
    svc = make_service("claude")
    v = svc.login.start()
    secret = "BI-MAT-KHONG-DUOC-LO-98765"
    svc.login.submit(v["session_id"], secret)
    w = wait_state(svc, v["session_id"], {"awaiting_code", "failed"})
    assert secret not in json.dumps(w) and secret not in json.dumps(svc.login.status(v["session_id"]))
    out = capfd.readouterr()
    assert secret not in out.out and secret not in out.err
    svc.login.cancel(v["session_id"])


# ---------------------------------------------------------------- phiên: một cái một lúc, hết hạn, hủy
def test_bat_dau_phien_moi_huy_phien_cu_va_id_cu_khong_dung_duoc(make_service):
    svc = make_service("claude")
    a = svc.login.start()
    b = svc.login.start()
    assert a["session_id"] != b["session_id"]
    with pytest.raises(RunnerError):
        svc.login.status(a["session_id"])
    svc.login.cancel(b["session_id"])
    assert svc.login.status(b["session_id"])["state"] == "cancelled"
    with pytest.raises(RunnerError):
        svc.login.submit(b["session_id"], "GOODCODE-123456")                     # đã hủy: không nhận mã


def test_het_han_thi_that_bai_va_giet_cli(make_service):
    svc = make_service("claude")
    svc.login.ttl_s = 1
    v = svc.login.start()
    w = wait_state(svc, v["session_id"], {"failed"}, timeout=6)
    assert "Quá thời gian" in w["message"]


# ---------------------------------------------------------------- Codex / Grok: mã thiết bị
@pytest.mark.parametrize("prov,rel,code,host", [("codex", "auth.json", "ABCD-EFG12", "auth.openai.com"), ("grok", "auth.json", "WXYZ-1234", "accounts.x.ai")])
def test_thiet_bi_hien_ma_va_tu_hoan_tat_khi_nguoi_dung_xac_nhan(make_service, monkeypatch, tmp_path, prov, rel, code, host):
    approve = tmp_path / "approve"
    monkeypatch.setenv("STUB_APPROVE", str(approve))
    svc = make_service(prov)
    v = svc.login.start()
    assert v["kind"] == "device" and v["device_code"] == code and host in v["url"] and v["state"] == "awaiting_device"
    with pytest.raises(RunnerError):
        svc.login.submit(v["session_id"], "GOODCODE-123456")                      # luồng thiết bị không nhận mã dán
    approve.write_text("ok")
    assert wait_state(svc, v["session_id"], {"done", "failed"})["state"] == "done"
    assert json.loads(auth_file(svc, rel).read_text())["tokens"]["access_token"] == "NEW-ACCESS"


def test_thiet_bi_het_han_la_that_bai(make_service, monkeypatch):
    monkeypatch.setenv("STUB_DEVICE_WAIT", "0.3")
    svc = make_service("codex")
    v = svc.login.start()
    w = wait_state(svc, v["session_id"], {"failed"})
    assert "chưa hoàn tất" in w["message"]


# ---------------------------------------------------------------- an ninh
def test_duong_dan_la_khong_thuoc_ten_mien_hang_bi_chan(make_service, monkeypatch):
    monkeypatch.setenv("STUB_LOGIN_URL", "If the browser didn't open, visit: https://evil.example.org/phish?x=1")
    svc = make_service("claude")
    with pytest.raises(RunnerError):
        svc.login.start()


def test_hang_chua_ho_tro_tra_loi_ro_rang(make_service):
    svc = make_service("agy")
    with pytest.raises(RunnerError) as e:
        svc.login.start()
    assert e.value.error_class == "unsupported" and "terminal" in e.value.message


def test_cli_chay_voi_moi_truong_toi_thieu_va_trong_thu_muc_tam(make_service):
    svc = make_service("claude")
    v = svc.login.start()
    c = [json.loads(l) for l in svc.log.read_text().splitlines()][-1]
    assert c["provider"] == "claude-login" and "RUNNER_SECRET_FILE" not in c["env_keys"] and c["cwd"].startswith(str(svc.cfg.work_root))
    svc.login.cancel(v["session_id"])


def test_thong_diep_loi_che_chuoi_giong_token(make_service):
    from runner.login import redact
    assert "abcdefghijklmnopqrstuvwxyz0123456789ABCD" not in redact("loi: abcdefghijklmnopqrstuvwxyz0123456789ABCD het han")
