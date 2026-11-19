import pytest

from conftest import KEY_B, KEY_C, KEY_EXPIRED, KEY_REVOKED


def code(r):
    return r.json()["error"]["code"]


@pytest.mark.parametrize("hdr", [None, "", "Bearer", "Bearer x", "Basic abc", "Bearer gw_" + "z" * 40, "Bearer gw_" + "a" * 39, "Bearer gw_" + "A" * 40, "bearer " + "gw_" + "a" * 40])
def test_khoa_thieu_hoac_sai_dinh_dang(env, hdr):
    r = env.client.post("/v1/chat/completions", json={"model": "claude:haiku", "messages": [{"role": "user", "content": "x"}]},
                        headers={"Authorization": hdr} if hdr is not None else {})
    assert r.status_code == 401 and code(r) == "invalid_api_key"
    assert env.runner.calls == []


def test_khoa_dung_dinh_dang_nhung_khong_co_trong_he_thong(env):
    assert env.post(key="gw_" + "9" * 40).status_code == 401


def test_khoa_da_thu_hoi_hoac_het_han(env):
    assert env.post(key=KEY_REVOKED).status_code == 401
    assert env.post(key=KEY_EXPIRED).status_code == 401


def test_ung_dung_bi_tam_ngung(env):
    r = env.post(key=KEY_B)
    assert r.status_code == 403 and code(r) == "app_suspended" and env.runner.calls == []


def test_chua_duoc_cap_kha_nang(env):
    r = env.post(key=KEY_C)
    assert r.status_code == 403 and code(r) == "capability_not_granted"


def test_khai_nang_bi_tat_toan_he_thong(env):
    env.db.execute("update capabilities set enabled=0 where code='text'"); env.db.commit()
    assert code(env.post()) == "capability_not_granted"


def test_chinh_sach_bi_tat(env):
    env.db.execute("update app_policies set enabled=0 where app_id=1"); env.db.commit()
    assert code(env.post()) == "capability_not_granted"


def test_model_khong_nam_trong_danh_sach_cho_phep(env):
    r = env.post("claude:opus")
    assert r.status_code == 403 and code(r) == "model_not_allowed" and env.runner.calls == []


def test_model_khong_co_tien_to_khi_nhieu_hang_la_mo_ho(env):
    r = env.post("haiku")   # pool chính là claude, dự phòng là grok
    assert r.status_code == 400 and code(r) == "model_requires_provider"


def test_model_khong_co_tien_to_khi_chi_co_mot_hang(env):
    r = env.post("anything", key="gw_" + "?" * 40)  # sai khóa -> 401 trước
    assert r.status_code == 401
    env.db.execute("update app_policies set fallback_pool_id=null where app_id=1"); env.db.commit()
    assert env.post("haiku").status_code == 200   # chỉ còn claude -> suy ra được


@pytest.mark.parametrize("model", ["khac:haiku", "claude:", "claude:a b", "claude:../x", "claude:$(id)", ":haiku", "x" * 80])
def test_ten_model_xau(env, model):
    r = env.post(model)
    assert r.status_code in (400, 403, 404) and env.runner.calls == []   # mọi dạng sai đều bị từ chối trước khi tới runner


def test_stream_chua_ho_tro(env):
    r = env.post(stream=True)
    assert r.status_code == 400 and code(r) == "stream_not_supported"


def test_anh_chua_ho_tro_khong_gui_xuong_runner(env):
    r = env.client.post("/v1/chat/completions", headers={"Authorization": "Bearer " + __import__("conftest").KEY_A},
                        json={"model": "claude:haiku", "messages": [{"role": "user", "content": [{"type": "image_url", "image_url": {"url": "data:image/png;base64,AAAA"}}]}]})
    assert r.status_code == 501 and code(r) == "capability_not_supported" and env.runner.calls == []


@pytest.mark.parametrize("body", [{}, {"model": "claude:haiku"}, {"model": "claude:haiku", "messages": []}, {"model": "claude:haiku", "messages": "x"},
                                  {"model": "claude:haiku", "messages": [{"role": "assistant", "content": "x"}]},
                                  {"model": "claude:haiku", "messages": [{"role": "hacker", "content": "x"}]},
                                  {"model": "claude:haiku", "messages": [{"role": "user", "content": 5}]},
                                  {"model": "claude:haiku", "messages": [{"role": "user", "content": "  "}]}])
def test_yeu_cau_sai(env, body):
    r = env.client.post("/v1/chat/completions", json=body, headers={"Authorization": "Bearer " + __import__("conftest").KEY_A})
    assert r.status_code == 400 and env.runner.calls == []


def test_json_hong_va_than_qua_lon(env):
    h = {"Authorization": "Bearer " + __import__("conftest").KEY_A}
    assert env.client.post("/v1/chat/completions", content=b"{khong", headers=h).status_code == 400
    assert env.client.post("/v1/chat/completions", content=b"x" * (env.settings.max_body + 5), headers=h).status_code == 413


def test_nhieu_luot_hoi_thoai_duoc_ghep(env):
    env.client.post("/v1/chat/completions", headers={"Authorization": "Bearer " + __import__("conftest").KEY_A},
                    json={"model": "claude:haiku", "messages": [{"role": "system", "content": "Hãy ngắn gọn"}, {"role": "user", "content": "Một"},
                                                                {"role": "assistant", "content": "Hai"}, {"role": "user", "content": "Ba"}]})
    p = env.runner.calls[0][3]
    assert p["system"] == "Hãy ngắn gọn" and "Người dùng: Một" in p["user"] and "Trợ lý: Hai" in p["user"] and p["user"].endswith("Người dùng: Ba")


def test_danh_sach_model(env):
    r = env.client.get("/v1/models", headers={"Authorization": "Bearer " + __import__("conftest").KEY_A})
    ids = {m["id"] for m in r.json()["data"]}
    assert "claude:haiku" in ids and "grok:haiku" in ids and "claude:opus" not in ids
    assert env.client.get("/v1/models").status_code == 401
