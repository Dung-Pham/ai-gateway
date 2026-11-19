# Cổng AI — Kết quả thăm dò CLI

Cập nhật: 19/11/2026, trên máy thử của bạn (Linux, 8 nhân, RAM 15 GB trong đó khoảng 6,5 GB còn trống lúc đo, Python 3.14.4, Docker 29.1.3). Tệp thô nằm ở `probe-results/2026-11-19/` (thư mục này bị git bỏ qua vì có mã phiên và thông tin tài khoản).

## Tình trạng tổng quát

**Mới chạy được phần không cần đăng nhập.** Chưa có tài khoản thử nào đăng nhập vào thư mục thử `~/.probe/…` (đăng nhập do bạn làm, tôi không xử lý). Nên **chưa có lượt gọi AI nào được thực hiện** và chưa dùng hạn mức của tài khoản nào. Môi trường không có khóa API (đã kiểm tra: `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY`, `XAI_API_KEY`… đều trống) nên CLI không thể âm thầm dùng tài khoản khác.

| Mục | Trạng thái |
|---|---|
| P0 / G0 môi trường, phiên bản | **Đã chạy** |
| P10 / G10 chưa đăng nhập trông thế nào | **Đã chạy** (cả 4 CLI) |
| P9 / G9 tên miền | **Chạy một phần**: chỉ ở trạng thái chưa đăng nhập |
| P1–P8, G1–G8, G11 | **Chưa chạy: bị chặn tích hợp** (cần đăng nhập tài khoản thử) |
| P3 (chạm giới hạn) | **Chưa chạy**: cần đăng nhập và dùng thật |
| P11 (LiteLLM) | **Chưa chạy** (làm sau) |

## P0 / G0: Môi trường

| CLI | Phiên bản |
|---|---|
| claude | 2.1.308 |
| codex | 0.163.0 |
| gemini | 0.64.0 |
| grok | 1.0.70 |

## P10 / G10: Chưa đăng nhập thì CLI báo gì

Chạy với thư mục cấu hình rỗng, đầu vào đóng (`< /dev/null`), `timeout 40`. **Cả bốn thoát có mã lỗi, không CLI nào treo chờ nhập.**

| CLI | Mã thoát | Thời gian | Nơi báo lỗi | Chữ ký nhận dạng |
|---|---|---|---|---|
| Claude | 1 | 1,1 giây | **stdout**, JSON | `is_error: true`, `terminal_reason: "api_error"`, `result: "Not logged in · Please run /login"` |
| Codex | 1 | **17 giây** | stdout (JSONL) và stderr | Sự kiện `type:"error"` với "401 Unauthorized: Missing bearer or basic authentication"; tự thử lại 5 lần (khoảng 13 kết nối) rồi bỏ |
| Gemini | **41** | 1,8 giây | **stderr**, JSON | `error.code: 41`, "Please set an Auth method … GEMINI_API_KEY, GOOGLE_GENAI_USE_VERTEXAI, GOOGLE_GENAI_USE_GCA" |
| Grok | 1 | 0,5 giây | stdout (JSON) và stderr | `type:"error"`, "Not signed in", gợi ý `grok login --device-code` hoặc đặt `XAI_API_KEY` |

Hệ quả cho thiết kế:
1. Gateway phải có **timeout cứng** và **đầu vào đóng**: Codex mất 17 giây mới bỏ cuộc.
2. Nhận dạng `auth_expired` phải theo từng hãng: Claude (JSON stdout), Codex (sự kiện lỗi 401), Gemini (mã thoát 41), Grok (JSON "Not signed in"). Mẫu trên là của trạng thái **chưa từng đăng nhập**; hình dạng lỗi khi **phiên hết hạn** có thể khác, cần thu thập khi gặp thật.
3. Ở Claude, kết quả JSON có sẵn khung `usage`, `total_cost_usd`, `modelUsage`, `session_id`; ở đây toàn số 0 vì chưa gọi được. Chưa thể kết luận G1 cho Claude cho tới khi có lượt gọi thật.

## P9 / G9: Tên miền (chỉ trạng thái chưa đăng nhập)

Dùng bộ ghi tên miền `probes/connect_logger.py` (chỉ ghi `host:port`, không ghi nội dung).

