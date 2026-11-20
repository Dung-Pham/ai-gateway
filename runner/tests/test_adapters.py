import os
import stat

import pytest

from conftest import calls

REQ = {"request_id": "req_t1", "capability": "text", "model": "haiku", "system": "", "user": "Chào bạn"}


def run(svc, **over):
    return svc.execute({**REQ, **over})


def leftover(svc):
    root = str(svc.cfg.work_root)
    return os.listdir(root) if os.path.isdir(root) else []


# ---------------------------------------------------------------- thành công + số liệu
def test_claude_thanh_cong_co_usage_va_quota(make_service):
    svc = make_service("claude")
    code, b = run(svc)
    assert code == 200 and b["ok"] and b["text"] == "xin chào"
    assert b["usage"] == {"input_tokens": 2, "output_tokens": 6, "cache_read_tokens": 649, "cache_creation_tokens": 3188}
    assert b["cost_usd"] == pytest.approx(0.00064729)
    assert b["quota"]["five_hour"]["utilization"] == 0.39 and b["quota"]["seven_day"]["utilization"] == 0.44
    assert b["model"] == "claude-haiku-5-5"


def test_grok_thanh_cong_co_usage(make_service):
    code, b = run(make_service("grok"), model="grok-4.7")
    assert b["ok"] and b["usage"]["input_tokens"] == 5050 and b["usage"]["reasoning_tokens"] == 294 and b["cost_usd"] > 0


def test_agy_thanh_cong_co_usage(make_service):
    code, b = run(make_service("agy"), model="gemini-4-neon-high")
    assert b["ok"] and b["text"] == "xin chào" and b["usage"]["input_tokens"] == 9600 and b["usage"]["reasoning_tokens"] == 200


# ---------------------------------------------------------------- cờ cứng hóa PHẢI có, cờ nguy hiểm PHẢI vắng
def test_claude_args_cung_hoa(make_service):
    svc = make_service("claude"); run(svc)
    a = calls(svc)[0]["argv"]
    for flag in ("--strict-mcp-config", "--no-session-persistence", "--disable-slash-commands"):
        assert flag in a
    assert a[a.index("--mcp-config") + 1] == '{"mcpServers":{}}'
    assert a[a.index("--tools") + 1] == "" and a[a.index("--setting-sources") + 1] == ""
    assert not any(x.startswith("--dangerously") or x in ("--allowedTools", "--allowed-tools", "--permission-mode") for x in a)


def test_grok_args_go_du_cong_cu(make_service):
    svc = make_service("grok"); run(svc, model="grok-4.7")
    c = calls(svc)[0]; a = c["argv"]
    assert a[a.index("--disallowed-tools") + 1] == "run_terminal_command,read_file,search_replace"
    for flag in ("--no-subagents", "--disable-web-search"):
        assert flag in a
    assert a[a.index("--max-turns") + 1] == "1"
    assert "--sandbox" not in a  # mặc định tắt: bwrap không chạy được trong container cứng hóa; việc gỡ công cụ + post_check là chốt chặn
    assert c["env"]["GROK_MEMORY"] == "0" and c["env"]["GROK_TELEMETRY_ENABLED"] == "0" and c["env"]["GROK_SUBAGENTS"] == "0"
    assert "--tools" not in a  # --tools "" từng bị chứng minh KHÔNG đủ


def test_grok_sandbox_bat_duoc_bang_cau_hinh(make_service):
    svc = make_service("grok", grok_sandbox="strict"); run(svc, model="grok-4.7")
    a = calls(svc)[0]["argv"]
    assert a[a.index("--sandbox") + 1] == "strict" and "--disallowed-tools" in a   # sandbox chỉ BỔ SUNG, không thay việc gỡ công cụ


def test_agy_khong_co_co_nguy_hiem(make_service):
    svc = make_service("agy"); run(svc, model="gemini-4-neon-high")
    a = calls(svc)[0]["argv"]
    assert "--dangerously-skip-permissions" not in a and "--mode" not in a  # --mode plan tạo tệp kế hoạch thay vì trả lời


def test_moi_truong_cua_cli_toi_thieu_va_khong_ro_bi_mat(make_service, monkeypatch):
    monkeypatch.setenv("RUNNER_SECRET_FILE", "/x"); monkeypatch.setenv("AWS_SECRET_ACCESS_KEY", "boom")
    svc = make_service("claude"); run(svc)
    c = calls(svc)[0]
    assert not any(k.startswith(("RUNNER_", "AWS_")) for k in c["env_keys"])
    assert set(c["env_keys"]) <= {"PATH", "HOME", "TMPDIR", "LANG", "LC_ALL", "STUB_LOG", "CLAUDE_CONFIG_DIR", "PWD", "SHLVL", "_", "LC_CTYPE"}
    assert c["home"].startswith(str(svc.cfg.work_root))  # HOME nằm trong thư mục tạm của yêu cầu


