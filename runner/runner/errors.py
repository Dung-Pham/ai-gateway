"""Lỗi chuẩn của runner. `error_class` là phần gateway dùng để quyết định thử lại hay đổi tài khoản."""

# Các mã lỗi hợp lệ (gateway phụ thuộc vào danh sách này)
ERROR_CLASSES = (
    "bad_request",       # yêu cầu sai (model sai định dạng, quá dài…)
    "auth_expired",      # tài khoản chưa đăng nhập / hết phiên
    "rate_limited",      # chạm giới hạn của hãng
    "timeout",           # CLI quá thời gian
    "upstream_error",    # CLI lỗi khác
    "tool_denied",       # mô hình chỉ cố gọi công cụ (bị từ chối), không có câu trả lời
    "policy_violation",  # CLI để lộ công cụ/connector không mong muốn: NGẮT MẠCH
    "busy",              # hết chỗ chạy song song
    "tripped",           # runner đã ngắt mạch, từ chối mọi yêu cầu cho tới khi khởi động lại
    "unsupported",       # khả năng chưa hỗ trợ
)


class RunnerError(Exception):
    def __init__(self, error_class: str, message: str = ""):
        assert error_class in ERROR_CLASSES, error_class
        super().__init__(message)
        self.error_class = error_class
        self.message = message[:300]


# Dấu hiệu chạm giới hạn (lấy từ cầu nối cũ và thông báo thật đã thu thập)
LIMIT_HINTS = ("rate limit", "usage limit", "session limit", "hit your", "overloaded", "429", "quota",
               "too many requests", "limit reached", "resets ")


# Dấu hiệu hết phiên đăng nhập. Thông báo THẬT của Claude CLI 2.1.308 khi token OAuth hết hạn mà không làm mới được:
# "OAuth token expired and refresh failed (re-login required)" (không chứa "/login" nên trước đây bị xếp nhầm là lỗi chung, hiện 502).
# Thông điệp THẬT đã thấy ở Claude CLI 2.1.308 khi proxy chặn việc làm mới: "Failed to authenticate: OAuth token revoked. Please log in again or contact your administrator."
AUTH_HINTS = ("not logged in", "/login", "re-login", "login required", "oauth token expired", "token has expired", "token revoked",
              "log in again", "please log in", "failed to authenticate", "authentication_error", "401 unauthorized", "not signed in")


def looks_auth_expired(text: str) -> bool:
    t = (text or "").lower()
    return any(h in t for h in AUTH_HINTS)


def looks_rate_limited(text: str) -> bool:
    t = (text or "").lower()
    return any(h in t for h in LIMIT_HINTS)
