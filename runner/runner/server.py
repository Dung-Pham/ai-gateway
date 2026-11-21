import hmac
import json
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

from . import __version__
from .adapters import make_adapter
from .config import sha256_file
from .context import Ctx
from .errors import RunnerError
from .proc import build_env, run_cli


class RunnerService:
    """Trạng thái dùng chung: giới hạn song song và NGẮT MẠCH khi CLI vi phạm chính sách."""

    def __init__(self, cfg, adapter=None):
        self.cfg = cfg
        self.adapter = adapter or make_adapter(cfg)
        self.sem = threading.BoundedSemaphore(cfg.max_concurrency)
        self.tripped = False
        self.inflight = 0
        self.lock = threading.Lock()
        from .login import LoginManager
        self.login = LoginManager(cfg, self.adapter)

    def execute(self, req: dict):
        """Trả (http_status, body). CLI lỗi vẫn là HTTP 200 với ok=false; chỉ lỗi truyền tải mới dùng mã khác."""
        if self.tripped:
            return 503, {"ok": False, "error_class": "tripped", "message": "Runner đã ngắt mạch vì vi phạm chính sách."}
        try:
            self.adapter.validate(req)
        except RunnerError as e:
            return 400, {"ok": False, "error_class": e.error_class, "message": e.message}
        if not self.sem.acquire(blocking=False):
            return 503, {"ok": False, "error_class": "busy", "message": "Hết chỗ chạy song song."}
        with self.lock:
            self.inflight += 1
        ctx = None
        t0 = time.monotonic()
        try:
            ctx = Ctx.create(self.cfg, str(req.get("request_id", ""))[:40])
            ctx.model = req["model"]
            ctx.capability = req.get("capability", "text")
            ctx.search = req.get("_search")
            self.adapter.prepare(ctx)
            args, stdin, extra = self.adapter.command(ctx, req)
            env = build_env(self.cfg, ctx.home, ctx.tmp, extra)
            rc, out, err = run_cli(args, stdin_bytes=stdin, env=env, cwd=ctx.work, timeout=self.cfg.timeout_s, max_out=self.cfg.max_output_bytes)
            result = self.adapter.parse(ctx, rc, out, err)
            self.adapter.post_check(ctx)
            result["duration_ms"] = int((time.monotonic() - t0) * 1000)
            return 200, {"ok": True, **result}
        except RunnerError as e:
            if e.error_class == "policy_violation":
                self.tripped = True
                print(f"[runner] NGẮT MẠCH: {e.message}", file=sys.stderr, flush=True)
            return 200, {"ok": False, "error_class": e.error_class, "message": e.message,
                         "duration_ms": int((time.monotonic() - t0) * 1000)}
        except Exception as e:  # không để lộ chi tiết nội bộ hay nội dung
            print(f"[runner] lỗi không lường trước: {type(e).__name__}", file=sys.stderr, flush=True)
            return 200, {"ok": False, "error_class": "upstream_error", "message": "Lỗi nội bộ runner."}
        finally:
            if ctx is not None:
                try:
                    self.adapter.finish(ctx)
                except Exception as e:
                    print(f"[runner] không ghi ngược token: {type(e).__name__}", file=sys.stderr, flush=True)
                ctx.destroy()
            with self.lock:
                self.inflight -= 1
            self.sem.release()