# ---------------------------------------------------------------- dọn dẹp và token
@pytest.mark.parametrize("prov,model", [("claude", "haiku"), ("grok", "grok-4.7"), ("agy", "m")])
def test_don_sach_thu_muc_tam_ke_ca_khi_loi(make_service, prov, model):
    svc = make_service(prov)
    run(svc, model=model); run(svc, model=model, user="@@AUTH")
    assert leftover(svc) == []


def test_grok_noi_dung_hoi_thoai_bi_xoa_khoi_dia(make_service):
    svc = make_service("grok"); run(svc, model="grok-4.7", user="mật mã siêu bí mật 12345")
    assert leftover(svc) == []
    for root, _d, files in os.walk(svc.tmp_path):
        for f in files:
            assert "NOI-DUNG-HOI-THOAI" not in open(os.path.join(root, f), errors="ignore").read()


@pytest.mark.parametrize("prov,model", [("claude", "haiku"), ("grok", "grok-4.7"), ("agy", "m")])
def test_token_duoc_lam_moi_thi_ghi_nguoc_dung_quyen(make_service, prov, model):
    svc = make_service(prov)
    code, b = run(svc, model=model, user="@@REFRESH")
    assert b["ok"] and "NEW-TOKEN-AFTER-REFRESH" in svc.token.read_text()
    assert stat.S_IMODE(os.stat(svc.token).st_mode) == 0o600


def test_token_khong_doi_thi_khong_ghi(make_service):
    svc = make_service("claude"); before = os.stat(svc.token).st_mtime_ns
    run(svc)
    assert os.stat(svc.token).st_mtime_ns == before and svc.token.read_text() == '{"token":"ORIGINAL-TOKEN"}'


def test_thieu_token_la_auth_expired(make_service):
    svc = make_service("claude"); svc.token.unlink()
    code, b = run(svc)
    assert not b["ok"] and b["error_class"] == "auth_expired"


# ---------------------------------------------------------------- phân loại lỗi (theo thông báo thật đã thu thập)
@pytest.mark.parametrize("prov,model,tag,cls", [
    ("claude", "haiku", "@@AUTH", "auth_expired"), ("claude", "haiku", "@@LIMIT", "rate_limited"),
    ("grok", "grok-4.7", "@@AUTH", "auth_expired"), ("grok", "grok-4.7", "@@LIMIT", "rate_limited"),
    ("agy", "m", "@@AUTH", "auth_expired"), ("agy", "m", "@@DENIED", "tool_denied"),
    ("claude", "haiku", "@@GARBAGE", "upstream_error"), ("grok", "grok-4.7", "@@GARBAGE", "upstream_error"),
])
def test_phan_loai_loi(make_service, prov, model, tag, cls):
    code, b = run(make_service(prov), model=model, user=tag)
    assert code == 200 and b["ok"] is False and b["error_class"] == cls


def test_timeout_giet_cli(make_service):
    svc = make_service("claude", timeout_s=1)
    code, b = run(svc, user="@@SLOW")
    assert b["error_class"] == "timeout" and leftover(svc) == []


# ---------------------------------------------------------------- NGẮT MẠCH khi CLI vi phạm chính sách
def test_claude_lo_connector_thi_ngat_mach(make_service):
    svc = make_service("claude")
    code, b = run(svc, user="@@TOOLS")
    assert b["error_class"] == "policy_violation" and svc.tripped
    code2, b2 = run(svc)
    assert code2 == 503 and b2["error_class"] == "tripped"


def test_grok_lo_cong_cu_thi_ngat_mach(make_service):
    svc = make_service("grok")
    code, b = run(svc, model="grok-4.7", user="@@TOOLS")
    assert b["error_class"] == "policy_violation" and svc.tripped


# ---------------------------------------------------------------- kiểm tra đầu vào
@pytest.mark.parametrize("model", ["", "a b", "x;rm -rf /", "../../etc", "a" * 65, "$(id)"])
def test_ten_model_xau_bi_tu_choi(make_service, model):
    code, b = run(make_service("claude"), model=model)
    assert code == 400 and b["error_class"] == "bad_request"


def test_noi_dung_rong_bi_tu_choi(make_service):
    assert run(make_service("claude"), user="  ")[0] == 400


