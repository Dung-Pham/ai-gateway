"""Đăng nhập tài khoản AI TỪ GIAO DIỆN: chạy CLI đăng nhập trong runner (container cứng hóa), đưa đường dẫn/mã thiết bị cho người dùng,
nhận mã xác nhận MỘT LẦN (hãng Claude) hoặc chờ người dùng xác nhận trên trang của hãng (Codex, Grok), rồi ghi token vào volume xác thực.

Đã quan sát THẬT (21/11/2026) với CLI Claude 2.1.308, Codex 0.163.0, Grok 1.0.70: cả ba in đường dẫn qua ống (không cần TTY);
Claude chờ mã dán ở stdin ("Paste code here if prompted >"), mã sai trả "Invalid code..." và CLI vẫn chờ; Codex/Grok in mã thiết bị rồi tự thăm dò.
Antigravity (agy) là giao diện dòng lệnh tương tác: CHƯA hỗ trợ, vẫn đăng nhập bằng terminal.

An ninh: mã người dùng dán KHÔNG được lưu, KHÔNG ghi log, KHÔNG nằm trong bất kỳ phản hồi nào; chỉ đi vào stdin của CLI. Thông điệp lỗi của CLI bị che mọi chuỗi giống token.
"""
import json
import os
import re
import secrets
import shutil
import signal
import subprocess
import tempfile
import threading
import time
from urllib.parse import urlparse

from .errors import RunnerError
from .proc import build_env

ANSI = re.compile(r"\x1b\[[0-9;?]*[a-zA-Z]")
URL_RE = re.compile(r"https://[^\s\"'<>]+")
CODE_RE = re.compile(r"\b[A-Z0-9]{4,6}-[A-Z0-9]{4,6}\b")
TOKENISH = re.compile(r"[A-Za-z0-9_\-\.=~#]{32,}")
USER_CODE_RE = re.compile(r"^[A-Za-z0-9._~#:/+=\-]{8,600}$")
MAX_ATTEMPTS = 5

SPECS = {
    "claude": {"args": ["auth", "login"], "env": "CLAUDE_CONFIG_DIR", "sub": ".claude", "token": ".credentials.json", "kind": "paste",
               "hosts": ("claude.com", "claude.ai", "platform.claude.com", "anthropic.com")},
    "codex": {"args": ["login", "--device-auth"], "env": "CODEX_HOME", "sub": ".codex", "token": "auth.json", "kind": "device",
              "hosts": ("auth.openai.com", "openai.com", "chatgpt.com")},
    "grok": {"args": ["login", "--device-code"], "env": "GROK_HOME", "sub": ".grok", "token": "auth.json", "kind": "device",
             "hosts": ("accounts.x.ai", "auth.x.ai", "x.ai", "grok.com")},
}


def redact(text: str) -> str:
    return TOKENISH.sub("…", text)[:300]


def host_ok(url: str, hosts) -> bool:
    u = urlparse(url)
    h = (u.hostname or "").lower()
    return u.scheme == "https" and u.username is None and u.password is None and any(h == x or h.endswith("." + x) for x in hosts)


