import logging

import pytest

from conftest import KEY_A


def code(r):
    return r.json()["error"]["code"]


# ---------------------------------------------------------------- hạn mức theo ứng dụng
def test_vuot_rpm_thi_chan(env):
    env.limit(rpm=2)
    assert env.post().status_code == 200 and env.post().status_code == 200
    r = env.post()
    assert r.status_code == 429 and code(r) == "app_rpm" and "retry-after" in r.headers
    assert len(env.runner.calls) == 2


def test_rpm_mem_theo_cua_so_phut(env):
    env.limit(rpm=1)
    assert env.post().status_code == 200
    assert env.post().status_code == 429
    env.clock.e += 61
    assert env.post().status_code == 200


def test_vuot_song_song_cua_ung_dung(env):
    env.limit(conc=2)
    env.app.state.gw_state.app_inflight[1] = 2
    assert code(env.post()) == "app_concurrency"


def _prior_usage(env, units):
    env.db.execute("insert into usage_events(ts,app_id,account_id,capability,model,weighted_units,status,request_id) values (?,?,?,?,?,?,?,?)",
                   ("2026-11-19 08:00:00", 1, 1, "text", "haiku", units, "ok", "req_old")); env.db.commit()


def test_het_han_muc_ngay_cung(env):
    env.limit(day=100); _prior_usage(env, 100)
    r = env.post()
    assert r.status_code == 429 and code(r) == "app_daily_units" and int(r.headers["retry-after"]) > 0 and env.runner.calls == []


def test_han_muc_mem_chi_canh_bao(env):
    env.limit(day=100, mode="soft"); _prior_usage(env, 500)
    r = env.post()
    assert r.status_code == 200 and "soft_limit_exceeded:app_daily_units" in r.headers["x-gateway-warning"]


def test_han_muc_chi_tinh_luot_thanh_cong_va_dung_ngay(env):
    env.limit(day=100)
    env.db.execute("insert into usage_events(ts,app_id,account_id,capability,model,weighted_units,status,request_id) values ('2026-11-19 08:00:00',1,1,'text','haiku',999,'error','r1'),"
                   "('2026-11-18 23:00:00',1,1,'text','haiku',999,'ok','r2')"); env.db.commit()
    assert env.post().status_code == 200   # lượt lỗi và lượt hôm qua không tính


def test_han_muc_thang(env):
    env.limit(month=50)
    env.db.execute("insert into usage_events(ts,app_id,account_id,capability,model,weighted_units,status,request_id) values ('2026-11-02 08:00:00',1,1,'text','haiku',50,'ok','r1')"); env.db.commit()
    assert code(env.post()) == "app_monthly_units"


def test_han_muc_khac_ung_dung_khong_anh_huong(env):
    env.limit(app=4, rpm=1, day=1)
    assert env.post().status_code == 200 and env.post().status_code == 200


# ---------------------------------------------------------------- định tuyến
def test_chon_tai_khoan_theo_hang_va_pool(env):
    r = env.post("claude:haiku")
    assert r.status_code == 200 and r.headers["x-gateway-account"] == "claude-01"
    assert env.runner.calls[0][1] == "http://runner-claude-01:8800" and env.runner.calls[0][2] == "x" * 40


def test_dung_pool_du_phong_khi_hang_khong_co_o_pool_chinh(env):
    r = env.post("grok:grok-4.7")
    assert r.status_code == 200 and r.headers["x-gateway-account"] == "grok-01"


def test_bo_qua_tai_khoan_khong_active(env):
    env.db.execute("update accounts set status='paused' where label='claude-01'"); env.db.commit()
    assert env.post().headers["x-gateway-account"] == "claude-02"


def test_tat_ca_tai_khoan_khong_san_sang_thi_503(env):
    env.db.execute("update accounts set status='auth_expired' where provider_id=1"); env.db.commit()
    r = env.post()
    assert r.status_code == 503 and code(r) == "no_account_available" and env.runner.calls == []


def test_least_used_uu_tien_tai_khoan_it_ban_hon(env):
    env.app.state.gw_state.inflight["claude-01"] = 1
    assert env.post().headers["x-gateway-account"] == "claude-02"


def test_tai_khoan_het_cho_song_song_bi_bo_qua(env):
    env.app.state.gw_state.inflight["claude-01"] = 2   # = max_concurrency
    assert env.post().headers["x-gateway-account"] == "claude-02"


@pytest.mark.parametrize("url", ["http://169.254.169.254:80", "http://127.0.0.1:8800", "http://10.0.0.5:8800", "https://runner-claude-01:8800",
                                 "http://runner-claude-01:8800/admin", "http://user:pw@runner-claude-01:8800", "http://evil.example.com:8800",
                                 "http://runner-claude-01:80", ""])
def test_runner_url_xau_khong_bao_gio_duoc_goi(env, url):
    env.db.execute("update accounts set runner_url=? where provider_id=1", (url,)); env.db.commit()
    r = env.post()
    assert r.status_code == 503 and env.runner.calls == []


def test_thieu_bi_mat_runner_thi_bo_qua_tai_khoan(env):
    del env.settings.runner_secrets["claude-01"]
    assert env.post().headers["x-gateway-account"] == "claude-02"


