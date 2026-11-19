"""Truy cập CSDL của giao diện điều khiển. Lược đồ lấy bằng reflection từ chính CSDL (hợp đồng: contracts/control_schema.sql)."""
import json
from datetime import datetime

from sqlalchemy import MetaData, Table, create_engine, func, insert, select, update
from sqlalchemy.engine import Engine
from sqlalchemy.pool import StaticPool

TABLES = ("providers", "accounts", "pools", "pool_members", "capabilities", "apps", "app_keys", "app_policies", "app_limits",
          "usage_events", "model_weights")


def make_engine(url: str) -> Engine:
    if url.startswith("sqlite") and ":memory:" in url:
        return create_engine(url, connect_args={"check_same_thread": False}, poolclass=StaticPool)
    if url.startswith("sqlite"):
        return create_engine(url, connect_args={"check_same_thread": False})
    return create_engine(url, pool_pre_ping=True)


class Store:
    def __init__(self, engine: Engine):
        self.engine = engine
        self.md = MetaData()
        self.t = {n: Table(n, self.md, autoload_with=engine) for n in TABLES}

    # ---- khóa và ứng dụng
    def find_key(self, key_hash: str, now: datetime):
        k, a = self.t["app_keys"], self.t["apps"]
        q = (select(k.c.id.label("key_id"), a.c.id.label("app_id"), a.c.name, a.c.slug, a.c.status)
             .join(a, a.c.id == k.c.app_id)
             .where(k.c.key_hash == key_hash, k.c.revoked_at.is_(None)))
        with self.engine.connect() as c:
            row = c.execute(q).mappings().first()
            if not row:
                return None
            exp = c.execute(select(k.c.expires_at).where(k.c.id == row["key_id"])).scalar()
        if exp is not None and _dt(exp) <= now:
            return None
        return dict(row)

    def touch_key(self, key_id: int, now: datetime):
        k = self.t["app_keys"]
        with self.engine.begin() as c:
            c.execute(update(k).where(k.c.id == key_id).values(last_used_at=now))

    # ---- chính sách và hạn mức
    def policy(self, app_id: int, capability: str):
        p, cap = self.t["app_policies"], self.t["capabilities"]
        q = (select(p.c.allowed_models, p.c.pool_id, p.c.fallback_pool_id, p.c.enabled.label("policy_enabled"), cap.c.enabled.label("cap_enabled"))
             .join(cap, cap.c.id == p.c.capability_id)
             .where(p.c.app_id == app_id, cap.c.code == capability))
        with self.engine.connect() as c:
            row = c.execute(q).mappings().first()
        if not row:
            return None
        r = dict(row)
        am = r["allowed_models"]
        if isinstance(am, str):
            try:
                am = json.loads(am)
            except ValueError:
                am = None
        r["allowed_models"] = [str(x) for x in am] if isinstance(am, list) else []
        r["policy_enabled"], r["cap_enabled"] = bool(r["policy_enabled"]), bool(r["cap_enabled"])
        return r

    def limits(self, app_id: int, capability: str):
        l = self.t["app_limits"]
        with self.engine.connect() as c:
            rows = c.execute(select(l).where(l.c.app_id == app_id, (l.c.capability_code.is_(None)) | (l.c.capability_code == capability))).mappings().all()
        return [dict(r) for r in rows]

    def units_since(self, app_id: int, since: datetime) -> int:
        u = self.t["usage_events"]
        with self.engine.connect() as c:
            v = c.execute(select(func.coalesce(func.sum(u.c.weighted_units), 0)).where(u.c.app_id == app_id, u.c.ts >= since, u.c.status == "ok")).scalar()
        return int(v or 0)

    def model_weight(self, model: str) -> float:
        w = self.t["model_weights"]
        with self.engine.connect() as c:
            v = c.execute(select(w.c.weight).where(w.c.model == model)).scalar()
        return float(v) if v is not None else 1.0

    def known_models(self):
        w = self.t["model_weights"]
        with self.engine.connect() as c:
            return [r[0] for r in c.execute(select(w.c.model).order_by(w.c.model))]

    # ---- tài khoản và pool
    def pool_accounts(self, pool_id: int):
        m, a, pr, pl = self.t["pool_members"], self.t["accounts"], self.t["providers"], self.t["pools"]
        q = (select(a.c.id, a.c.label, a.c.status, a.c.cooldown_until, a.c.max_concurrency, a.c.runner_url, pr.c.code.label("provider"),
                    m.c.priority, m.c.weight, pl.c.strategy)
             .join(a, a.c.id == m.c.account_id).join(pr, pr.c.id == a.c.provider_id).join(pl, pl.c.id == m.c.pool_id)
             .where(m.c.pool_id == pool_id).order_by(m.c.priority, a.c.id))
        with self.engine.connect() as c:
            return [dict(r) for r in c.execute(q).mappings()]

    # ---- ghi nhận sử dụng (chỉ INSERT)
    def insert_event(self, **v):
        with self.engine.begin() as c:
            c.execute(insert(self.t["usage_events"]).values(**v))


def _dt(v):
    if isinstance(v, datetime):
        return v
    return datetime.fromisoformat(str(v))
