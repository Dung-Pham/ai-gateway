# Cổng AI — Kết quả thăm dò CLI

Cập nhật: 19/11/2026, trên máy thử của bạn (Linux, 8 nhân, RAM 15 GB trong đó khoảng 6,5 GB còn trống lúc đo, Python 3.14.4, Docker 29.1.3). Tệp thô nằm ở `probe-results/2026-11-19/` (thư mục này bị git bỏ qua vì có mã phiên và thông tin tài khoản).

## Tình trạng tổng quát

**Mới chạy được phần không cần đăng nhập.** Chưa có tài khoản thử nào đăng nhập vào thư mục thử `~/.probe/…` (đăng nhập do bạn làm, tôi không xử lý). Nên **chưa có lượt gọi AI nào được thực hiện** và chưa dùng hạn mức của tài khoản nào. Môi trường không có khóa API (đã kiểm tra: `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY`, `XAI_API_KEY`… đều trống) nên CLI không thể âm thầm dùng tài khoản khác.

| Mục | Trạng thái |
|---|---|
| P0 / G0 môi trường, phiên bản | **Đã chạy** |
| P10 / G10 chưa đăng nhập trông thế nào | **Đã chạy** (cả 4 CLI) |
| P9 / G9 tên miền | **Chạy một phần**: chỉ ở trạng thái chưa đăng nhập |
| P1–P8, G1–G8, G11 | **Chưa chạy: bị chặn tích hợp** (cần đăng nhập tài khoản thử). Gemini đã thử đăng nhập 3 lần, **chưa thành công** (xem mục "Gemini: đăng nhập" bên dưới); Claude và Grok chưa thử |
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


## Gemini: đăng nhập kiểu không có trình duyệt (19/11/2026, bản 0.64.0)

Mục tiêu: đăng nhập tài khoản Google (gói đăng ký, không dùng khóa API) vào thư mục thử bằng `NO_BROWSER=true`. **Kết quả: chưa thành công sau 3 lần thử.** Phần dưới chỉ ghi điều đã quan sát và điều đọc được trong mã của CLI; **nguyên nhân gốc chưa xác định**.

Đã quan sát (cả 3 lần giống nhau):
- Với `NO_BROWSER=true`, CLI in đường dẫn đăng nhập Google rất dài và chờ mã. Đường dẫn dễ bị **cụt khi sao chép từ Terminal** (mất `&response_type=code`), gây lỗi `400 invalid_request`.
- Sau khi dán mã đúng, `google_accounts.json` được ghi (bước Google có vẻ thành công), nhưng **không có tệp token nào được tạo**: không có `oauth_creds.json`, không có `gemini-credentials.json`.
- Cùng thời điểm đó `settings.json` bị ghi `security.auth.selectedType = "gemini-api-key"`, nên CLI khởi động lại và **đòi khóa API**, dù ta chọn đăng nhập Google.
- Gọi không tương tác (`gemini -p …`) sau đó thoát mã 41: "Manual authorization is required but the current session is non-interactive". Thử cả có và không có `GEMINI_FORCE_FILE_STORAGE`.
- Mỗi tiến trình sinh mã PKCE riêng, nên **mã lấy từ đường dẫn của tab này không dùng được ở tab khác**. Từng có tới 8 tiến trình `gemini` chạy chồng nhau, có thể ghi đè cấu hình của nhau (đã dọn trước lần 3).

Đọc từ mã nguồn của CLI (bundle 0.64.0, chưa kiểm chứng bằng chạy):
- Token đăng nhập Google lưu qua `HybridTokenStorage`, dịch vụ `gemini-cli-oauth`, khóa cố định **`main-account`**. Ở chế độ keychain, đó là **một danh tính duy nhất cho cả người dùng hệ điều hành**, đổi `HOME` không tách được. Không dùng được cho nhiều tài khoản.
- `GEMINI_FORCE_FILE_STORAGE=true` ép dùng tệp `~/.gemini/gemini-credentials.json`. Tệp này được "mã hóa" bằng khóa suy ra từ **tên máy và tên người dùng** (không phải bí mật thật): coi như tệp token thường, bảo vệ bằng quyền `0600` và volume riêng.
- `GEMINI_CLI_HOME` đổi thư mục gốc của CLI, một lựa chọn thay cho đổi `HOME`.

Rủi ro cần kiểm tra: lần thử đầu (chưa bật chế độ tệp) có thể đã ghi vào keychain của hệ điều hành trên máy bạn, dùng chung với Gemini CLI thật của bạn (nếu bạn dùng). Tôi không xem keychain (sẽ lộ token). Bạn nên kiểm tra Gemini CLI thật còn đúng tài khoản không.

