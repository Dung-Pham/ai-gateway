import os
import sqlite3
import sys
from datetime import datetime

import pytest
from fastapi.testclient import TestClient

sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))
from gateway.app import create_app  # noqa: E402
from gateway.auth import hash_key  # noqa: E402
from gateway.config import Settings  # noqa: E402

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(__file__)))
SCHEMA = open(os.path.join(ROOT, "contracts", "control_schema.sql"), encoding="utf-8").read()
PEPPER = "base64:Rk9PQkFSRk9PQkFSRk9PQkFSRk9PQkFSRk9PQkE="
KEY_A = "gw_" + "ab" * 20
KEY_REVOKED, KEY_EXPIRED, KEY_B, KEY_C = ("gw_" + c * 40 for c in "1234")
ADMIN = "a" * 40
LABELS = ("claude-01", "claude-02", "grok-01")
SECRETS = {l: "x" * 40 for l in LABELS}


class Clock:
    def __init__(self):
        self.t = datetime(2026, 11, 19, 10, 0, 0)
        self.e = 1_795_000_000.0

    def now(self):
        return self.t

    def epoch(self):
        return self.e


class FakeRunner:
    """Runner GIẢ: trả lời theo kịch bản của từng nhãn tài khoản; ghi lại mọi lần gọi."""

    def __init__(self):
        self.calls, self.script = [], {}

    def ok(self, text="xin chào", **over):
        r = {"ok": True, "text": text, "model": "claude-haiku-5-5", "denied_actions": [],
             "usage": {"input_tokens": 2, "output_tokens": 6, "cache_read_tokens": 649, "cache_creation_tokens": 3188},
             "cost_usd": 0.0006, "quota": {"five_hour": {"utilization": 0.39, "resets_at": 1_795_088_400}}}
        r.update(over)
        return r

    def fail(self, cls, **over):
        return {"ok": False, "error_class": cls, "message": "x", **over}

    async def info(self, ref):
        self.info_calls = getattr(self, "info_calls", 0) + 1
        return getattr(self, "auth_script", {}).get(ref.label, {"provider": "claude", "auth": {"login_expires_at": None}})

    async def run(self, ref, payload):
        self.calls.append((ref.label, ref.url, ref.secret, payload))
        q = self.script.get(ref.label)
        res = (q.pop(0) if isinstance(q, list) and q else q) if q else self.ok()
        if callable(res):
            res = res()
        return 200, res


def seed(path):
    c = sqlite3.connect(path)
    c.executescript(SCHEMA)
    q = c.execute
    for i, (code, name) in enumerate([("claude", "Claude"), ("grok", "Grok"), ("agy", "Antigravity")], 1):
        q("insert into providers(id,code,name,color) values (?,?,?,?)", (i, code, name, "#000"))
    for i, (label, prov) in enumerate([("claude-01", 1), ("claude-02", 1), ("grok-01", 2)], 1):
        q("insert into accounts(id,provider_id,label,status,max_concurrency,runner_url) values (?,?,?,?,?,?)",
          (i, prov, label, "active", 2, f"http://runner-{label}:8800"))
    q("insert into pools(id,name,strategy) values (1,'Viết bài','least_used'),(2,'Dự phòng','priority')")
    q("insert into pool_members(pool_id,account_id,priority,weight) values (1,1,1,1),(1,2,2,1),(2,3,1,1)")
    q("insert into capabilities(id,code,name,risk_level,enabled) values (1,'text','Văn bản','low',1),(2,'image_in','Ảnh','medium',1),(3,'web_search','Tìm web','high',1)")
    for i, (name, slug, st) in enumerate([("App A", "a", "active"), ("App B", "b", "suspended"), ("App C", "c", "active"), ("App D", "d", "active")], 1):
        q("insert into apps(id,name,slug,status) values (?,?,?,?)", (i, name, slug, st))
    # App A: text -> pool 1, dự phòng pool 2, chỉ haiku/sonnet. App C: chưa cấp gì. App D: pool 1 và model bất kỳ.
    q("insert into app_policies(app_id,capability_id,allowed_models,pool_id,fallback_pool_id,enabled) values (1,1,?,1,2,1)", ('["haiku","sonnet","grok-4.7"]',))
    q("insert into app_policies(app_id,capability_id,allowed_models,pool_id,fallback_pool_id,enabled) values (4,1,null,1,null,1)")
    # App A có tìm web (pool 1, dự phòng pool 2 chứa grok: phải bị loại vì tìm web chỉ chạy ở claude); App D chỉ có văn bản
    q("insert into app_policies(app_id,capability_id,allowed_models,pool_id,fallback_pool_id,enabled) values (1,3,null,1,2,1)")
    q("insert into app_policies(app_id,capability_id,allowed_models,pool_id,enabled) values (2,1,null,1,1)")
    from gateway.auth import hash_key as h
    keys = [(1, KEY_A, None, None), (1, KEY_REVOKED, None, "2026-01-01 00:00:00"), (1, KEY_EXPIRED, "2026-11-01 00:00:00", None),
            (2, KEY_B, None, None), (3, KEY_C, None, None)]
    for app_id, k, exp, rev in keys:
        q("insert into app_keys(app_id,name,key_prefix,key_hash,expires_at,revoked_at) values (?,?,?,?,?,?)", (app_id, "k", k[:11], h(k, PEPPER), exp, rev))
    q("insert into model_weights(model,weight) values ('haiku',0.25),('sonnet',1),('grok-4.7',1)")
    c.commit()
    return c


@pytest.fixture
def env(tmp_path):
    path = str(tmp_path / "control.sqlite")
    conn = seed(path)
    settings = Settings(database_url="sqlite:///" + path, key_pepper=PEPPER, runner_secrets=dict(SECRETS), admin_token=ADMIN)
    runner, clock = FakeRunner(), Clock()
    app = create_app(settings, runner_client=runner, clock=clock)
    client = TestClient(app, raise_server_exceptions=True)

    class E:
        pass
    e = E()
    e.db, e.runner, e.clock, e.client, e.app, e.settings, e.path = conn, runner, clock, client, app, settings, path

    def post(model="claude:haiku", key=KEY_A, content="Chào bạn", **body):
        b = {"model": model, "messages": [{"role": "user", "content": content}], **body}
        return client.post("/v1/chat/completions", json=b, headers={"Authorization": f"Bearer {key}"} if key else {})
    e.post = post
    e.limit = lambda **kw: (conn.execute("insert into app_limits(app_id,rpm,max_concurrency,units_per_day,units_per_month,mode,capability_code) values (?,?,?,?,?,?,?)",
                                         (kw.get("app", 1), kw.get("rpm"), kw.get("conc"), kw.get("day"), kw.get("month"), kw.get("mode", "hard"), kw.get("cap"))), conn.commit())
    e.events = lambda: [dict(zip(("account", "status", "error_class", "in", "out", "w", "model"), r)) for r in conn.execute(
        "select a.label,u.status,u.error_class,u.input_units,u.output_units,u.weighted_units,u.model from usage_events u join accounts a on a.id=u.account_id order by u.id")]
    return e
