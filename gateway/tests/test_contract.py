"""Hợp đồng với giao diện điều khiển: lược đồ, thuật toán băm khóa, và phạm vi ghi của gateway."""
from sqlalchemy import event

from conftest import KEY_A, PEPPER
from gateway.auth import hash_key
from gateway.db import TABLES

# Vector sinh bằng PHP thật: hash_hmac("sha256", "gw_" . str_repeat("ab", 20), "base64:Rk9PQkFSRk9PQkFSRk9PQkFSRk9PQkFSRk9PQkE=")
PHP_VECTOR = "6d511c851321fc81c191e5f33ee5d1e9370cf02dcc25b52e303f2687cbca4740"


def test_bam_khoa_khop_voi_php():
    assert hash_key(KEY_A, PEPPER) == PHP_VECTOR


def test_gateway_doc_duoc_toan_bo_luoc_do_hop_dong(env):
    store = env.app.state.store
    assert set(store.t) == set(TABLES)
    need = {"usage_events": {"ts", "app_id", "account_id", "capability", "model", "input_units", "output_units", "weighted_units",
                             "latency_ms", "status", "error_class", "request_id", "estimated"},
            "app_keys": {"key_hash", "revoked_at", "expires_at", "last_used_at", "app_id"},
            "accounts": {"label", "status", "cooldown_until", "max_concurrency", "runner_url", "provider_id"}}
    for t, cols in need.items():
        assert cols <= set(store.t[t].c.keys()), t


def test_gateway_chi_ghi_usage_events_va_last_used_at(env):
    stmts = []
    event.listen(env.app.state.store.engine, "before_cursor_execute", lambda c, cur, s, p, ctx, m: stmts.append(s.strip().upper()))
    assert env.post().status_code == 200
    writes = [s for s in stmts if s.startswith(("INSERT", "UPDATE", "DELETE", "DROP", "ALTER", "CREATE"))]
    assert writes, "phải có ghi nhận sử dụng"
    for s in writes:
        assert s.startswith("INSERT INTO USAGE_EVENTS") or s.startswith("UPDATE APP_KEYS SET LAST_USED_AT"), s