Hệ quả: **Gemini bị chặn tích hợp** trong đợt này. Đường tiếp theo (chưa làm, cần bạn chọn): (a) thử lại trên bản mới hơn (0.69.0 có sẵn) cài vào thư mục riêng, không đụng bản đang dùng; (b) dùng khóa API Gemini (tính tiền theo lượt, không phải gói đăng ký); (c) tạm bỏ Gemini khỏi phạm vi, làm Claude và Grok trước.

**Cập nhật (cùng ngày): bản 0.69.0 cho kết quả y hệt.** Cài riêng vào `~/.probe/gemini-0.69` (bản toàn máy giữ nguyên 0.64.0). Lần thử thứ 4: `google_accounts.json` ghi lúc 17:52:28, `settings.json` bị đổi sang `gemini-api-key` sau 20 mili giây, vẫn không có `gemini-credentials.json`. Kết luận: **lỗi không do phiên bản 0.64.0**. Chưa xác định nguyên nhân. Còn một giả thuyết chưa thử: chế độ `NO_BROWSER` (dán mã) bị hỏng ở cả hai bản, trong khi luồng mặc định (tự mở trình duyệt, nhận mã qua cổng cục bộ) có thể lưu được token. Lần thử mặc định đầu tiên thất bại vì chạy trong Terminal nhúng của ứng dụng ("Authentication consent could not be obtained … run in an interactive terminal"), chưa thử trong Terminal hệ thống.

## Gemini: NGUYÊN NHÂN THẬT đã xác định (19/11/2026)

Ảnh chụp màn hình Terminal hệ thống của bạn (đăng nhập bằng trình duyệt, không `NO_BROWSER`) cho thấy chuỗi sự kiện rõ ràng:

1. `Authentication succeeded` (bước Google thành công).
2. Ngay sau đó CLI báo `Failed to sign in. Message: This client is no longer supported for Gemini Code Assist for individuals. To continue using Gemini, please migrate to the Antigravity suite of products`.
3. Vì từ chối, CLI **không lưu token** và rơi về màn nhập khóa API.

**Kết luận: Gemini CLI (cả 0.64.0 và 0.69.0) không còn đăng nhập được bằng tài khoản Google cá nhân; đây là thông báo của chính CLI, không phải lỗi cấu hình của ta.** Các giả thuyết trước đó (cụt đường dẫn, chế độ `NO_BROWSER`, keychain, tiến trình chạy chồng, `selectedType`) **không phải nguyên nhân**: chúng chỉ là các triệu chứng phụ hoặc lỗi riêng đã gặp trên đường đi (cụt đường dẫn có thật nhưng chỉ gây lỗi 400 một lần). Tôi chưa tự kiểm chứng chính sách của Google ngoài thông báo này.

Phát hiện tương ứng trên máy bạn: đã có sẵn **Antigravity CLI (`agy`)** tại `~/.local/bin/agy` (báo phiên bản 1.5.12) và thư mục `~/.gemini/antigravity*`. Đây là công cụ được thông báo trên để thay thế.

`agy --help` (chỉ đọc, chưa đăng nhập) cho thấy chế độ không tương tác `--print`/`-p`, `--output-format text|json|stream-json`, `--input-format stream-json`, `--model`, `--mode accept-edits|auto|plan`, `--sandbox`, `--print-timeout`, `--settings` (JSON chỉ dùng cho phiên, không lưu), `--project`, `--conversation`, `acp` (máy chủ ACP qua stdio), `--dangerously-skip-permissions` (KHÔNG dùng).

Hệ quả thiết kế:
- Hãng "Gemini" trong thiết kế nên đổi thành **"Google (Antigravity, `agy`)"**. Toàn bộ giả định G1–G7 phải kiểm lại cho `agy`.
- `agy` là tệp thực thi khoảng 280 MB, tự cập nhật qua một dịch vụ của Google (`releaseBaseUrl`): trong runner phải **tắt tự cập nhật và ghim phiên bản**, cần thêm tên miền của dịch vụ cập nhật vào phân tích tên miền.
- Chưa biết `agy` đăng nhập thế nào trên máy không có trình duyệt, token lưu ở đâu, có dùng được nhiều tài khoản trên một máy không. Cần thăm dò (xem mục Antigravity trong THAM_DO_CLI).
- **Không dùng bản `agy` và thư mục `~/.gemini` thật của bạn cho thăm dò** (đó là đăng nhập và hội thoại cá nhân của bạn). Thăm dò dùng `HOME` riêng như các CLI khác.