| CLI | Tên miền đã thấy | Ghi chú |
|---|---|---|
| Claude | `api.anthropic.com:443` | 5 kết nối |
| Codex | `api.openai.com:443`, `chatgpt.com:443`, `github.com:443` | Tách riêng từng CLI nên đã gán đúng. Lý do cần `github.com` chưa biết (có thể kiểm tra cập nhật), **chưa kết luận được có bắt buộc không** |
| Gemini | (không có) | Thoát trước khi gọi mạng, nên **chưa biết** tên miền và **chưa biết** có tôn trọng biến proxy không |
| Grok | (không có) | Như trên |

**Chưa dùng được làm danh sách cho phép chính thức**: đây mới là các tên miền thử đăng nhập. Tên miền khi gọi mô hình thật (và khi đăng nhập, làm mới token) chưa thấy hết. Cần chạy lại sau khi đăng nhập.

## Phát hiện quan trọng cho thiết kế: CLI tự ghi trạng thái vào thư mục cấu hình

Dù đã dùng các cờ tránh lưu (`--no-session-persistence` của Claude, `--ephemeral` của Codex), mỗi CLI vẫn tạo tệp trong thư mục cấu hình của nó chỉ sau một lần chạy lỗi:

| CLI | Tệp/thư mục tự tạo (rút gọn) |
|---|---|
| Claude | `.claude.json`, `backups/`, `projects/<thư mục làm việc>/`, `sessions/` |
| Codex | **Nhiều cơ sở dữ liệu SQLite**: `state_5.sqlite` (WAL khoảng 2 MB), `logs_2.sqlite`, `goals_1.sqlite`, `queue_1.sqlite`, **`memories_1.sqlite`**; cùng `shell_snapshots/*.sh`, `skills/`, `installation_id` |
| Gemini | `.gemini/history/`, `.gemini/tmp/`, `.gemini/projects.json` |
| Grok | `sessions/session_search.sqlite`, `logs/unified.jsonl` (khoảng 11 KB), `config.toml`, bản sao tài liệu, `installed-plugins/` |

Ý nghĩa:
- **Chưa biết** các tệp này có chứa nội dung prompt không (lần chạy này chỉ có prompt "ok" và đều lỗi trước khi gọi AI). Cần kiểm tra sau khi đăng nhập bằng một chuỗi dấu hiệu độc nhất rồi tìm trong thư mục cấu hình.
- Codex có `memories_1.sqlite`: nhiều khả năng là bộ nhớ giữa các phiên. Nếu có thật thì là **đường rò dữ liệu giữa các ứng dụng** và phải tắt hoặc xóa. Chưa kiểm chứng.
- Nếu các tệp này có nội dung, mô hình "một thư mục cấu hình bền cho mỗi tài khoản" cần chỉnh: **chỉ phần xác thực nằm trên volume bền**, còn mọi thứ khác chạy trên `tmpfs` hoặc thư mục tạm riêng cho từng yêu cầu rồi xóa. Cách tách này phụ thuộc vị trí tệp xác thực của từng CLI (chưa xác định).

## Việc cần bạn làm để chạy tiếp (đăng nhập tài khoản thử)

Bạn có Claude, Gemini, Grok (không có Codex nên bỏ Codex). Đăng nhập vào **thư mục thử** (không phải tài khoản đang code của bạn). Mỗi lệnh chạy riêng, bạn tự hoàn tất bước xác thực:

```bash
CLAUDE_CONFIG_DIR="$HOME/.probe/claude" claude auth login
```

```bash
GROK_HOME="$HOME/.probe/grok" grok login --device-code
```

```bash
HOME="$HOME/.probe/gemini-home" gemini
```
(Gemini: chọn đăng nhập bằng tài khoản Google trong giao diện rồi thoát; **cần xác nhận** cách đăng nhập trên máy không có trình duyệt.)

Khi xong, tôi chạy tiếp P1 đến P8, G1 đến G8, G11 và P9/G9 lần hai. Ước tính khoảng 30 lượt gọi nhỏ trên mỗi tài khoản (Claude, Gemini, Grok).

Mục chủ động chạm giới hạn (P3) **không** nằm trong đợt này; chỉ làm nếu bạn đồng ý riêng.
