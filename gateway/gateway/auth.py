import hashlib
import hmac
import re

from .errors import ApiError

KEY_RE = re.compile(r"^gw_[0-9a-f]{40}$")


def hash_key(plain: str, pepper: str) -> str:
    """Giống hệt AppKey::issue của giao diện điều khiển: hash_hmac('sha256', khóa, APP_KEY)."""
    return hmac.new(pepper.encode(), plain.encode(), hashlib.sha256).hexdigest()


def bearer(header: str | None) -> str:
    if not header or not header.startswith("Bearer "):
        raise ApiError(401, "invalid_api_key", "Thiếu hoặc sai khóa API.")
    key = header[7:].strip()
    if not KEY_RE.match(key):
        raise ApiError(401, "invalid_api_key", "Thiếu hoặc sai khóa API.")
    return key
