"""Gateway (mặt dữ liệu): API tương thích OpenAI, xác thực khóa theo ứng dụng, chính sách, hạn mức, định tuyến tới runner.

Nguyên tắc: không ghi nội dung prompt/phản hồi ra log hay CSDL; chỉ INSERT vào usage_events và cập nhật app_keys.last_used_at.
"""
__version__ = "0.2.0"
