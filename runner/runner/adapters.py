"""Ba adapter CLI. Mỗi cờ cứng hóa ở đây đã được KIỂM CHỨNG ở các đợt thăm dò (docs/KET_QUA_THAM_DO.md); đừng bỏ khi sửa.

- claude: --tools "" KHÔNG chặn connector claude.ai; phải thêm --strict-mcp-config + cấu hình MCP rỗng.
- grok:   --tools ""/--sandbox KHÔNG đủ; phải --disallowed-tools với ĐỦ tên công cụ; nội dung hội thoại bị CLI lưu ra đĩa nên
          GROK_HOME là thư mục tạm xóa sau mỗi yêu cầu.
- agy:    mặc định không tương tác tự từ chối công cụ (denied_actions); KHÔNG dùng --dangerously-skip-permissions và --mode plan.
- codex:  MẶC ĐỊNH chạy được lệnh shell, đọc tệp bất kỳ và tìm web (đã thử thật 20/11/2026). Phải tắt từng tính năng; danh sách công cụ vẫn
          còn nhưng gọi thì thất bại. Luồng sự kiện --json KHÔNG liệt kê mọi lượt gọi công cụ, nên không dựa vào đó để phát hiện: lớp chính
          là container của runner. KHÔNG dùng --dangerously-bypass-approvals-and-sandbox.
"""
import json
import os
import re

from .context import copy_secret, sync_back
from . import search as S
from .errors import RunnerError, looks_rate_limited

MODEL_RE = re.compile(r"^[A-Za-z0-9._:-]{1,64}$")


def compose(system: str, user: str) -> str:
    if system:
        return "[HƯỚNG DẪN HỆ THỐNG]\n" + system + "\n[HẾT HƯỚNG DẪN]\n\n" + user
    return user


def _first_json_object(text: str):
    """Tìm đối tượng JSON đầu tiên trong đầu ra (CLI có thể in thêm vài dòng thông báo)."""
    dec = json.JSONDecoder()
    i = text.find("{")
    while i != -1:
        try:
            obj, _ = dec.raw_decode(text[i:])
            if isinstance(obj, dict):
                return obj
        except ValueError:
            pass
        i = text.find("{", i + 1)
    return None


def _tokens(**kw):
    return {k: int(v or 0) for k, v in kw.items()}


class Adapter:
    name = ""
    capabilities = {"text"}
    token_relpath = ""   # đường dẫn tương đối của tệp token trong auth_dir (và trong HOME tạm)

    def __init__(self, cfg):
        self.cfg = cfg

    # -- vòng đời mỗi yêu cầu -------------------------------------------------
    def prepare(self, ctx):
        raise NotImplementedError

    def command(self, ctx, req):
        """Trả (args, stdin_bytes, env_bổ_sung)."""
        raise NotImplementedError

    def parse(self, ctx, rc, out, err):
        raise NotImplementedError

    def post_check(self, ctx):
        pass

    def finish(self, ctx):
        pass

    def validate(self, req):
        if not MODEL_RE.match(req.get("model", "")):
            raise RunnerError("bad_request", "Tên model không hợp lệ.")
        cap = req.get("capability", "text")
        if cap not in self.capabilities:
            raise RunnerError("unsupported", "Khả năng chưa được hỗ trợ ở runner này.")
        if cap == "web_search":
            req["_search"] = S.validate_search(req)      # chuẩn hóa một lần, các bước sau dùng bản này
            return
        system, user = req.get("system", ""), req.get("user", "")
        if not isinstance(system, str) or not isinstance(user, str) or not user.strip():
            raise RunnerError("bad_request", "Thiếu nội dung.")
        if len(system.encode()) + len(user.encode()) > self.max_prompt_bytes():
            raise RunnerError("bad_request", "Nội dung quá dài cho runner này.")

    def max_prompt_bytes(self):
        return self.cfg.max_body

    def info(self):
        return {"provider": self.name, "capabilities": sorted(self.capabilities)}


