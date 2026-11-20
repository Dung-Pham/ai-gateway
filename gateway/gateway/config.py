import json
import os
from dataclasses import dataclass, field


@dataclass(frozen=True)
class Settings:
    database_url: str
    key_pepper: str                       # CHÍNH XÁC chuỗi APP_KEY của Laravel (kể cả tiền tố "base64:")
    runner_secrets: dict = field(default_factory=dict)   # nhãn tài khoản -> bí mật runner
    admin_token: str | None = None
    runner_timeout_s: int = 330
    timezone: str = "Asia/Ho_Chi_Minh"
    max_body: int = 2_000_000
    default_cooldown_s: int = 1800
    runner_host_regex: str = r"^runner-[a-z0-9]+(-[a-z0-9]+)*$"   # SSRF: chỉ gọi tên máy runner nội bộ
    allow_runner_port_min: int = 1024
    search_model: str = "haiku"           # model mặc định cho /v1/search (việc nhẹ: dùng model nhỏ để dành hạn mức)
    check_models: dict = field(default_factory=lambda: {"claude": "haiku", "grok": "grok-4.7", "agy": "gemini-4-neon-high", "codex": "gpt-6-luna"})   # model rẻ nhất để "Kiểm tra ngay"

    @staticmethod
    def from_env(env=os.environ) -> "Settings":
        def read(k, required=True):
            p = env.get(k)
            if not p:
                if required:
                    raise SystemExit(f"Thiếu biến môi trường bắt buộc: {k}")
                return None
            return open(p, encoding="utf-8").read().strip()

        pepper = read("GATEWAY_PEPPER_FILE")
        if len(pepper) < 20:
            raise SystemExit("GATEWAY_PEPPER_FILE quá ngắn: phải là APP_KEY của giao diện điều khiển.")
        secrets = json.loads(read("GATEWAY_RUNNER_SECRETS_FILE"))
        if not isinstance(secrets, dict) or any(len(str(v)) < 32 for v in secrets.values()):
            raise SystemExit("GATEWAY_RUNNER_SECRETS_FILE: cần JSON {nhãn: bí mật >= 32 ký tự}.")
        url = env.get("DATABASE_URL") or read("DATABASE_URL_FILE", False)
        if not url:
            raise SystemExit("Thiếu DATABASE_URL (hoặc DATABASE_URL_FILE).")
        return Settings(database_url=url, key_pepper=pepper, runner_secrets=secrets, admin_token=read("GATEWAY_ADMIN_TOKEN_FILE", False),
                        timezone=env.get("APP_TIMEZONE", "Asia/Ho_Chi_Minh"), search_model=env.get("GATEWAY_SEARCH_MODEL", "haiku"),
                        check_models=_check_models(env.get("GATEWAY_CHECK_MODELS")))


def _check_models(raw):
    """GATEWAY_CHECK_MODELS="claude=haiku,grok=...,agy=..." ghi đè mặc định."""
    out = {"claude": "haiku", "grok": "grok-4.7", "agy": "gemini-4-neon-high", "codex": "gpt-6-luna"}
    for part in (raw or "").split(","):
        if "=" in part:
            k, v = part.split("=", 1)
            if k.strip() in out and v.strip():
                out[k.strip()] = v.strip()
    return out