def make_handler(service: RunnerService):
    cfg = service.cfg

    class Handler(BaseHTTPRequestHandler):
        protocol_version = "HTTP/1.1"
        server_version = "runner"
        sys_version = ""

        def setup(self):
            super().setup()
            self.connection.settimeout(30)

        # Không bao giờ ghi nội dung; chỉ dòng yêu cầu ngắn (không có thân).
        def log_message(self, fmt, *args):
            print("[runner] " + (fmt % args), file=sys.stderr, flush=True)

        def _send(self, code, obj, extra=None):
            body = json.dumps(obj, ensure_ascii=False).encode()
            self.send_response(code)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.send_header("Cache-Control", "no-store")
            for k, v in (extra or {}).items():
                self.send_header(k, v)
            self.end_headers()
            self.wfile.write(body)

        def _authed(self):
            got = self.headers.get("Authorization", "")
            return got.startswith("Bearer ") and hmac.compare_digest(got[7:].encode(), cfg.secret.encode())

        def do_GET(self):
            if self.path == "/healthz":
                return self._send(200, {"ok": True, "provider": cfg.provider, "tripped": service.tripped, "inflight": service.inflight})
            if not self._authed():
                return self._send(401, {"ok": False, "error_class": "bad_request", "message": "Không có quyền."})
            if self.path.startswith("/v1/login/status"):
                from urllib.parse import parse_qs, urlparse
                sid = (parse_qs(urlparse(self.path).query).get("session_id") or [""])[0]
                try:
                    return self._send(200, service.login.status(sid))
                except RunnerError as e:
                    return self._send(200, {"ok": False, "error_class": e.error_class, "message": e.message})
            if self.path == "/v1/info":
                info = service.adapter.info()
                info["auth"] = service.adapter.auth_info()
                info.update({"version": __version__, "cli_sha256": (sha256_file(cfg.cli_bin)[:16] if cfg.cli_sha256 else None),
                             "max_concurrency": cfg.max_concurrency, "tripped": service.tripped})
                return self._send(200, info)
            self._send(404, {"ok": False, "message": "Không có đường dẫn này."})

        def do_POST(self):
            if not self._authed():
                return self._send(401, {"ok": False, "error_class": "bad_request", "message": "Không có quyền."})
            if self.path.startswith("/v1/login/"):
                return self._login_post()
            if self.path != "/v1/run":
                return self._send(404, {"ok": False, "message": "Không có đường dẫn này."})
            try:
                n = int(self.headers.get("Content-Length", "-1"))
            except ValueError:
                n = -1
            if n < 0:
                return self._send(411, {"ok": False, "error_class": "bad_request", "message": "Thiếu Content-Length."})
            if n > cfg.max_body:
                self.close_connection = True
                return self._send(413, {"ok": False, "error_class": "bad_request", "message": "Thân yêu cầu quá lớn."}, {"Connection": "close"})
            try:
                req = json.loads(self.rfile.read(n))
                if not isinstance(req, dict):
                    raise ValueError
            except ValueError:
                return self._send(400, {"ok": False, "error_class": "bad_request", "message": "JSON không hợp lệ."})
            code, body = service.execute(req)
            self._send(code, body, {"Retry-After": "1"} if code == 503 else None)

        def _login_post(self):
            """Đăng nhập từ giao diện. Thân yêu cầu nhỏ; mã người dùng KHÔNG bao giờ được ghi log hay trả lại."""
            try:
                n = int(self.headers.get("Content-Length", "0"))
                req = json.loads(self.rfile.read(n)) if 0 < n <= 4096 else {}
                if not isinstance(req, dict):
                    raise ValueError
            except ValueError:
                return self._send(400, {"ok": False, "error_class": "bad_request", "message": "JSON không hợp lệ."})
            try:
                if self.path == "/v1/login/start":
                    return self._send(200, service.login.start())
                if self.path == "/v1/login/code":
                    return self._send(200, service.login.submit(str(req.get("session_id", "")), str(req.get("code", ""))))
                if self.path == "/v1/login/cancel":
                    return self._send(200, service.login.cancel(str(req.get("session_id", ""))))
            except RunnerError as e:
                return self._send(200, {"ok": False, "error_class": e.error_class, "message": e.message})
            self._send(404, {"ok": False, "message": "Không có đường dẫn này."})

    return Handler


def serve(service: RunnerService):
    srv = ThreadingHTTPServer((service.cfg.host, service.cfg.port), make_handler(service))
    srv.daemon_threads = True
    return srv