# =============================================================================
class ClaudeAdapter(Adapter):
    name = "claude"
    capabilities = {"text", "web_search"}
    token_relpath = ".credentials.json"

    def _cfgdir(self, ctx):
        return os.path.join(ctx.home, ".claude")

    def prepare(self, ctx):
        os.makedirs(self._cfgdir(ctx), mode=0o700)
        if not copy_secret(os.path.join(self.cfg.auth_dir, self.token_relpath), os.path.join(self._cfgdir(ctx), self.token_relpath)):
            raise RunnerError("auth_expired", "Chưa có token Claude trong volume xác thực.")

    def command(self, ctx, req):
        search = req.get("capability", "text") == "web_search"
        # Tìm web mức NHẸ: duy nhất WebSearch (phía hãng thực hiện). TUYỆT ĐỐI không thêm WebFetch: nó tải trang từ máy runner.
        tools = "WebSearch" if search else ""
        args = [self.cfg.cli_bin, "-p", "--model", req["model"], "--output-format", "stream-json", "--verbose",
                "--tools", tools, "--setting-sources", "", "--no-session-persistence", "--disable-slash-commands",
                "--strict-mcp-config", "--mcp-config", '{"mcpServers":{}}']
        if search:
            args += ["--allowedTools", "WebSearch", "--max-turns", "6", "--system-prompt=" + S.SEARCH_SYSTEM]
            return args, S.build_prompt(req["_search"]).encode("utf-8"), {"CLAUDE_CONFIG_DIR": self._cfgdir(ctx)}
        system, user = req.get("system", ""), req["user"]
        if system and len(system.encode()) <= self.cfg.max_prompt_bytes:
            args.append("--system-prompt=" + system)
        else:
            user = compose(system, user)
        return args, user.encode("utf-8"), {"CLAUDE_CONFIG_DIR": self._cfgdir(ctx)}

    def parse(self, ctx, rc, out, err):
        events = []
        for line in out.splitlines():
            line = line.strip()
            if line.startswith("{"):
                try:
                    events.append(json.loads(line))
                except ValueError:
                    pass
        init = next((e for e in events if e.get("type") == "system" and e.get("subtype") == "init"), None)
        search = getattr(ctx, "capability", "text") == "web_search"
        if init is not None:
            extra = [t for t in (init.get("tools") or []) if not (search and t == "WebSearch")]
            if extra or init.get("mcp_servers"):
                raise RunnerError("policy_violation", "Claude để lộ công cụ hoặc máy chủ MCP ngoài danh sách cho phép.")
        quota, limited = {}, False
        for e in events:
            if e.get("type") == "rate_limit_event":
                info = e.get("rate_limit_info") or {}
                for k, v in (info.get("unifiedWindows") or {}).items():
                    quota[k] = {"utilization": v.get("utilization"), "resets_at": v.get("resetsAt")}
                if info.get("status") not in (None, "allowed", "allowed_warning"):
                    limited = True
        res = next((e for e in reversed(events) if e.get("type") == "result"), None)
        if res is None:
            msg = (err or out)[:300]
            raise RunnerError("rate_limited" if looks_rate_limited(msg) else "upstream_error", "Claude không trả kết quả: " + msg)
        text = res.get("result")
        if res.get("is_error") or not isinstance(text, str):
            msg = str(text or err)[:300]
            low = msg.lower()
            if "not logged in" in low or "/login" in low:
                raise RunnerError("auth_expired", msg)
            raise RunnerError("rate_limited" if (limited or looks_rate_limited(msg)) else "upstream_error", msg)
        if limited:
            raise RunnerError("rate_limited", "Hãng báo đã chạm giới hạn.")
        u = res.get("usage") or {}
        models = list((res.get("modelUsage") or {}).keys())
        if search:
            sq = ctx.search
            text_out = ""
            results = S.clean_results(S.extract_array(text), sq["max_results"], sq["include_domains"], sq["exclude_domains"])
        else:
            text_out, results = text, None
        out = {"text": text_out, "model": models[0] if models else req_model(ctx),
                "usage": _tokens(input_tokens=u.get("input_tokens"), output_tokens=u.get("output_tokens"),
                                 cache_read_tokens=u.get("cache_read_input_tokens"),
                                 cache_creation_tokens=u.get("cache_creation_input_tokens")),
                "cost_usd": res.get("total_cost_usd"), "quota": quota, "denied_actions": [],
                "estimated": False}
        if results is not None:
            out["results"] = results
        return out

    def finish(self, ctx):
        sync_back(os.path.join(self._cfgdir(ctx), self.token_relpath), os.path.join(self.cfg.auth_dir, self.token_relpath))


