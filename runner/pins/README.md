# Ghim phiên bản CLI

Mỗi CLI chỉ được chạy ở **đúng bản đã kiểm chứng**. Runner băm SHA-256 tệp thực thi khi khởi động và từ chối chạy nếu lệch.

| CLI | Bản | Vì sao phải ghim |
|---|---|---|
| claude | 2.1.308 | Cờ `--strict-mcp-config` là chốt chặn connector claude.ai; định dạng `stream-json` chứa `rate_limit_event` |
| grok | 1.0.70 | Danh sách công cụ bị gỡ (`grok-1.0.70.deny.txt`) khớp đúng 24 công cụ của bản này |
| agy | 1.5.12 | Tự cập nhật qua dịch vụ của Google; phải tắt/chặn cập nhật và chỉ chạy đúng bản đã thử |

## Khi muốn nâng phiên bản (bắt buộc làm đủ)
1. Cài bản mới vào thư mục riêng, **không** thay bản đang chạy.
2. Grok: lấy lại danh sách công cụ (chạy một lượt thử không gỡ công cụ nào rồi đọc `tool_definitions.json` của phiên), cập nhật tệp `.deny.txt`, rồi chạy lượt thử với danh sách mới và **khẳng định mô hình thấy 0 công cụ**. Runner cũng tự kiểm tra điều này sau mỗi lượt và **ngắt mạch** nếu thấy công cụ.
3. Claude: chạy lượt thử và khẳng định sự kiện `system:init` có `tools: []` và `mcp_servers: []`.
4. Chạy bộ test bảo mật (tệp mồi ngoài thư mục làm việc, lệnh `id`, ghi tệp) và lưu log làm bằng chứng.
5. Cập nhật `versions.env` (phiên bản và SHA-256) trong cùng một PR.
