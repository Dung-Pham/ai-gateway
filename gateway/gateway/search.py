"""Kiểm tra LẠI kết quả tìm web do runner trả về (phòng thủ nhiều lớp: runner có thể bị chiếm, nên gateway không tin tuyệt đối)."""
import ipaddress
import re
from urllib.parse import urlsplit

MAX_RESULTS = 8
DOMAIN_RE = re.compile(r"^[A-Za-z0-9.-]{1,100}$")
CTRL_RE = re.compile(r"[\x00-\x08\x0b-\x1f\x7f​-‏‪-‮⁦-⁩]")
WS_RE = re.compile(r"\s+")
INTERNAL_SUFFIX = (".local", ".internal", ".lan", ".home", ".corp", ".intranet", ".localdomain")


def domains(v) -> list[str]:
    """Lọc danh sách tên miền như cầu nối cũ: bỏ phần tử sai định dạng, tối đa 20."""
    return [x.lower().strip(".") for x in (v or []) if isinstance(x, str) and DOMAIN_RE.match(x)][:20] if isinstance(v, list) else []


def url_ok(url) -> str | None:
    if not isinstance(url, str) or len(url) > 2048 or CTRL_RE.search(url) or " " in url.strip():
        return None
    try:
        u = urlsplit(url.strip())
        host = (u.hostname or "").lower().rstrip(".")
        port = u.port
    except ValueError:
        return None
    if u.scheme not in ("http", "https") or not host or u.username or u.password or port not in (None, 80, 443):
        return None
    try:
        ipaddress.ip_address(host)
        return None
    except ValueError:
        pass
    if host == "localhost" or "." not in host or host.endswith(INTERNAL_SUFFIX):
        return None
    return u.geturl()


def _in(host, doms):
    return any(host == d or host.endswith("." + d) for d in doms)


def reclean(rows, n: int, include=(), exclude=()) -> list[dict]:
    out, seen = [], set()
    for r in rows if isinstance(rows, list) else []:
        if not isinstance(r, dict):
            continue
        url = url_ok(r.get("url"))
        if not url:
            continue
        sp = urlsplit(url)
        host = sp.hostname.lower().rstrip(".")
        if (include and not _in(host, list(include))) or (exclude and _in(host, list(exclude))):
            continue
        key = (host, sp.path.rstrip("/"), sp.query)
        if key in seen:
            continue
        seen.add(key)
        title = WS_RE.sub(" ", CTRL_RE.sub(" ", str(r.get("title") or url))).strip()[:200] or url
        content = WS_RE.sub(" ", CTRL_RE.sub(" ", str(r.get("content") or ""))).strip()[:1200]
        out.append({"title": title, "url": url[:2048], "content": content})
        if len(out) >= min(n, MAX_RESULTS):
            break
    return out