def req_model(ctx):
    return getattr(ctx, "model", "")


# =============================================================================
class GrokAdapter(Adapter):
    name = "grok"
    token_relpath = "auth.json"

    def __init__(self, cfg, deny_tools):
        super().__init__(cfg)
        if not deny_tools:
            raise SystemExit("Grok cần danh sách công cụ bị gỡ (RUNNER_GROK_DENY_FILE) khớp đúng phiên bản CLI đã ghim.")
        for t in deny_tools:
            if not re.match(r"^[A-Za-z0-9_.:-]+$", t):
                raise SystemExit(f"Tên công cụ không hợp lệ trong danh sách gỡ: {t!r}")
        self.deny = list(deny_tools)

    def max_prompt_bytes(self):
        return self.cfg.max_body

    def _ghome(self, ctx):
        return os.path.join(ctx.home, ".grok")

    def prepare(self, ctx):
        os.makedirs(self._ghome(ctx), mode=0o700)
        if not copy_secret(os.path.join(self.cfg.auth_dir, self.token_relpath), os.path.join(self._ghome(ctx), self.token_relpath)):
            raise RunnerError("auth_expired", "Chưa có token Grok trong volume xác thực.")

    def command(self, ctx, req):
        pf = os.path.join(ctx.work, "prompt.txt")
        fd = os.open(pf, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, "w", encoding="utf-8") as f:
            f.write(compose(req.get("system", ""), req["user"]))
        args = [self.cfg.cli_bin, "--prompt-file", pf, "--output-format", "json", "--disallowed-tools", ",".join(self.deny),
                "--no-subagents", "--disable-web-search", "--max-turns", "1", "--cwd", ctx.work, "--model", req["model"]]
        if self.cfg.grok_sandbox:
            args += ["--sandbox", self.cfg.grok_sandbox]
        env = {"GROK_HOME": self._ghome(ctx), "GROK_MEMORY": "0", "GROK_SUBAGENTS": "0", "GROK_WEB_FETCH": "0",
               "GROK_TELEMETRY_ENABLED": "0"}
        return args, None, env

    def parse(self, ctx, rc, out, err):
        obj = _first_json_object(out)
        if obj is None:
            msg = (err or out)[:300]
            raise RunnerError("rate_limited" if looks_rate_limited(msg) else "upstream_error", "Grok trả dữ liệu không đọc được: " + msg)
        if obj.get("type") == "error":
            msg = str(obj.get("message", ""))[:300]
            if "not signed in" in msg.lower():
                raise RunnerError("auth_expired", msg)
            raise RunnerError("rate_limited" if looks_rate_limited(msg) else "upstream_error", msg)
        text = obj.get("text")
        if not isinstance(text, str) or not text.strip():
            raise RunnerError("upstream_error", "Grok không trả nội dung (stopReason=%s)." % obj.get("stopReason"))
        u = obj.get("usage") or {}
        models = list((obj.get("modelUsage") or {}).keys())
        return {"text": text, "model": models[0] if models else "",
                "usage": _tokens(input_tokens=u.get("input_tokens"), output_tokens=u.get("output_tokens"),
                                 cache_read_tokens=u.get("cache_read_input_tokens"),
                                 cache_creation_tokens=u.get("cache_creation_input_tokens"),
                                 reasoning_tokens=u.get("reasoning_tokens")),
                "cost_usd": obj.get("total_cost_usd"), "quota": {}, "denied_actions": [], "estimated": False}

    def post_check(self, ctx):
        """Fail-closed sau mỗi lượt: nếu phiên cho thấy mô hình đã thấy công cụ nào thì NGẮT MẠCH (bản Grok mới có thể thêm công cụ)."""
        base = os.path.join(self._ghome(ctx), "sessions")
        for dirpath, _dirs, files in os.walk(base):
            if "tool_definitions.json" in files:
                try:
                    data = json.load(open(os.path.join(dirpath, "tool_definitions.json"), encoding="utf-8"))
                except ValueError:
                    raise RunnerError("policy_violation", "Không đọc được tool_definitions.json của Grok.")
                tools = data if isinstance(data, list) else data.get("tools", data)
                if tools:
                    raise RunnerError("policy_violation", "Grok vẫn để lộ %d công cụ cho mô hình." % len(tools))

    def finish(self, ctx):
        sync_back(os.path.join(self._ghome(ctx), self.token_relpath), os.path.join(self.cfg.auth_dir, self.token_relpath))

    def info(self):
        d = super().info()
        d["tools_denied"] = len(self.deny)
        return d