class LoginSession:
    def __init__(self, mgr, spec, cfg, adapter):
        self.mgr, self.spec, self.cfg, self.adapter = mgr, spec, cfg, adapter
        self.id = "lg_" + secrets.token_hex(8)
        self.kind = spec["kind"]
        self.state, self.message = "starting", ""
        self.url = self.device_code = None
        self.attempts = 0
        self.lines = []
        self.created = time.monotonic()
        self.lock = threading.Lock()
        self.root = tempfile.mkdtemp(prefix="login-", dir=self._ensure_root())
        os.chmod(self.root, 0o700)
        self.home, self.work, self.tmp = (os.path.join(self.root, n) for n in ("home", "work", "tmp"))
        for d in (self.home, self.work, self.tmp):
            os.mkdir(d, 0o700)
        self.cfgdir = os.path.join(self.home, spec["sub"])
        os.mkdir(self.cfgdir, 0o700)
        env = build_env(cfg, self.home, self.tmp, {spec["env"]: self.cfgdir})
        self.proc = subprocess.Popen([cfg.cli_bin] + spec["args"], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                     env=env, cwd=self.work, start_new_session=True)
        threading.Thread(target=self._read, daemon=True).start()
        threading.Thread(target=self._watchdog, daemon=True).start()

    def _ensure_root(self):
        os.makedirs(self.cfg.work_root, mode=0o700, exist_ok=True)
        return self.cfg.work_root

    # ---- đọc đầu ra của CLI ------------------------------------------------
    def _read(self):
        try:
            for raw in iter(self.proc.stdout.readline, b""):
                self._on_line(ANSI.sub("", raw.decode("utf-8", "replace")).rstrip())
        except (OSError, ValueError):
            pass
        rc = self.proc.wait()
        self._finish(rc)

    def _on_line(self, line: str):
        with self.lock:
            if line.strip():
                self.lines = (self.lines + [redact(line.strip())])[-30:]
            if self.state in ("done", "failed", "cancelled"):
                return
            if self.url is None:
                m = URL_RE.search(line)
                if m:
                    if not host_ok(m.group(0), self.spec["hosts"]):
                        self.state, self.message = "failed", "Đường dẫn đăng nhập từ CLI không thuộc tên miền của hãng: đã chặn."
                        self._kill()
                        return
                    self.url = m.group(0)
            if self.kind == "device" and self.device_code is None:
                m = CODE_RE.search(line) or (CODE_RE.search(self.url or "") if self.url else None)
                if m:
                    self.device_code = m.group(0)
            if "invalid code" in line.lower() and self.state in ("verifying", "awaiting_code", "starting"):
                self.state, self.message = "awaiting_code", "Mã không đúng hoặc chưa đủ. Hãy sao chép TOÀN BỘ mã rồi dán lại."
                return
            if self.state == "starting" and self.url:
                if self.kind == "paste":
                    self.state = "awaiting_code"
                elif self.device_code:
                    self.state = "awaiting_device"

    def _finish(self, rc):
        with self.lock:
            if self.state in ("failed", "cancelled"):
                self._cleanup()
                return
        ok = rc == 0 and self._install()
        with self.lock:
            if ok:
                self.state, self.message = "done", "Đã lưu phiên đăng nhập."
            elif self.state != "failed":
                tail = " | ".join(self.lines[-3:])
                self.state, self.message = "failed", ("Đăng nhập chưa hoàn tất" + (f": {tail}" if tail else "") + f" (mã thoát {rc}).")[:300]
            self._cleanup()

    def _install(self) -> bool:
        src = os.path.join(self.cfgdir, self.spec["token"])
        try:
            data = open(src, "rb").read()
            obj = json.loads(data)
        except (OSError, ValueError):
            return False
        if not isinstance(obj, dict) or not obj:
            return False
        if self.adapter.name == "claude" and not ((obj.get("claudeAiOauth") or obj).get("accessToken")):
            return False
        dst = os.path.join(self.cfg.auth_dir, self.adapter.token_relpath)
        lock = getattr(self.adapter, "_refresh_lock", None)         # không ghi đè giữa lúc một lượt gọi đang làm mới token
        if lock is not None and not lock.acquire(timeout=30):
            return False
        try:
            os.makedirs(os.path.dirname(dst), mode=0o700, exist_ok=True)
            tmp = dst + ".new"
            fd = os.open(tmp, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
            with os.fdopen(fd, "wb") as f:
                f.write(data)
            os.replace(tmp, dst)
            os.chmod(dst, 0o600)
        finally:
            if lock is not None:
                lock.release()
        return True

    def _kill(self):
        try:
            os.killpg(self.proc.pid, signal.SIGKILL)
        except (ProcessLookupError, PermissionError):
            pass

    def _cleanup(self):
        shutil.rmtree(self.root, ignore_errors=True)

    def _watchdog(self):
        while time.monotonic() - self.created < self.mgr.ttl_s:
            if self.proc.poll() is not None:
                return
            time.sleep(min(1.0, max(0.05, self.mgr.ttl_s / 20)))
        with self.lock:
            if self.state not in ("done", "failed", "cancelled"):
                self.state, self.message = "failed", "Quá thời gian chờ đăng nhập. Bắt đầu lại."
        self._kill()

    # ---- thao tác từ người dùng --------------------------------------------
    def submit(self, code: str):
        with self.lock:
            if self.kind != "paste" or self.state != "awaiting_code":
                raise RunnerError("bad_request", "Phiên này không đang chờ mã dán.")
            code = (code or "").strip()
            if not USER_CODE_RE.match(code):
                raise RunnerError("bad_request", "Mã không hợp lệ (độ dài hoặc ký tự lạ). Hãy sao chép TOÀN BỘ mã.")
            self.attempts += 1
            if self.attempts > MAX_ATTEMPTS:
                self.state, self.message = "failed", "Nhập sai quá nhiều lần. Bắt đầu lại."
                self._kill()
                raise RunnerError("bad_request", self.message)
            self.state, self.message = "verifying", ""
            try:
                self.proc.stdin.write((code + "\n").encode())
                self.proc.stdin.flush()
            except (OSError, ValueError):
                self.state, self.message = "failed", "CLI đăng nhập đã dừng."
                raise RunnerError("upstream_error", self.message)

    def cancel(self):
        with self.lock:
            if self.state not in ("done", "failed"):
                self.state, self.message = "cancelled", "Đã hủy."
        self._kill()

    def view(self) -> dict:
        with self.lock:
            return {"ok": True, "session_id": self.id, "kind": self.kind, "state": self.state, "message": self.message,
                    "url": self.url, "device_code": self.device_code, "attempts": self.attempts,
                    "expires_in": max(0, int(self.mgr.ttl_s - (time.monotonic() - self.created)))}


class LoginManager:
    """Một phiên đăng nhập tại một thời điểm cho mỗi runner (mỗi runner = một tài khoản)."""

    def __init__(self, cfg, adapter, ttl_s: int = 900):
        self.cfg, self.adapter, self.ttl_s = cfg, adapter, ttl_s
        self.current: LoginSession | None = None
        self.lock = threading.Lock()

    def supported(self) -> bool:
        return self.adapter.name in SPECS

    def start(self) -> dict:
        if not self.supported():
            raise RunnerError("unsupported", "Hãng này chưa hỗ trợ đăng nhập từ giao diện: đăng nhập bằng terminal (scripts/aigw login).")
        with self.lock:
            if self.current is not None:
                self.current.cancel()
            self.current = LoginSession(self, SPECS[self.adapter.name], self.cfg, self.adapter)
            s = self.current
        deadline = time.monotonic() + 20
        while time.monotonic() < deadline and s.view()["state"] == "starting":     # chờ CLI in đường dẫn
            time.sleep(0.05)
        v = s.view()
        if v["state"] in ("failed", "cancelled"):
            raise RunnerError("policy_violation" if "tên miền" in v["message"] else "upstream_error", v["message"] or "Không bắt đầu được đăng nhập.")
        if v["state"] == "starting":
            s.cancel()
            raise RunnerError("upstream_error", "CLI không in đường dẫn đăng nhập (kiểm tra mạng của runner tới hãng).")
        return v

    def _get(self, sid: str) -> LoginSession:
        s = self.current
        if s is None or not secrets.compare_digest(s.id, str(sid)):
            raise RunnerError("bad_request", "Không có phiên đăng nhập này (đã hết hạn hoặc bị thay).")
        return s

    def status(self, sid: str) -> dict:
        return self._get(sid).view()

    def submit(self, sid: str, code: str) -> dict:
        s = self._get(sid)
        s.submit(code)
        return s.view()

    def cancel(self, sid: str) -> dict:
        s = self._get(sid)
        s.cancel()
        return s.view()