# ---------------------------------------------------------------- lỗi từ runner
def test_rate_limited_thi_nghi_va_thu_tai_khoan_khac(env):
    env.runner.script["claude-01"] = [env.runner.fail("rate_limited", quota={"five_hour": {"resets_at": 1_795_088_400}})]
    r = env.post()
    assert r.status_code == 200 and r.headers["x-gateway-account"] == "claude-02"
    assert [e["status"] for e in env.events()] == ["rate_limited", "ok"]
    assert env.app.state.gw_state.cooldown_until["claude-01"] == 1_795_088_400   # theo giờ reset do hãng báo
    assert env.post().headers["x-gateway-account"] == "claude-02"                  # claude-01 đang nghỉ


def test_nghi_mac_dinh_khi_khong_co_gio_reset(env):
    env.runner.script["claude-01"] = [env.runner.fail("rate_limited")]
    env.post()
    assert env.app.state.gw_state.cooldown_until["claude-01"] == env.clock.e + env.settings.default_cooldown_s


def test_het_nghi_thi_dung_lai(env):
    env.runner.script["claude-01"] = [env.runner.fail("rate_limited")]
    env.post(); env.clock.e += env.settings.default_cooldown_s + 1
    assert env.post().headers["x-gateway-account"] == "claude-01"


def test_tat_ca_bi_gioi_han_thi_429(env):
    env.runner.script["claude-01"] = [env.runner.fail("rate_limited")]; env.runner.script["claude-02"] = [env.runner.fail("rate_limited")]
    r = env.post()
    assert r.status_code == 429 and code(r) == "all_accounts_rate_limited"


def test_het_phien_dang_nhap_thi_loai_tai_khoan(env):
    env.runner.script["claude-01"] = [env.runner.fail("auth_expired")]
    assert env.post().headers["x-gateway-account"] == "claude-02"
    assert env.app.state.gw_state.flags["claude-01"] == "auth_expired"
    assert env.post().headers["x-gateway-account"] == "claude-02"


def test_chi_thu_lai_toi_da_mot_lan(env):
    env.runner.script["claude-01"] = [env.runner.fail("rate_limited")]; env.runner.script["claude-02"] = [env.runner.fail("auth_expired")]
    env.db.execute("update pool_members set pool_id=1 where account_id=3"); env.db.commit()
    r = env.post()
    assert len(env.runner.calls) == 2 and r.status_code in (429, 503)


@pytest.mark.parametrize("cls,status,c", [("timeout", 504, "upstream_timeout"), ("upstream_error", 502, "upstream_error"),
                                          ("tool_denied", 502, "empty_response"), ("policy_violation", 502, "upstream_policy_violation")])
def test_loi_khong_thu_lai_va_anh_xa_ma(env, cls, status, c):
    env.runner.script["claude-01"] = [env.runner.fail(cls)]
    r = env.post()
    assert r.status_code == status and code(r) == c and len(env.runner.calls) == 1


def test_vi_pham_chinh_sach_thi_loai_tai_khoan(env):
    env.runner.script["claude-01"] = [env.runner.fail("policy_violation")]
    env.post()
    assert env.app.state.gw_state.flags["claude-01"] == "tripped"
    assert env.post().headers["x-gateway-account"] == "claude-02"


# ---------------------------------------------------------------- đo lường và đầu ra
def test_dinh_dang_openai_va_dau_trang(env):
    r = env.post("claude:haiku")
    b = r.json()
    assert b["object"] == "chat.completion" and b["model"] == "claude:haiku" and b["choices"][0]["message"] == {"role": "assistant", "content": "xin chào"}
    assert b["usage"] == {"prompt_tokens": 3839, "completion_tokens": 6, "total_tokens": 3845}
    assert r.headers["x-gateway-request-id"].startswith("req_") and r.headers["x-gateway-account"] == "claude-01"


def test_don_vi_quy_doi_theo_he_so_model(env):
    r = env.post("claude:haiku")   # (3839 + 6*3)/100 * 0.25 = 9.6425 -> 10
    assert r.headers["x-gateway-units"] == "10"
    assert env.events() == [{"account": "claude-01", "status": "ok", "error_class": None, "in": 3839, "out": 6, "w": 10, "model": "haiku"}]
    r2 = env.post("claude:sonnet")   # he so 1 -> 38.57 -> 39
    assert r2.headers["x-gateway-units"] == "39"


def test_model_khong_co_he_so_thi_dung_1(env):
    env.db.execute("delete from model_weights where model='sonnet'"); env.db.commit()
    assert env.post("claude:sonnet").headers["x-gateway-units"] == "39"


def test_nhat_ky_loi_khong_co_don_vi(env):
    env.runner.script["claude-01"] = [env.runner.fail("upstream_error")]
    env.post()
    e = env.events()[0]
    assert e["status"] == "error" and e["error_class"] == "upstream_error" and (e["in"], e["out"], e["w"]) == (0, 0, 0)


def test_cap_nhat_last_used_at(env):
    env.post()
    assert env.db.execute("select last_used_at from app_keys where key_prefix=?", (KEY_A[:11],)).fetchone()[0] is not None


