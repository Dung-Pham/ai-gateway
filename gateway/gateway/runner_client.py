import json
import re
from urllib.parse import urlparse

import httpx

from .errors import ApiError


class RunnerRef:
    """Thông tin tối thiểu để gọi một runner."""

    def __init__(self, label: str, url: str, secret: str):
        self.label, self.url, self.secret = label, url, secret


def validate_runner_url(url: str, host_regex: str, port_min: int) -> str:
    """Chống SSRF: chỉ http://runner-<nhãn>:<cổng>, không IP, không thông tin đăng nhập, không đường dẫn/query."""
    u = urlparse(url or "")
    ok = (u.scheme == "http" and u.hostname and re.match(host_regex, u.hostname) and u.username is None and u.password is None
          and not u.query and not u.fragment and u.path in ("", "/") and (u.port is None or u.port >= port_min))
    if not ok:
        raise ApiError(502, "bad_runner_url", "Địa chỉ runner không hợp lệ.")
    return f"http://{u.hostname}:{u.port or 80}"


class HttpRunnerClient:
    def __init__(self, timeout_s: int):
        self.timeout_s = timeout_s

    async def info(self, ref: RunnerRef) -> dict | None:
        """GET /v1/info (có xác thực). None nếu runner không trả lời."""
        try:
            async with httpx.AsyncClient(timeout=min(self.timeout_s, 4), trust_env=False) as c:
                r = await c.get(ref.url + "/v1/info", headers={"Authorization": "Bearer " + ref.secret})
            body = r.json() if r.status_code == 200 else None
            return body if isinstance(body, dict) else None
        except (httpx.HTTPError, ValueError):
            return None

    async def run(self, ref: RunnerRef, payload: dict) -> tuple[int, dict]:
        try:
            async with httpx.AsyncClient(timeout=self.timeout_s, trust_env=False) as c:   # trust_env=False: không đi qua proxy
                r = await c.post(ref.url + "/v1/run", json=payload, headers={"Authorization": "Bearer " + ref.secret})
            try:
                body = r.json()
            except ValueError:
                body = {"ok": False, "error_class": "upstream_error", "message": "Runner trả dữ liệu không đọc được."}
            return r.status_code, body
        except httpx.TimeoutException:
            return 504, {"ok": False, "error_class": "timeout", "message": "Runner quá thời gian chờ."}
        except httpx.HTTPError:
            return 503, {"ok": False, "error_class": "upstream_error", "message": "Không kết nối được runner."}