# =============================================================================
class AgyAdapter(Adapter):
    name = "agy"
    token_relpath = os.path.join(".gemini", "antigravity-cli", "antigravity-oauth-token")

    def max_prompt_bytes(self):
        return self.cfg.max_prompt_bytes   # prompt đi qua đối số dòng lệnh

    def prepare(self, ctx):
        if not copy_secret(os.path.join(self.cfg.auth_dir, self.token_relpath), os.path.join(ctx.home, self.token_relpath)):
            raise RunnerError("auth_expired", "Chưa có token agy trong volume xác thực.")

    def command(self, ctx, req):
        t = max(20, self.cfg.timeout_s - 10)
        args = [self.cfg.cli_bin, "--print", compose(req.get("system", ""), req["user"]), "--output-format", "json",
                "--print-timeout", f"{t}s", "--model", req["model"]]
        return args, b"", {}

    def parse(self, ctx, rc, out, err):
        obj = _first_json_object(out)
        if obj is None:
            msg = (err or out)[:300]
            raise RunnerError("upstream_error", "agy trả dữ liệu không đọc được: " + msg)
        status = obj.get("status")
        denied = [d.get("action") for d in (obj.get("denied_actions") or []) if isinstance(d, dict)]
        if status != "SUCCESS":
            msg = str(obj.get("error") or out)[:300]
            low = msg.lower()
            if "authentication" in low or "not signed in" in low:
                raise RunnerError("auth_expired", msg)
            raise RunnerError("rate_limited" if looks_rate_limited(msg) else "upstream_error", msg)
        text = obj.get("response") or ""
        if not text.strip():
            if denied:
                raise RunnerError("tool_denied", "Mô hình chỉ cố gọi công cụ bị từ chối: " + ",".join(sorted(set(denied))))
            raise RunnerError("upstream_error", "agy không trả nội dung.")
        u = obj.get("usage") or {}
        return {"text": text, "model": "", "denied_actions": denied,
                "usage": _tokens(input_tokens=u.get("input_tokens"), output_tokens=u.get("output_tokens"),
                                 cache_read_tokens=u.get("cache_read_tokens"), reasoning_tokens=u.get("thinking_tokens")),
                "cost_usd": None, "quota": {}, "estimated": False}

    def finish(self, ctx):
        sync_back(os.path.join(ctx.home, self.token_relpath), os.path.join(self.cfg.auth_dir, self.token_relpath))