def test_loi_ghi_nhat_ky_khong_lam_hong_cau_tra_loi(env):
    env.app.state.store.insert_event = lambda **kw: (_ for _ in ()).throw(RuntimeError("db down"))
    assert env.post().status_code == 200 and env.app.state.gw_state.counters["metering_failed_total"] == 1


def test_noi_dung_khong_bao_gio_vao_log_hay_csdl(env, caplog):
    caplog.set_level(logging.DEBUG)
    env.runner.script["claude-01"] = [env.runner.fail("upstream_error")]
    env.post(content="CHUOI-BI-MAT-KHONG-DUOC-GHI-7731"); env.post(content="CHUOI-BI-MAT-KHONG-DUOC-GHI-7731")
    assert "CHUOI-BI-MAT-KHONG-DUOC-GHI-7731" not in caplog.text
    for line in env.db.iterdump():
        assert "CHUOI-BI-MAT-KHONG-DUOC-GHI-7731" not in line


# ---------------------------------------------------------------- quản trị nội bộ
def test_noi_bo_can_token(env):
    assert env.client.get("/internal/state").status_code == 401
    assert env.client.get("/internal/state", headers={"X-Admin-Token": "sai"}).status_code == 401
    assert env.client.get("/metrics").status_code == 401
    assert env.client.post("/internal/accounts/claude-01/reset").status_code == 401


def test_noi_bo_tat_khi_khong_cau_hinh_token(env):
    from dataclasses import replace
    from fastapi.testclient import TestClient
    from gateway.app import create_app
    app = create_app(replace(env.settings, admin_token=None), runner_client=env.runner, clock=env.clock)
    assert TestClient(app).get("/internal/state", headers={"X-Admin-Token": "a" * 40}).status_code == 404


def test_trang_thai_noi_bo_cong_bo_quota_va_reset(env):
    from conftest import ADMIN
    env.runner.script["claude-01"] = [env.runner.fail("auth_expired")]
    env.post(); env.post()
    h = {"X-Admin-Token": ADMIN}
    s = env.client.get("/internal/state", headers=h).json()
    assert s["accounts"]["claude-01"]["flag"] == "auth_expired" and s["accounts"]["claude-02"]["quota"]["five_hour"]["utilization"] == 0.39
    assert env.client.post("/internal/accounts/claude-01/reset", headers=h).json() == {"ok": True}
    assert env.post().headers["x-gateway-account"] == "claude-01"
    assert "gateway_requests_total" in env.client.get("/metrics", headers=h).text


def test_healthz_cong_khai(env):
    assert env.client.get("/healthz").json()["ok"] is True


# ---------------------------------------------------------------- "Kiểm tra ngay"
def test_kiem_tra_ngay_can_token_va_tai_khoan_ton_tai(env):
    from conftest import ADMIN
    assert env.client.post("/internal/accounts/claude-01/check").status_code == 401
    assert env.client.post("/internal/accounts/khong-co/check", headers={"X-Admin-Token": ADMIN}).status_code == 404


def test_kiem_tra_ngay_thanh_cong_go_co_het_phien_va_dung_model_re(env):
    from conftest import ADMIN
    h = {"X-Admin-Token": ADMIN}
    env.runner.script["claude-01"] = [env.runner.fail("auth_expired")]
    env.post(); env.post()
    assert env.client.get("/internal/state", headers=h).json()["accounts"]["claude-01"]["flag"] == "auth_expired"
    env.runner.calls.clear()
    r = env.client.post("/internal/accounts/claude-01/check", headers=h).json()
    assert r["ok"] is True and r["quota"]["five_hour"]["utilization"] == 0.39
    label, _url, _sec, payload = env.runner.calls[0]
    assert label == "claude-01" and payload["model"] == "haiku" and payload["capability"] == "text"
    st = env.client.get("/internal/state", headers=h).json()["accounts"]["claude-01"]
    assert st["flag"] is None and st["checked_at"] is not None
    assert not any(p["request_id"].startswith("chk_") for _, _, _, p in env.runner.calls[1:])


def test_kiem_tra_ngay_that_bai_dat_co_va_khong_ghi_usage(env):
    from conftest import ADMIN
    h = {"X-Admin-Token": ADMIN}
    before = len(env.events())
    env.runner.script["claude-02"] = [env.runner.fail("auth_expired")]
    r = env.client.post("/internal/accounts/claude-02/check", headers=h).json()
    assert r["ok"] is False and r["error_class"] == "auth_expired"
    assert env.client.get("/internal/state", headers=h).json()["accounts"]["claude-02"]["flag"] == "auth_expired"
    assert len(env.events()) == before
    env.runner.script["grok-01"] = [env.runner.fail("rate_limited", quota={"five_hour": {"utilization": 1, "resets_at": 4_000_000_000}})]
    assert env.client.post("/internal/accounts/grok-01/check", headers=h).json()["error_class"] == "rate_limited"
    assert env.client.get("/internal/state", headers=h).json()["accounts"]["grok-01"]["cooldown_s"] > 0
