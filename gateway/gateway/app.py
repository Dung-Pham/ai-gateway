import hmac
import json
import logging
import math
import re
import secrets
import sys
import time
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse, PlainTextResponse
from starlette.concurrency import run_in_threadpool as threaded

from . import __version__
from .auth import bearer, hash_key
from .config import Settings
from .db import Store, make_engine
from .errors import ApiError
from .messages import flatten
from .search import MAX_RESULTS, domains, reclean
from .runner_client import HttpRunnerClient, RunnerRef, validate_runner_url
from .state import State

log = logging.getLogger("gateway")
PROVIDERS = ("claude", "grok", "agy", "codex")
MODEL_RE = re.compile(r"^[A-Za-z0-9._-]{1,64}$")
RETRYABLE = {"rate_limited", "auth_expired", "busy", "tripped"}


class Clock:
    def __init__(self, tz: str):
        self.tz = ZoneInfo(tz)

    def now(self) -> datetime:          # giờ địa phương, KHÔNG múi giờ (giống cách Laravel ghi vào CSDL)
        return datetime.now(self.tz).replace(tzinfo=None, microsecond=0)

    def epoch(self) -> float:
        return time.time()


def create_app(settings: Settings, *, engine=None, runner_client=None, clock=None) -> FastAPI:
    store = Store(engine or make_engine(settings.database_url))
    state = State()
    runner = runner_client or HttpRunnerClient(settings.runner_timeout_s)
    clock = clock or Clock(settings.timezone)
    app = FastAPI(title="Cổng AI (gateway)", version=__version__, docs_url=None, redoc_url=None, openapi_url=None)
    app.state.store, app.state.gw_state, app.state.clock = store, state, clock

    @app.exception_handler(ApiError)
    async def api_error(_req, e: ApiError):
        state.inc(f"errors_total{{code=\"{e.code}\"}}")
        return JSONResponse(e.body(), status_code=e.status, headers=e.headers)

    async def authenticate(request: Request) -> dict:
        key = bearer(request.headers.get("authorization"))
        ident = await threaded(store.find_key, hash_key(key, settings.key_pepper), clock.now())
        if not ident:
            raise ApiError(401, "invalid_api_key", "Thiếu hoặc sai khóa API.")
        if str(ident["status"]) != "active":
            raise ApiError(403, "app_suspended", "Ứng dụng đang bị tạm ngưng.")
        return ident

    def admin_only(request: Request):
        if not settings.admin_token:
            raise ApiError(404, "not_found", "Không có đường dẫn này.")
        got = request.headers.get("x-admin-token", "")
        if not hmac.compare_digest(got.encode(), settings.admin_token.encode()):
            raise ApiError(401, "invalid_admin_token", "Không có quyền.")

    # ------------------------------------------------------------------ giới hạn theo ứng dụng
    async def enforce_limits(app_id: int, limits: list[dict], capability: str) -> list[str]:
        now = clock.now()
        warnings: list[str] = []
        hard = [l for l in limits if l["mode"] == "hard"]
        hard_rpm = min([l["rpm"] for l in hard if l["rpm"]], default=0)
        if not state.check_rpm((app_id, capability), hard_rpm, clock.epoch()):   # cửa sổ riêng cho từng khả năng
            raise ApiError(429, "app_rpm", "Vượt số yêu cầu mỗi phút của ứng dụng.", {"Retry-After": "30"})
        hard_conc = min([l["max_concurrency"] for l in hard if l["max_concurrency"]], default=0)
        if hard_conc and state.app_inflight[app_id] >= hard_conc:
            raise ApiError(429, "app_concurrency", "Vượt số yêu cầu song song của ứng dụng.", {"Retry-After": "5"})
        for field, since, label in (("units_per_day", now.replace(hour=0, minute=0, second=0), "app_daily_units"),
                                    ("units_per_month", now.replace(day=1, hour=0, minute=0, second=0), "app_monthly_units")):
            for l in limits:
                lim = l[field]
                if not lim:
                    continue
                used = await threaded(store.units_since, app_id, since, l["capability_code"])   # hạn mức riêng của một khả năng chỉ đếm khả năng đó
                if used >= lim:
                    if l["mode"] == "hard":
                        nxt = (since + timedelta(days=1)) if field == "units_per_day" else (since.replace(day=28) + timedelta(days=4)).replace(day=1)
                        raise ApiError(429, label, "Ứng dụng đã dùng hết hạn mức.", {"Retry-After": str(max(1, int((nxt - now).total_seconds())))})
                    warnings.append(f"soft_limit_exceeded:{label}")
        return warnings

    # ------------------------------------------------------------------ chọn tài khoản
    def order(accs: list[dict], strategy: str, key) -> list[dict]:
        if strategy == "priority":
            return sorted(accs, key=lambda a: (a["priority"], a["id"]))
        if strategy == "round_robin":
            if not accs:
                return accs
            i = state.rr[key] % len(accs); state.rr[key] += 1
            return accs[i:] + accs[:i]
        return sorted(accs, key=lambda a: (state.inflight[a["label"]] / max(1, a["max_concurrency"]), a["priority"], a["id"]))

    def usable(a: dict) -> bool:
        if str(a["status"]) != "active":
            return False
        now = clock.epoch()
        if state.cooling(a["label"], now) or a["label"] in state.flags:
            return False
        cu = a.get("cooldown_until")
        if cu:
            cu = cu if isinstance(cu, datetime) else datetime.fromisoformat(str(cu))
            if cu > clock.now():
                return False
        return True

    # ------------------------------------------------------------------ POST /v1/chat/completions
    @app.post("/v1/chat/completions")
    async def chat(request: Request):
        t0 = time.monotonic()
        state.inc("requests_total")
        ident = await authenticate(request)
        raw = await request.body()
        if len(raw) > settings.max_body:
            raise ApiError(413, "request_too_large", "Thân yêu cầu quá lớn.")
        try:
            body = json.loads(raw)
            assert isinstance(body, dict)
        except (ValueError, AssertionError):
            raise ApiError(400, "invalid_request", "JSON không hợp lệ.")
        if body.get("stream"):
            raise ApiError(400, "stream_not_supported", "Chưa hỗ trợ stream.")
        system, user = flatten(body.get("messages"))
        model_req = body.get("model")
        if not isinstance(model_req, str) or not model_req:
            raise ApiError(400, "invalid_request", "Thiếu model.")

        app_id = ident["app_id"]
        pol = await threaded(store.policy, app_id, "text")
        if not pol or not pol["policy_enabled"] or not pol["cap_enabled"]:
            raise ApiError(403, "capability_not_granted", "Ứng dụng chưa được cấp khả năng văn bản.")

        primary = await threaded(store.pool_accounts, pol["pool_id"]) if pol["pool_id"] else []
        fallback = await threaded(store.pool_accounts, pol["fallback_pool_id"]) if pol["fallback_pool_id"] else []
        strategy = (primary[0]["strategy"] if primary else None) or "least_used"

        if ":" in model_req:
            provider, model = model_req.split(":", 1)
        else:
            provs = sorted({a["provider"] for a in primary + fallback})
            if len(provs) != 1:
                raise ApiError(400, "model_requires_provider", "Dùng dạng provider:model (ví dụ claude:haiku).")
            provider, model = provs[0], model_req
        if provider not in PROVIDERS or not MODEL_RE.match(model):
            raise ApiError(404, "model_not_found", "Model không tồn tại.")
        allowed = pol["allowed_models"]
        if allowed and model not in allowed and f"{provider}:{model}" not in allowed:
            raise ApiError(403, "model_not_allowed", "Ứng dụng không được dùng model này.")

        limits = await threaded(store.limits, app_id, "text")
        warnings = await enforce_limits(app_id, limits, "text")

        cands = [a for a in order([a for a in primary if a["provider"] == provider], strategy, (app_id, "p"))] + \
                [a for a in order([a for a in fallback if a["provider"] == provider and a["id"] not in {x["id"] for x in primary}], "least_used", (app_id, "f"))]
        request_id = "req_" + secrets.token_hex(6)
        state.app_inflight[app_id] += 1
        try:
            def build(res, tin, tout):
                return {"id": "chatcmpl-" + request_id[4:], "object": "chat.completion", "created": int(clock.epoch()), "model": f"{provider}:{model}",
                        "choices": [{"index": 0, "message": {"role": "assistant", "content": res["text"]}, "finish_reason": "stop"}],
                        "usage": {"prompt_tokens": tin, "completion_tokens": tout, "total_tokens": tin + tout}}
            return await dispatch(ident, request_id, "text", provider, model, {"system": system, "user": user}, cands, warnings, t0, build)
        finally:
            state.app_inflight[app_id] = max(0, state.app_inflight[app_id] - 1)

    async def record(ident, acc, capability, model, status, error_class, units, latency_ms, request_id):
        try:
            await threaded(lambda: store.insert_event(
                ts=clock.now(), app_id=ident["app_id"], account_id=acc["id"], capability=capability, model=model,
                input_units=units[0], output_units=units[1], weighted_units=units[2], latency_ms=latency_ms,
                status=status, error_class=error_class, request_id=request_id, estimated=False))
        except Exception as e:
            state.inc("metering_failed_total")
            log.error(json.dumps({"event": "metering_failed", "request_id": request_id, "type": type(e).__name__}))

    async def dispatch(ident, request_id, capability, provider, model, payload, cands, warnings, t0, build):
        """Dùng chung cho mọi khả năng: chọn tài khoản, gọi runner, thử lại tối đa 1 lần khi hãng báo giới hạn/hết phiên."""
        attempts, last = 0, None
        for acc in cands:
            if attempts >= 2:
                break
            if not usable(acc) or not state.acquire_slot(acc["label"], acc["max_concurrency"]):
                continue
            try:
                secret = settings.runner_secrets.get(acc["label"])
                if not secret:
                    log.error(json.dumps({"event": "no_runner_secret", "account": acc["label"]}))
                    continue
                try:
                    url = validate_runner_url(acc["runner_url"], settings.runner_host_regex, settings.allow_runner_port_min)
                except ApiError:
                    log.error(json.dumps({"event": "bad_runner_url", "account": acc["label"]}))
                    continue
                attempts += 1
                t1 = time.monotonic()
                _code, res = await runner.run(RunnerRef(acc["label"], url, secret), {"request_id": request_id, "capability": capability, "model": model, **payload})
            finally:
                state.release_slot(acc["label"])
            lat = int((time.monotonic() - t1) * 1000)
            if res.get("ok"):
                return await success(ident, acc, capability, provider, model, res, request_id, lat, warnings, build)
            cls = res.get("error_class", "upstream_error")
            last = (cls, res.get("message", ""))
            state.last_error[acc["label"]] = cls
            state.inc(f"runner_errors_total{{class=\"{cls}\"}}")
            if cls == "rate_limited":
                state.set_cooldown(acc["label"], _resume_at(res, clock.epoch(), settings.default_cooldown_s))
                await record(ident, acc, capability, model, "rate_limited", cls, (0, 0, 0), lat, request_id)
            elif cls == "auth_expired":
                state.flags[acc["label"]] = "auth_expired"
                await record(ident, acc, capability, model, "error", cls, (0, 0, 0), lat, request_id)
            elif cls == "busy":
                pass
            elif cls == "tripped":
                state.flags[acc["label"]] = "tripped"
            else:
                if cls == "policy_violation":
                    state.flags[acc["label"]] = "tripped"
                await record(ident, acc, capability, model, "error", cls, (0, 0, 0), lat, request_id)
            if cls not in RETRYABLE:
                break
        if attempts == 0 and last is None:
            raise ApiError(503, "no_account_available", "Không có tài khoản AI nào sẵn sàng cho yêu cầu này.", {"Retry-After": "30"})
        cls = (last or ("upstream_error", ""))[0]
        status, code = {"rate_limited": (429, "all_accounts_rate_limited"), "auth_expired": (503, "no_account_available"),
                        "busy": (503, "no_account_available"), "tripped": (503, "no_account_available"),
                        "timeout": (504, "upstream_timeout"), "policy_violation": (502, "upstream_policy_violation"),
                        "tool_denied": (502, "empty_response"), "bad_request": (400, "invalid_request"),
                        "unsupported": (501, "capability_not_supported")}.get(cls, (502, "upstream_error"))
        raise ApiError(status, code, "Yêu cầu không hoàn thành: " + cls, {"Retry-After": "30"} if status in (429, 503) else {})

    async def success(ident, acc, capability, provider, model, res, request_id, lat, warnings, build):
        u = res.get("usage") or {}
        tin = int(u.get("input_tokens", 0)) + int(u.get("cache_read_tokens", 0)) + int(u.get("cache_creation_tokens", 0))
        tout = int(u.get("output_tokens", 0))
        weight = await threaded(store.model_weight, model)
        weighted = int(round((tin + tout * 3) / 100 * weight))
        if res.get("quota"):
            state.quota[acc["label"]] = res["quota"]
        state.last_error.pop(acc["label"], None)
        await record(ident, acc, capability, model, "ok", None, (tin, tout, weighted), lat, request_id)
        state.inc("requests_ok_total")
        if ident.get("key_id"):
            try:
                await threaded(store.touch_key, ident["key_id"], clock.now())
            except Exception:
                pass
        headers = {"X-Gateway-Account": acc["label"], "X-Gateway-Request-Id": request_id, "X-Gateway-Units": str(weighted)}
        if warnings:
            headers["X-Gateway-Warning"] = ",".join(warnings)
        return JSONResponse(build(res, tin, tout), headers=headers)

    # ------------------------------------------------------------------ POST /v1/search (tương thích cầu nối cũ)
    @app.post("/v1/search")
    async def search(request: Request):
        t0 = time.monotonic()
        state.inc("requests_total")
        ident = await authenticate(request)
        raw = await request.body()
        if len(raw) > 100_000:
            raise ApiError(413, "request_too_large", "Thân yêu cầu quá lớn.")
        try:
            body = json.loads(raw)
            assert isinstance(body, dict)
        except (ValueError, AssertionError):
            raise ApiError(400, "invalid_request", "JSON không hợp lệ.")
        query = body.get("query")
        if not isinstance(query, str) or not query.strip() or len(query) > 400:
            raise ApiError(400, "invalid_request", "Truy vấn phải là chuỗi 1 đến 400 ký tự.")
        try:
            n = max(1, min(MAX_RESULTS, int(body.get("max_results") or 5)))
        except (TypeError, ValueError):
            raise ApiError(400, "invalid_request", "max_results không hợp lệ.")
        include, exclude = domains(body.get("include_domains")), domains(body.get("exclude_domains"))

        app_id = ident["app_id"]
        pol = await threaded(store.policy, app_id, "web_search")
        if not pol or not pol["policy_enabled"] or not pol["cap_enabled"]:
            raise ApiError(403, "capability_not_granted", "Ứng dụng chưa được cấp khả năng tìm web.")
        model = body.get("model") if isinstance(body.get("model"), str) and body.get("model") else settings.search_model
        if ":" in model:
            prov, model = model.split(":", 1)
            if prov != "claude":
                raise ApiError(404, "model_not_found", "Tìm web hiện chỉ hỗ trợ hãng claude.")
        if not MODEL_RE.match(model):
            raise ApiError(404, "model_not_found", "Model không tồn tại.")
        allowed = pol["allowed_models"]
        if allowed and model not in allowed and f"claude:{model}" not in allowed:
            raise ApiError(403, "model_not_allowed", "Ứng dụng không được dùng model này cho tìm web.")

        limits = await threaded(store.limits, app_id, "web_search")
        warnings = await enforce_limits(app_id, limits, "web_search")
        primary = await threaded(store.pool_accounts, pol["pool_id"]) if pol["pool_id"] else []
        fallback = await threaded(store.pool_accounts, pol["fallback_pool_id"]) if pol["fallback_pool_id"] else []
        strategy = (primary[0]["strategy"] if primary else None) or "least_used"
        cands = order([a for a in primary if a["provider"] == "claude"], strategy, (app_id, "sp")) + \
            order([a for a in fallback if a["provider"] == "claude" and a["id"] not in {x["id"] for x in primary}], "least_used", (app_id, "sf"))
        request_id = "req_" + secrets.token_hex(6)
        payload = {"query": query.strip(), "max_results": n, "include_domains": include, "exclude_domains": exclude}

        def build(res, tin, tout):
            # Không tin runner tuyệt đối: kiểm tra lại URL, độ dài, bộ lọc tên miền trước khi trả cho ứng dụng.
            return {"results": reclean(res.get("results"), n, include, exclude)}
        state.app_inflight[app_id] += 1
        try:
            return await dispatch(ident, request_id, "web_search", "claude", model, payload, cands, warnings, t0, build)
        finally:
            state.app_inflight[app_id] = max(0, state.app_inflight[app_id] - 1)

    # ------------------------------------------------------------------ GET /v1/models
    @app.get("/v1/models")
    async def models(request: Request):
        ident = await authenticate(request)
        pol = await threaded(store.policy, ident["app_id"], "text")
        if not pol or not pol["policy_enabled"] or not pol["cap_enabled"]:
            return {"object": "list", "data": []}
        accs = (await threaded(store.pool_accounts, pol["pool_id"]) if pol["pool_id"] else []) + \
               (await threaded(store.pool_accounts, pol["fallback_pool_id"]) if pol["fallback_pool_id"] else [])
        provs = sorted({a["provider"] for a in accs})
        ids = []
        for m in pol["allowed_models"] or ["*"]:
            ids += [m] if ":" in m else [f"{p}:{m}" for p in provs]
        return {"object": "list", "data": [{"id": i, "object": "model", "owned_by": "gateway"} for i in sorted(set(ids))]}

    @app.get("/healthz")
    async def healthz():
        return {"ok": True, "version": __version__}

    # ------------------------------------------------------------------ nội bộ (cần X-Admin-Token)
    @app.get("/internal/state")
    async def internal_state(request: Request):
        admin_only(request)
        now = clock.epoch()
        labels = set(state.inflight) | set(state.cooldown_until) | set(state.flags) | set(state.last_error) | set(state.quota)
        return {"accounts": {l: {"inflight": state.inflight.get(l, 0), "cooldown_s": max(0, int(state.cooldown_until.get(l, 0) - now)),
                                 "flag": state.flags.get(l), "last_error": state.last_error.get(l), "quota": state.quota.get(l),
                                 "checked_at": state.checked_at.get(l)} for l in sorted(labels | set(state.checked_at))},
                "counters": dict(state.counters)}

    @app.post("/internal/accounts/{label}/reset")
    async def reset_account(label: str, request: Request):
        admin_only(request)
        state.flags.pop(label, None); state.cooldown_until.pop(label, None); state.last_error.pop(label, None)
        return {"ok": True}

    @app.post("/internal/accounts/{label}/check")
    async def check_account(label: str, request: Request):
        """"Kiểm tra ngay": một lượt gọi rất nhỏ qua runner của tài khoản. Thành công thì gỡ cờ hết phiên/ngắt mạch; thất bại thì đặt cờ tương ứng."""
        admin_only(request)
        acc = await threaded(store.account_by_label, label)
        if not acc:
            raise ApiError(404, "account_not_found", "Không có tài khoản này.")
        secret = settings.runner_secrets.get(label)
        if not secret:
            return {"ok": False, "error_class": "no_runner_secret", "message": "Chưa khai báo bí mật runner cho tài khoản này."}
        url = validate_runner_url(acc["runner_url"], settings.runner_host_regex, settings.allow_runner_port_min)
        model = settings.check_models.get(acc["provider"], "haiku")
        t1 = time.monotonic()
        _code, res = await runner.run(RunnerRef(label, url, secret), {"request_id": "chk_" + secrets.token_hex(6), "capability": "text", "model": model,
                                                                       "system": "Trả lời đúng một từ.", "user": "ok"})
        lat = int((time.monotonic() - t1) * 1000)
        if res.get("ok"):
            state.flags.pop(label, None); state.last_error.pop(label, None)
            if res.get("quota"):
                state.quota[label] = res["quota"]
            state.checked_at[label] = clock.epoch()
            state.inc("account_checks_ok_total")
            return {"ok": True, "latency_ms": lat, "quota": res.get("quota") or {}}
        cls = res.get("error_class", "upstream_error")
        state.last_error[label] = cls
        if cls == "auth_expired":
            state.flags[label] = "auth_expired"
        elif cls == "rate_limited":
            state.set_cooldown(label, _resume_at(res, clock.epoch(), settings.default_cooldown_s))
        elif cls in ("tripped", "policy_violation"):
            state.flags[label] = "tripped"
        state.checked_at[label] = clock.epoch()
        state.inc(f"account_checks_failed_total{{class=\"{cls}\"}}")
        return {"ok": False, "error_class": cls, "latency_ms": lat, "message": str(res.get("message", ""))[:200]}

    @app.get("/metrics")
    async def metrics(request: Request):
        admin_only(request)
        lines = [f"gateway_{k.replace('{', '{', 1)} {v}" for k, v in sorted(state.counters.items())]
        return PlainTextResponse("\n".join(lines) + "\n")

    return app


def _resume_at(res: dict, now: float, default_s: int) -> float:
    """Thời điểm hết nghỉ: ưu tiên giờ reset do hãng báo (cửa sổ 5 giờ), nếu không thì mặc định."""
    five = (res.get("quota") or {}).get("five_hour") or {}
    r = five.get("resets_at")
    if isinstance(r, (int, float)) and r > now:
        return float(r)
    return now + default_s