def test_claude_nhan_noi_dung_dai_qua_stdin_gioi_han_la_than_yeu_cau(make_service):
    # Claude/Grok không bị giới hạn đối số lệnh (stdin / tệp); giới hạn thật là max_body của máy chủ (test_server: 413).
    assert run(make_service("claude"), user="x" * 3000)[0] == 200


def test_khong_ho_tro_khai_nang_chua_lam(make_service):
    code, b = run(make_service("claude"), capability="image_in")
    assert code == 400 and b["error_class"] == "unsupported"


def test_agy_prompt_dai_qua_doi_so_bi_tu_choi(make_service):
    assert run(make_service("agy", max_prompt_bytes=100), model="m", user="x" * 200)[0] == 400


# ---------------------------------------------------------------- Codex (định dạng sự kiện lấy từ codex-cli 0.163.0 thật)
CODEX_REQ = {"model": "gpt-6-luna"}


def test_codex_thanh_cong_co_usage(make_service):
    code, b = run(make_service("codex"), **CODEX_REQ)
    assert code == 200 and b["ok"] and b["text"] == "xin chào" and b["model"] == "gpt-6-luna"
    assert b["usage"] == {"input_tokens": 12773, "output_tokens": 5, "cache_read_tokens": 10880, "reasoning_tokens": 0}
    assert b["cost_usd"] is None and b["quota"] == {}


def test_codex_args_cung_hoa(make_service):
    svc = make_service("codex"); run(svc, **CODEX_REQ)
    c = calls(svc)[0]; a = c["argv"]
    assert a[0] == "exec" and a[-1] == "-"                                 # prompt đi qua stdin, không qua đối số
    for flag in ("--ephemeral", "--ignore-user-config", "--ignore-rules", "--skip-git-repo-check", "--json"):
        assert flag in a
    assert a[a.index("--sandbox") + 1] == "read-only"
    disabled = {a[i + 1] for i, x in enumerate(a) if x == "--disable"}
    # mỗi tính năng này từng mở lại một công cụ khi chạy Codex thật
    assert {"shell_tool", "unified_exec", "apps", "plugins", "browser_use", "computer_use", "multi_agent", "code_mode_host", "image_generation"} <= disabled
    cfg = [a[i + 1] for i, x in enumerate(a) if x == "-c"]
    assert 'web_search="disabled"' in cfg
    assert not any(x.startswith("--dangerously") or x in ("--oss", "--full-auto") for x in a)
    assert "-m" in a and a[a.index("-m") + 1] == "gpt-6-luna"
    assert c["env"]["CODEX_HOME"].endswith("/.codex")                       # trong HOME tạm, không phải thư mục thật


def test_codex_het_phien_va_gioi_han(make_service):
    code, b = run(make_service("codex"), user="@@AUTH", **CODEX_REQ)
    assert not b["ok"] and b["error_class"] == "auth_expired"
    code, b = run(make_service("codex"), user="@@LIMIT", **CODEX_REQ)
    assert not b["ok"] and b["error_class"] == "rate_limited"


def test_codex_chua_dang_nhap_khong_goi_cli(make_service):
    svc = make_service("codex")
    os.remove(svc.token)
    code, b = run(svc, **CODEX_REQ)
    assert b["error_class"] == "auth_expired" and calls(svc) == []


def test_codex_lo_cong_cu_thi_ngat_mach(make_service):
    svc = make_service("codex")
    code, b = run(svc, user="@@TOOLS", **CODEX_REQ)
    assert not b["ok"] and b["error_class"] == "policy_violation"
    code, b = run(svc, **CODEX_REQ)
    assert b["error_class"] == "tripped"       # ngắt mạch: từ chối mọi yêu cầu cho tới khi khởi động lại


def test_codex_khong_co_ket_thuc_luot_la_loi(make_service):
    code, b = run(make_service("codex"), user="@@NOTURN", **CODEX_REQ)
    assert not b["ok"] and b["error_class"] == "upstream_error"


def test_codex_token_lam_moi_duoc_ghi_nguoc_va_thu_muc_tam_bi_xoa(make_service):
    svc = make_service("codex")
    code, b = run(svc, user="@@REFRESH", **CODEX_REQ)
    assert b["ok"] and "NEW-TOKEN-AFTER-REFRESH" in svc.token.read_text()
    assert leftover(svc) == []


def test_codex_chi_ho_tro_van_ban(make_service):
    code, b = run(make_service("codex"), capability="web_search", **CODEX_REQ)
    assert not b["ok"] and b["error_class"] == "unsupported"
