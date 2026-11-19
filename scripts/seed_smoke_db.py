#!/usr/bin/env python3
"""Tạo CSDL SQLite thử cho gateway từ hợp đồng, kèm tài khoản/pool/ứng dụng/khóa mẫu. CHỈ dùng dev/smoke.

  seed_smoke_db.py <đường_dẫn_sqlite> <tệp_pepper>    # in khóa API thử MỘT lần
"""
import os
import secrets
import sqlite3
import sys

sys.path.insert(0, os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "gateway"))
from gateway.auth import hash_key  # noqa: E402

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
path, pepper_file = sys.argv[1], sys.argv[2]
pepper = open(pepper_file, encoding="utf-8").read().strip()
if os.path.exists(path):
    os.remove(path)
c = sqlite3.connect(path)
c.executescript(open(os.path.join(ROOT, "contracts", "control_schema.sql"), encoding="utf-8").read())
q = c.execute
for i, (code, name) in enumerate([("claude", "Claude"), ("grok", "Grok"), ("agy", "Antigravity")], 1):
    q("insert into providers(id,code,name,color) values (?,?,?,?)", (i, code, name, "#000"))
for i, (label, prov) in enumerate([("claude-01", 1), ("grok-01", 2), ("agy-01", 3)], 1):
    q("insert into accounts(id,provider_id,label,status,max_concurrency,runner_url) values (?,?,?,?,?,?)", (i, prov, label, "active", 2, f"http://runner-{label}:8800"))
q("insert into pools(id,name,strategy) values (1,'Thử','priority')")
q("insert into pool_members(pool_id,account_id,priority,weight) values (1,1,1,1),(1,2,2,1),(1,3,3,1)")
q("insert into capabilities(id,code,name,risk_level,enabled) values (1,'text','Văn bản','low',1)")
q("insert into apps(id,name,slug,status) values (1,'Ứng dụng thử','thu','active')")
q("insert into app_policies(app_id,capability_id,allowed_models,pool_id,enabled) values (1,1,null,1,1)")
q("insert into model_weights(model,weight) values ('haiku',0.25),('sonnet',1),('opus',5),('grok-4.7',1),('gemini-4-neon-high',1)")
key = "gw_" + secrets.token_hex(20)
q("insert into app_keys(app_id,name,key_prefix,key_hash) values (1,'thử',?,?)", (key[:11], hash_key(key, pepper)))
c.commit()
print(key)
