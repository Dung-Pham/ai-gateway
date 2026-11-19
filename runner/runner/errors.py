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


def looks_rate_limited(text: str) -> bool:
    t = (text or "").lower()
    return any(h in t for h in LIMIT_HINTS)
