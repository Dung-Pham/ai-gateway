import hashlib
import os
from dataclasses import dataclass, field

PROVIDERS = ("claude", "grok", "agy")


@dataclass(frozen=True)
class Config:
    provider: str
    cli_bin: str
    auth_dir: str                      # volume chứa token (duy nhất bền vững); mọi thứ khác nằm trong thư mục tạm
    secret: str                        # bí mật gateway -> runner
    cli_sha256: str | None = None      # ghim phiên bản: băm SHA-256 của tệp thực thi (bắt buộc ở production)
    work_root: str = "/tmp/runner"     # nên là tmpfs
    max_concurrency: int = 2
    timeout_s: int = 300
    max_body: int = 1_048_576          # 1 MiB
    max_prompt_bytes: int = 100_000    # đối số lệnh của HĐH có giới hạn (~128 KB)
    max_output_bytes: int = 4_000_000
    host: str = "0.0.0.0"
    port: int = 8800
    grok_deny_file: str | None = None
    grok_sandbox: str = ""             # "strict" cần bubblewrap + không gian tên; trong container đã cứng hóa thì không chạy được (xem docs)
    passthrough_env: tuple[str, ...] = ()   # CHỈ dùng cho test; mặc định rỗng
    allow_unpinned: bool = False       # CHỈ dùng cho test/dev; production phải có cli_sha256
    proxy_env: dict = field(default_factory=dict)

    @staticmethod
    def from_env(env=os.environ) -> "Config":
        def req(k):
            v = env.get(k)
            if not v:
                raise SystemExit(f"Thiếu biến môi trường bắt buộc: {k}")
            return v

        provider = req("RUNNER_PROVIDER")
        if provider not in PROVIDERS:
            raise SystemExit(f"RUNNER_PROVIDER phải là một trong {PROVIDERS}")
        with open(req("RUNNER_SECRET_FILE"), encoding="utf-8") as f:
            secret = f.read().strip()
        if len(secret) < 32:
            raise SystemExit("Bí mật runner quá ngắn (cần >= 32 ký tự)")
        proxy_env = {k: env[k] for k in ("HTTPS_PROXY", "HTTP_PROXY", "NO_PROXY", "https_proxy", "http_proxy", "no_proxy", "SSL_CERT_FILE") if env.get(k)}
        return Config(
            provider=provider,
            cli_bin=req("RUNNER_CLI_BIN"),
            auth_dir=req("RUNNER_AUTH_DIR"),
            secret=secret,
            cli_sha256=(env.get("RUNNER_CLI_SHA256") or None),
            work_root=env.get("RUNNER_WORK_ROOT", "/tmp/runner"),
            max_concurrency=int(env.get("RUNNER_MAX_CONCURRENCY", "2")),
            timeout_s=int(env.get("RUNNER_TIMEOUT_S", "300")),
            grok_deny_file=env.get("RUNNER_GROK_DENY_FILE"),
            grok_sandbox=env.get("RUNNER_GROK_SANDBOX", ""),
            port=int(env.get("RUNNER_PORT", "8800")),
            allow_unpinned=env.get("RUNNER_ALLOW_UNPINNED") == "1",
            proxy_env=proxy_env,
        )


def sha256_file(path: str) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def verify_pin(cfg: Config) -> None:
    """Từ chối khởi động nếu tệp thực thi không đúng bản đã ghim (chống đổi/cập nhật CLI ngoài ý muốn)."""
    if not os.path.isfile(cfg.cli_bin):
        raise SystemExit(f"Không thấy tệp thực thi CLI: {cfg.cli_bin}")
    if cfg.cli_sha256 is None:
        if cfg.allow_unpinned:
            return
        raise SystemExit("Thiếu RUNNER_CLI_SHA256: production phải ghim phiên bản CLI (đặt RUNNER_ALLOW_UNPINNED=1 chỉ khi dev).")
    actual = sha256_file(cfg.cli_bin)
    if actual != cfg.cli_sha256.lower():
        raise SystemExit(f"SHA-256 của CLI không khớp bản đã ghim (thực tế {actual[:16]}…, ghim {cfg.cli_sha256[:16]}…). Từ chối khởi động.")
