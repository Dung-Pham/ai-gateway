import logging

import pytest

from conftest import ADMIN, KEY_A, KEY_B, KEY_C, KEY_REVOKED

ROWS = [{"title": "Huyết áp thấp là gì", "url": "https://www.vinmec.com/vi/a", "content": "Dưới 90/60 mmHg."},
        {"title": "Triệu chứng", "url": "https://suckhoedoisong.vn/b", "content": "Chóng mặt."}]


def code(r):
    return r.json()["error"]["code"]


def search(env, key=KEY_A, **body):
    b = {"query": "huyết áp thấp là gì", **body}
    return env.client.post("/v1/search", json=b, headers={"Authorization": f"Bearer {key}"} if key else {})


def ok_search(env, rows=ROWS, **over):
    return env.runner.ok(text="", results=rows, **over)


@pytest.fixture
def senv(env):
    env.runner.script["claude-01"] = lambda: ok_search(env)
    env.runner.script["claude-02"] = lambda: ok_search(env)
    return env


# ---------------------------------------------------------------- tương thích cầu nối cũ
def test_hinh_dang_phan_hoi_nhu_cau_noi_cu(senv):
    r = search(senv)
    assert r.status_code == 200 and r.json() == {"results": ROWS}
    assert r.headers["x-gateway-account"] == "claude-01" and r.headers["x-gateway-request-id"].startswith("req_")


def test_payload_gui_xuong_runner(senv):
    search(senv, query="  huyết áp  ", max_results=3, include_domains=["nhs.uk"], exclude_domains=["a.com"])
    label, url, secret, p = senv.runner.calls[0]
    assert p["capability"] == "web_search" and p["model"] == "haiku" and p["query"] == "huyết áp" and p["max_results"] == 3
    assert p["include_domains"] == ["nhs.uk"] and p["exclude_domains"] == ["a.com"] and "user" not in p and "messages" not in p


# ---------------------------------------------------------------- xác thực và chính sách
@pytest.mark.parametrize("key", [None, "gw_" + "9" * 40, KEY_REVOKED])
def test_khoa_sai(senv, key):
    assert search(senv, key=key).status_code == 401 and senv.runner.calls == []


def test_ung_dung_bi_ngung(senv):
    assert code(search(senv, key=KEY_B)) == "app_suspended"


@pytest.mark.parametrize("key", [KEY_C, "gw_" + "?" * 40])
def test_chua_cap_kha_nang_tim_web(senv, key):
    r = search(senv, key=key)
    assert r.status_code in (401, 403) and senv.runner.calls == []


def test_chi_co_van_ban_khong_co_tim_web(senv):
    senv.db.execute("update app_policies set enabled=0 where app_id=1 and capability_id=3"); senv.db.commit()
    r = search(senv)
    assert r.status_code == 403 and code(r) == "capability_not_granted"
    assert senv.post().status_code == 200           # văn bản vẫn chạy: hai khả năng độc lập


def test_khai_nang_tim_web_bi_tat_toan_he_thong(senv):
    senv.db.execute("update capabilities set enabled=0 where code='web_search'"); senv.db.commit()
    assert code(search(senv)) == "capability_not_granted"
    assert senv.post().status_code == 200


# ---------------------------------------------------------------- kiểm tra đầu vào
@pytest.mark.parametrize("body", [{"query": ""}, {"query": "   "}, {"query": "x" * 401}, {"query": 5}, {"query": None}, {"query": ["a"]}, {}, {"query": "a", "max_results": "abc"}])
def test_dau_vao_sai(senv, body):
    r = senv.client.post("/v1/search", json=body, headers={"Authorization": f"Bearer {KEY_A}"})
    assert r.status_code == 400 and senv.runner.calls == []


def test_json_hong_va_qua_lon(senv):
    h = {"Authorization": f"Bearer {KEY_A}"}
    assert senv.client.post("/v1/search", content=b"{khong", headers=h).status_code == 400
    assert senv.client.post("/v1/search", content=b"x" * 100_001, headers=h).status_code == 413


@pytest.mark.parametrize("given,sent", [(None, 5), (0, 5), (3, 3), (99, 8), (-4, 1), ("6", 6)])
def test_gioi_han_max_results(senv, given, sent):
    search(senv, **({} if given is None else {"max_results": given}))
    assert senv.runner.calls[0][3]["max_results"] == sent


