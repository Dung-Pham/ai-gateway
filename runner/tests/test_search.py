import os

import pytest

from conftest import calls
from runner import search as S

SEARCH = {"request_id": "req_s1", "capability": "web_search", "model": "haiku", "query": "huyết áp thấp là gì", "max_results": 5}


def run(svc, **over):
    return svc.execute({**SEARCH, **over})


def leftover(svc):
    root = str(svc.cfg.work_root)
    return os.listdir(root) if os.path.isdir(root) else []


# ---------------------------------------------------------------- dòng lệnh: CHỈ WebSearch
def test_args_chi_websearch_va_van_cung_hoa(make_service):
    svc = make_service("claude"); run(svc)
    a = calls(svc)[0]["argv"]
    assert a[a.index("--tools") + 1] == "WebSearch" and a[a.index("--allowedTools") + 1] == "WebSearch"
    assert "WebFetch" not in " ".join(a)                                       # WebFetch tải trang từ máy runner: KHÔNG BAO GIỜ
    assert "--strict-mcp-config" in a and a[a.index("--mcp-config") + 1] == '{"mcpServers":{}}'
    assert "--no-session-persistence" in a and a[a.index("--max-turns") + 1] == "6"
    assert a[a.index("--setting-sources") + 1] == "" and not any(x.startswith("--dangerously") for x in a)
    sysprompt = next(x for x in a if x.startswith("--system-prompt="))
    assert "WebSearch" in sysprompt and "WebFetch" not in sysprompt and "DỮ LIỆU" in sysprompt


def test_text_van_khong_co_cong_cu(make_service):
    svc = make_service("claude"); svc.execute({"capability": "text", "model": "haiku", "system": "", "user": "xin chào"})
    a = calls(svc)[0]["argv"]
    assert a[a.index("--tools") + 1] == "" and "--allowedTools" not in a        # khả năng mới không làm yếu đường văn bản


# ---------------------------------------------------------------- kết quả
def test_ket_qua_hop_le(make_service):
    code, b = run(make_service("claude"))
    assert code == 200 and b["ok"] and b["text"] == ""
    assert [r["url"] for r in b["results"]] == ["https://www.vinmec.com/vi/bai-viet/a", "https://suckhoedoisong.vn/b"]
    assert set(b["results"][0]) == {"title", "url", "content"} and b["usage"]["output_tokens"] == 300 and b["quota"]["five_hour"]["utilization"] == 0.1


def test_ket_qua_rong_la_hop_le(make_service):
    code, b = run(make_service("claude"), query="@@EMPTY")
    assert b["ok"] and b["results"] == []


def test_khong_co_json_thi_rong_chu_khong_loi(make_service):
    code, b = run(make_service("claude"), query="@@NOJSON")
    assert b["ok"] and b["results"] == []


def test_toi_da_max_results(make_service):
    assert len(run(make_service("claude"), query="@@MANY", max_results=3)[1]["results"]) == 3
    assert len(run(make_service("claude"), query="@@MANY", max_results=99)[1]["results"]) == 8      # chặn trên cứng
    assert len(run(make_service("claude"), query="@@MANY", max_results=0)[1]["results"]) == 1       # chặn dưới


def test_khu_trung_url(make_service):
    urls = [r["url"] for r in run(make_service("claude"), query="@@DUP")[1]["results"]]
    assert len(urls) == len(set(u.lower().rstrip("/") for u in urls)) == 3


def test_url_nguy_hiem_bi_loai_chi_con_url_tot(make_service):
    code, b = run(make_service("claude"), query="@@BADURLS")
    assert [r["url"] for r in b["results"]] == ["https://www.vinmec.com/vi/bai-viet/a"]


def test_lam_sach_ky_tu_dieu_khien_va_dao_chieu(make_service):
    r = run(make_service("claude"), query="@@CTRL")[1]["results"][0]
    assert r["title"] == "Tiêu đề lạ nhiều dòng" and r["content"] == "nội dung ẩn"
    for bad in ("\x00", "\x07", "‮", "​", "\n"):
        assert bad not in r["title"] + r["content"]


def test_bo_loc_ten_mien_ap_dung_xac_dinh(make_service):
    inc = run(make_service("claude"), query="@@DOM", include_domains=["nhs.uk", "cdc.gov"])[1]["results"]
    assert [r["title"] for r in inc] == ["n1", "n3"]            # khớp cả tên miền con, loại vinmec.com
    exc = run(make_service("claude"), query="@@DOM", exclude_domains=["vinmec.com"])[1]["results"]
    assert [r["title"] for r in exc] == ["n1", "n3"]


# ---------------------------------------------------------------- an toàn
def test_lo_webfetch_thi_ngat_mach(make_service):
    svc = make_service("claude")
    code, b = run(svc, query="@@FETCH")
    assert b["error_class"] == "policy_violation" and svc.tripped
    assert svc.execute({"capability": "text", "model": "haiku", "system": "", "user": "x"})[0] == 503     # mọi khả năng đều dừng


def test_phan_loai_loi_giong_text(make_service):
    assert run(make_service("claude"), query="@@LIMIT")[1]["error_class"] == "rate_limited"


def test_don_sach_va_token(make_service):
    svc = make_service("claude"); run(svc); run(svc, query="@@FETCH")
    assert leftover(svc) == []


@pytest.mark.parametrize("prov,model", [("grok", "grok-4.7"), ("agy", "m")])
def test_hang_khac_khong_ho_tro_web_search(make_service, prov, model):
    code, b = run(make_service(prov), model=model)
    assert code == 400 and b["error_class"] == "unsupported"


@pytest.mark.parametrize("over", [{"query": ""}, {"query": "  "}, {"query": "x" * 401}, {"query": 5}, {"max_results": "abc"},
                                  {"include_domains": "a.com"}, {"include_domains": ["a b.com"]}, {"exclude_domains": ["x/y"]},
                                  {"include_domains": ["a.com"] * 21}, {"include_domains": [1]}, {"include_domains": ["../x"]}])
def test_dau_vao_sai(make_service, over):
    svc = make_service("claude")
    code, b = run(svc, **over)
    assert code == 400 and b["error_class"] == "bad_request" and calls(svc) == []


def test_truy_van_duoc_lam_sach_ky_tu_dieu_khien(make_service):
    svc = make_service("claude"); run(svc, query="a\x00b‮c")
    # CLI giả không ghi nội dung; kiểm tra bằng hàm chuẩn hóa
    assert S.validate_search({"query": "a\x00b‮c"})["query"] == "a b c"


# ---------------------------------------------------------------- hàm làm sạch (bảng)
@pytest.mark.parametrize("url,ok", [
    ("https://example.org/a", True), ("http://example.org:80/a", True), ("https://example.org:443/a", True),
    ("https://sub.example.org/a?q=1#f", True), ("HTTPS://Example.ORG/a", True),
    ("https://example.org:8443/a", False), ("javascript:alert(1)", False), ("data:text/html,x", False), ("file:///etc/passwd", False),
    ("http://127.0.0.1/", False), ("http://8.8.8.8/", False), ("http://[::1]/", False), ("http://169.254.169.254/", False),
    ("http://localhost/", False), ("http://a.localhost.local/", False), ("https://intranet/x", False), ("https://a.internal/x", False),
    ("https://user@example.org/", False), ("https://user:pw@example.org/", False), ("https://exa mple.org/", False), ("", False), ("//example.org/", False),
    ("https://example.org/\x00", False), ("https://example.org/‮", False)])
def test_url_safe(url, ok):
    assert (S.url_safe(url) is not None) is ok