# =============================================================================
class CodexAdapter(Adapter):
    """Codex CLI đăng nhập bằng tài khoản ChatGPT. Chỉ khả năng `text`.

    Các cờ dưới đây đã thử THẬT với codex-cli 0.163.0 (docs/KET_QUA_THAM_DO.md, Đợt 4): bỏ bất kỳ cờ nào là mở lại một công cụ.
    """
    name = "codex"
    token_relpath = "auth.json"
    # Mỗi tính năng ở đây từng làm mô hình chạy được lệnh, đọc tệp, tìm web, gọi MCP/ứng dụng/trình duyệt hoặc sinh tác tử con.
    DISABLE = ("shell_tool", "unified_exec", "apps", "plugins", "browser_use", "browser_use_external", "computer_use", "image_generation",
               "view_image", "in_app_browser", "hooks", "skill_search", "goals", "memories", "tool_suggest", "sleep_tool", "multi_agent",
               "multi_agent_v2", "code_mode_host", "code_mode_interrupt", "auth_elicitation", "tool_call_mcp_elicitation",
               "skill_mcp_dependency_install", "collaboration_modes")

    def max_prompt_bytes(self):
        return self.cfg.max_body   # prompt đi qua stdin

    def _chome(self, ctx):
        return os.path.join(ctx.home, ".codex")

    def prepare(self, ctx):
        os.makedirs(self._chome(ctx), mode=0o700)
        if not copy_secret(os.path.join(self.cfg.auth_dir, self.token_relpath), os.path.join(self._chome(ctx), self.token_relpath)):
            raise RunnerError("auth_expired", "Chưa có token Codex trong volume xác thực.")

    def command(self, ctx, req):
        args = [self.cfg.cli_bin, "exec", "--skip-git-repo-check", "--ephemeral", "--ignore-user-config", "--ignore-rules",
                "--sandbox", "read-only", "--json", "-m", req["model"]]
        for f in self.DISABLE:
            args += ["--disable", f]
        args += ["-c", 'web_search="disabled"', "-"]   # include_apply_patch_tool KHÔNG có tác dụng ở bản 0.163.0 (CLI báo bỏ qua); apply_patch bị chặn bởi --sandbox read-only
        return args, compose(req.get("system", ""), req["user"]).encode("utf-8"), {"CODEX_HOME": self._chome(ctx)}

    def parse(self, ctx, rc, out, err):
        events = []
        for line in out.splitlines():
            line = line.strip()
            if line.startswith("{"):
                try:
                    events.append(json.loads(line))
                except ValueError:
                    pass
        # Lớp phụ: nếu luồng sự kiện LỘ một lượt dùng công cụ (lệnh, tìm web, sửa tệp, MCP...) thì ngắt mạch. Không đủ để làm lớp chính.
        for e in events:
            it = e.get("item") or {}
            if e.get("type", "").startswith("item.") and it.get("type") not in (None, "agent_message", "reasoning", "error"):   # "error" chỉ là cảnh báo cấu hình của CLI
                raise RunnerError("policy_violation", "Codex dùng công cụ ngoài danh sách cho phép: " + str(it.get("type"))[:40])
        msgs = [e["item"].get("text") for e in events
                if e.get("type") == "item.completed" and (e.get("item") or {}).get("type") == "agent_message" and isinstance(e["item"].get("text"), str)]
        done = next((e for e in reversed(events) if e.get("type") == "turn.completed"), None)
        if not msgs or done is None:
            errs = " | ".join(str(e.get("message") or (e.get("error") or {}).get("message") or "") for e in events
                              if e.get("type") in ("error", "turn.failed"))
            msg = (errs or err or out)[:300]
            low = msg.lower()
            if "401" in low or "unauthorized" in low or "not logged in" in low or "sign in" in low or "log in" in low:
                raise RunnerError("auth_expired", msg)
            raise RunnerError("rate_limited" if looks_rate_limited(msg) else "upstream_error", "Codex không trả kết quả: " + msg)
        text = msgs[-1]
        if not text.strip():
            raise RunnerError("upstream_error", "Codex không trả nội dung.")
        u = done.get("usage") or {}
        return {"text": text, "model": req_model(ctx),
                "usage": _tokens(input_tokens=u.get("input_tokens"), output_tokens=u.get("output_tokens"),
                                 cache_read_tokens=u.get("cached_input_tokens"), reasoning_tokens=u.get("reasoning_output_tokens")),
                "cost_usd": None, "quota": {}, "denied_actions": [], "estimated": False}

    def finish(self, ctx):
        sync_back(os.path.join(self._chome(ctx), self.token_relpath), os.path.join(self.cfg.auth_dir, self.token_relpath))


def make_adapter(cfg):
    if cfg.provider == "claude":
        return ClaudeAdapter(cfg)
    if cfg.provider == "grok":
        path = cfg.grok_deny_file
        if not path or not os.path.isfile(path):
            raise SystemExit("Thiếu tệp RUNNER_GROK_DENY_FILE.")
        names = [x.strip() for x in open(path, encoding="utf-8").read().replace("\n", ",").split(",") if x.strip()]
        return GrokAdapter(cfg, names)
    if cfg.provider == "agy":
        return AgyAdapter(cfg)
    if cfg.provider == "codex":
        return CodexAdapter(cfg)
    raise SystemExit("Provider không hỗ trợ: " + cfg.provider)