def test_loc_ten_mien_nhu_cau_noi_cu_bo_phan_tu_sai(senv):
    search(senv, include_domains=["nhs.uk", "a b.com", "x/y", 5, "CDC.gov."], exclude_domains="khong-phai-danh-sach")
    p = senv.runner.calls[0][3]
    assert p["include_domains"] == ["nhs.uk", "cdc.gov"] and p["exclude_domains"] == []


# ---------------------------------------------------------------- gateway KHÔNG tin runner: kiểm tra lại kết quả
def test_loai_ket_qua_nguy_hiem_do_runner_tra_ve(senv):
    bad = [{"title": "x", "url": u, "content": "c"} for u in ("javascript:alert(1)", "http://169.254.169.254/x", "http://localhost/a", "file:///etc/passwd",
                                                                "http://u:p@evil.example.org/", "https://ok.example.org:8443/x")]
    senv.runner.script["claude-01"] = lambda: ok_search(senv, rows=bad + ROWS)
    assert search(senv).json()["results"] == ROWS


def test_gioi_han_so_ket_qua_va_khu_trung_ke_ca_khi_runner_tra_thua(senv):
    many = [{"title": f"t{i}", "url": f"https://s{i}.example.org/p", "content": "c"} for i in range(30)]
    senv.runner.script["claude-01"] = lambda: ok_search(senv, rows=many + many)
    assert len(search(senv, max_results=3).json()["results"]) == 3
    assert len(search(senv, max_results=99).json()["results"]) == 8


def test_ap_dung_lai_bo_loc_ten_mien(senv):
    rows = [{"title": "n1", "url": "https://www.nhs.uk/a", "content": "1"}, {"title": "n2", "url": "https://vinmec.com/b", "content": "2"}]
    senv.runner.script["claude-01"] = lambda: ok_search(senv, rows=rows)
    assert [r["title"] for r in search(senv, include_domains=["nhs.uk"]).json()["results"]] == ["n1"]
    assert [r["title"] for r in search(senv, exclude_domains=["nhs.uk"]).json()["results"]] == ["n2"]


def test_lam_sach_ky_tu_dieu_khien_trong_ket_qua(senv):
    rows = [{"title": "Tiêu‮đề\x00\n  lạ", "url": "https://ok.example.org/p", "content": "nội\x07dung​  ẩn"}]
    senv.runner.script["claude-01"] = lambda: ok_search(senv, rows=rows)
    r = search(senv).json()["results"][0]
    assert r["title"] == "Tiêu đề lạ" and r["content"] == "nội dung ẩn"


def test_runner_tra_rac_thi_ket_qua_rong_khong_loi(senv):
    senv.runner.script["claude-01"] = lambda: ok_search(senv, rows="khong phai danh sach")
    assert search(senv).json() == {"results": []}


# ---------------------------------------------------------------- model và định tuyến
def test_model_mac_dinh_va_ghi_de(senv):
    search(senv); search(senv, model="claude:sonnet"); search(senv, model="sonnet")
    assert [c[3]["model"] for c in senv.runner.calls] == ["haiku", "sonnet", "sonnet"]


@pytest.mark.parametrize("model", ["grok:grok-4.7", "agy:x", "claude:a b", "claude:../x", "claude:$(id)"])
def test_chi_ho_tro_claude(senv, model):
    r = search(senv, model=model)
    assert r.status_code in (403, 404) and senv.runner.calls == []


def test_model_phai_nam_trong_danh_sach_cho_phep(senv):
    senv.db.execute("update app_policies set allowed_models=? where app_id=1 and capability_id=3", ('["haiku"]',)); senv.db.commit()
    assert search(senv).status_code == 200
    r = search(senv, model="claude:opus")
    assert r.status_code == 403 and code(r) == "model_not_allowed"


def test_chi_dung_tai_khoan_claude_khong_dung_grok_o_pool_du_phong(senv):
    senv.db.execute("update accounts set status='paused' where provider_id=1"); senv.db.commit()
    r = search(senv)
    assert r.status_code == 503 and code(r) == "no_account_available"
    assert all(c[0] != "grok-01" for c in senv.runner.calls)


