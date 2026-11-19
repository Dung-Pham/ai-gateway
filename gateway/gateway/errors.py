class ApiError(Exception):
    """Lỗi trả về cho ứng dụng gọi, theo định dạng OpenAI: {"error": {"message","type","code"}}."""

    def __init__(self, status: int, code: str, message: str, headers: dict | None = None):
        super().__init__(message)
        self.status, self.code, self.message, self.headers = status, code, message, headers or {}

    def body(self):
        return {"error": {"message": self.message, "type": "gateway_error", "code": self.code}}