def test_rate_limited_thu_tai_khoan_khac(senv):
    senv.runner.script["claude-01"] = [senv.runner.fail("rate_limited")]
    r = search(senv)
    assert r.status_code == 200 and r.headers["x-gateway-account"] == "claude-02"
    assert [(e["status"], e["account"]) for e in senv.events()] == [("rate_limited", "claude-01"), ("ok", "claude-02")]


@pytest.mark.parametrize("cls,status,c", [("timeout", 504, "upstream_timeout"), ("upstream_error", 502, "upstream_error"),
                                          ("policy_violation", 502, "upstream_policy_violation")])
def test_anh_xa_loi(senv, cls, status, c):
    senv.runner.script["claude-01"] = [senv.runner.fail(cls)]
    r = search(senv)
    assert r.status_code == status and code(r) == c and len(senv.runner.calls) == 1


def test_vi_pham_chinh_sach_cua_runner_loai_tai_khoan_khoi_ca_van_ban(senv):
    senv.runner.script["claude-01"] = [senv.runner.fail("policy_violation")]
    search(senv)
    assert senv.app.state.gw_state.flags["claude-01"] == "tripped"
    assert senv.post().headers["x-gateway-account"] == "claude-02"


# ---------------------------------------------------------------- đo lường
def test_ghi_nhan_su_dung_theo_khai_nang(senv):
    r = search(senv)
    e = senv.events()[0]
    assert e["status"] == "ok" and e["model"] == "haiku" and (e["in"], e["out"], e["w"]) == (3839, 6, 10)
    assert senv.db.execute("select capability from usage_events").fetchone()[0] == "web_search"
    assert r.headers["x-gateway-units"] == "10"


def test_noi_dung_truy_van_khong_vao_log_hay_csdl(senv, caplog):
    caplog.set_level(logging.DEBUG)
    senv.runner.script["claude-01"] = [senv.runner.fail("upstream_error")]
    search(senv, query="TRUY-VAN-RIENG-TU-KHONG-DUOC-GHI-5521"); search(senv, query="TRUY-VAN-RIENG-TU-KHONG-DUOC-GHI-5521")
    assert "TRUY-VAN-RIENG-TU-KHONG-DUOC-GHI-5521" not in caplog.text
    assert not any("TRUY-VAN-RIENG-TU-KHONG-DUOC-GHI-5521" in l for l in senv.db.iterdump())


# ---------------------------------------------------------------- hạn mức riêng từng khả năng
def test_rpm_rieng_cho_tim_web_khong_lan_voi_van_ban(senv):
    senv.limit(rpm=1, cap="web_search")
    assert senv.post().status_code == 200 and senv.post().status_code == 200       # văn bản: không có giới hạn
    assert search(senv).status_code == 200
    r = search(senv)
    assert r.status_code == 429 and code(r) == "app_rpm"


def test_rpm_cua_van_ban_khong_chan_tim_web(senv):
    senv.limit(rpm=1, cap="text")
    senv.post(); assert senv.post().status_code == 429
    assert search(senv).status_code == 200 and search(senv).status_code == 200


def test_han_muc_ngay_rieng_tim_web_chi_dem_tim_web(senv):
    senv.limit(day=100, cap="web_search")
    senv.db.execute("insert into usage_events(ts,app_id,account_id,capability,model,weighted_units,status,request_id) values "
                    "('2026-11-19 08:00:00',1,1,'text','haiku',9999,'ok','r1')"); senv.db.commit()
    assert search(senv).status_code == 200                                         # 9999 đơn vị VĂN BẢN không tính vào hạn mức tìm web
    senv.db.execute("insert into usage_events(ts,app_id,account_id,capability,model,weighted_units,status,request_id) values "
                    "('2026-11-19 09:00:00',1,1,'web_search','haiku',100,'ok','r2')"); senv.db.commit()
    r = search(senv)
    assert r.status_code == 429 and code(r) == "app_daily_units"
    assert senv.post().status_code == 200                                          # văn bản không bị ảnh hưởng


def test_han_muc_chung_dem_moi_khai_nang(senv):
    senv.limit(day=100)                                                            # capability_code rỗng = chung
    senv.db.execute("insert into usage_events(ts,app_id,account_id,capability,model,weighted_units,status,request_id) values "
                    "('2026-11-19 08:00:00',1,1,'text','haiku',100,'ok','r1')"); senv.db.commit()
    assert code(search(senv)) == "app_daily_units" and code(senv.post()) == "app_daily_units"
