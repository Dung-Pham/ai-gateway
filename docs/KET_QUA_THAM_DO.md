# Cổng AI — Kết quả thăm dò CLI

Cập nhật: 19/11/2026, trên máy thử của bạn (Linux, 8 nhân, RAM 15 GB trong đó khoảng 6,5 GB còn trống lúc đo, Python 3.14.4, Docker 29.1.3). Tệp thô nằm ở `probe-results/2026-11-19/` (thư mục này bị git bỏ qua vì có mã phiên và thông tin tài khoản).

## Tình trạng tổng quát

**Đã chạy phần không cần đăng nhập, và phần có đăng nhập cho Claude và Grok** (xem mục "Đợt 2" ở cuối). Gemini CLI không đăng nhập được; **Antigravity (`agy`) đã thăm dò, dùng được trong container** (xem mục "Đợt 3"). Chưa có tài khoản thử nào đăng nhập vào thư mục thử `~/.probe/…` (đăng nhập do bạn làm, tôi không xử lý). Nên **chưa có lượt gọi AI nào được thực hiện** và chưa dùng hạn mức của tài khoản nào. Môi trường không có khóa API (đã kiểm tra: `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY`, `XAI_API_KEY`… đều trống) nên CLI không thể âm thầm dùng tài khoản khác.

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


---

# Đợt 2: Claude và Grok đã đăng nhập (19/11/2026)

Tài khoản: **Claude trong thư mục thử là cùng một tài khoản claude.ai với đăng nhập mặc định của bạn** (đã kiểm tra, chỉ so sánh trùng/khác); bạn đã cho phép dùng. Các số phần trăm hạn mức dưới đây là của tài khoản dùng chung với việc code. Grok đăng nhập qua grok.com, model mặc định `grok-4.7`. Tổng cộng khoảng 20 lượt gọi nhỏ.

## Tóm tắt các giả định

| Giả định | Claude | Grok |
|---|---|---|
| **G1** JSON có số token | **Đạt**: `usage` (vào, ra, cache), `total_cost_usd`, `modelUsage` | **Đạt**: `usage` (vào, ra, cache, suy luận), `total_cost_usd`; thêm `grok usage <phiên>` |
| **G2** đọc % hạn mức bằng máy | **Đạt**: sự kiện `rate_limit_event` trong `stream-json` có `unifiedWindows.five_hour.utilization` và `seven_day.utilization` kèm `resetsAt` | **Chưa thấy**: luồng sự kiện không có thông tin hạn mức; chưa thử lệnh tương tác |
| **G4** ảnh vào không cần công cụ đọc tệp | **Đạt**: ảnh base64 trong đầu vào `stream-json`, trả lời đúng "Đỏ" | Chưa chạy (`--prompt-json` chưa thử) |
| **G5** sinh ảnh qua CLI | **Không**: nói thẳng không có công cụ tạo ảnh, chỉ đưa mã SVG dạng chữ | Không (hết lượt trước khi trả về ảnh; chưa kết luận chắc) |
| **G6** song song cùng thư mục cấu hình | **Đạt (ngắn)**: 3 lượt song song đều thành công, đăng nhập còn nguyên. Chưa chứng minh an toàn dài hạn | Chưa chạy |
| **G7** quyền thật bị khóa | **Có điều kiện**: xem "Phát hiện bảo mật" | **Có điều kiện**: xem "Phát hiện bảo mật" |
| **G11** nội dung lưu ra đĩa | **Không lưu** khi dùng `--no-session-persistence` (đã tìm chuỗi dấu hiệu độc nhất trong cả thư mục cấu hình) | **CÓ lưu** nội dung hội thoại (`chat_history.jsonl`, `updates.jsonl`, `prompt_history.jsonl`), không có cờ tắt |
| **P8** tài nguyên một lượt nhỏ | khoảng 5,6 giây, RAM đỉnh khoảng 267 MB | khoảng 6,5 giây, RAM đỉnh khoảng 142 MB |

Ghi chú về chi phí nền: một lượt "trả lời một từ" của Grok tốn khoảng 14.000 token (phần lớn là lời nhắc hệ thống và định nghĩa công cụ); của Claude khoảng 3.800 token (phần lớn tạo cache). Đơn vị quy đổi trong gateway phải tính cả phần nền này.

## PHÁT HIỆN BẢO MẬT (ảnh hưởng thiết kế)

### 1. Claude: connector của tài khoản claude.ai vẫn được nạp dù tắt công cụ
- Với `--tools "" --setting-sources ""`, sự kiện khởi tạo vẫn liệt kê **8 công cụ `mcp__claude_ai_Claude_Docs__*`** và máy chủ MCP "claude.ai Claude Docs" ở trạng thái đã kết nối (nạp bất đồng bộ nên có lần init báo 0 công cụ nhưng mô hình vẫn thấy).
- Khi bẫy bằng lời nhắc yêu cầu gọi công cụ chỉ đọc `guide`, mô hình **thật sự phát lệnh gọi** công cụ. Chỉ có hệ thống xin quyền chặn lại ("chưa được cấp quyền"). **Rào chắn duy nhất là xin quyền**, không phải việc tắt công cụ. Nếu có cấu hình cho phép (cờ `--allowedTools`, quy tắc cho phép trong cài đặt, chế độ bỏ qua quyền), lời nhắc độc hại có thể xóa hoặc xuất tài liệu trên tài khoản thật.
- **Cách chặn đã kiểm chứng:** thêm `--strict-mcp-config --mcp-config '{"mcpServers":{}}'`: 0 công cụ, không có MCP, mô hình trả lời "không có công cụ". Lệnh cần dùng: `claude -p --tools "" --setting-sources "" --strict-mcp-config --mcp-config '{"mcpServers":{}}' --no-session-persistence`.
- Khuyến nghị bổ sung: tài khoản Claude của gateway nên **không gắn connector nào** (làm ở phía tài khoản), và tuyệt đối không dùng `--dangerously-skip-permissions` hay `--allowedTools`.
- **Cầu nối `scripts/claude_bridge.py` hiện có của SEO Studio chưa có `--strict-mcp-config`**, nên có thể dính đúng lỗ hổng này với bất kỳ tài khoản nào có connector. Chưa sửa vì nằm ngoài repo này; cần bạn quyết.

### 2. Grok: `--tools ""` và `--sandbox` không đủ để tắt công cụ
- Mô hình luôn thấy **24 công cụ** (kể cả `read_file`, `run_terminal_command`, `search_replace`) ở mọi chế độ (`--tools ""`, `--sandbox read-only`, `--sandbox strict`, mặc định); tệp định nghĩa công cụ giống hệt nhau.
- **`read_file` đã đọc được tệp mồi nằm NGOÀI thư mục làm việc** ở chế độ mặc định, `--tools ""` và `--sandbox read-only`, và trả nội dung cho mô hình. Chỉ `--sandbox strict` chặn ("Permission denied").
- `run_terminal_command` bị hủy ở mọi chế độ vì chế độ không tương tác không có ai duyệt, không phải do cờ.
- **Đính chính:** ở bản tóm tắt đầu tiên của tôi, mục "đọc được tệp mồi" ra KHÔNG cho ba chế độ. Đó là **âm tính giả** do tôi chỉ kiểm tra đầu ra cuối mà mô hình đã dừng sớm; nhật ký phiên cho thấy việc đọc đã xảy ra.
- **Cách chặn đã kiểm chứng:** `--disallowed-tools` với **đủ 24 tên công cụ** (danh sách lưu ở `probe-results/…/grok-disallow-list.txt`): mô hình thấy 0 công cụ, gọi 0 lần, trả lời đúng "KHONG_CO_CONG_CU", kể cả khi kết hợp `--sandbox strict`. Gỡ thiếu thì lỗi (`search_replace` đòi có `read_file`).
- **Mong manh:** danh sách phải liệt kê đủ tên, mà bản Grok mới có thể thêm công cụ. Cần **test tự động sau mỗi lần nâng phiên bản**: chạy lượt thử và khẳng định số công cụ mô hình thấy bằng 0.

### 3. Grok lưu nội dung hội thoại ra đĩa, không có cờ tắt
`chat_history.jsonl`, `updates.jsonl` và `prompt_history.jsonl` chứa nguyên câu hỏi, tổ chức theo thư mục làm việc: `sessions/<thư mục làm việc mã hóa>/<mã phiên>/`. Hệ quả: mỗi yêu cầu phải có **thư mục làm việc riêng** và **xóa đúng thư mục phiên đó** ngay sau khi xong, và quét dọn định kỳ. Chưa kiểm tra `session_search.sqlite` và `logs/unified.jsonl` có chứa nội dung không.

### 4. Đầu ra mô hình có thể chứa "giả lệnh gọi công cụ"
Với công cụ tắt, một lượt Claude vẫn in ra văn bản dạng `<invoke name="Read">…` (không phải lời gọi thật). Gateway và ứng dụng phía sau phải coi mọi đầu ra là **văn bản thuần**, không bao giờ diễn giải thành lệnh.

## Tên miền khi đã đăng nhập (một lượt gọi nhỏ)

| CLI | Tên miền | Ghi chú |
|---|---|---|
| Claude | `api.anthropic.com:443` (bắt buộc) | |
| Claude | `http-intake.logs.us5.datadoghq.com:443` | Gửi nhật ký/đo đạc; nên chặn nếu CLI vẫn chạy (chưa thử) |
| Claude | `mcp-proxy.anthropic.com:443` | Chỉ cho connector; với `--strict-mcp-config` lẽ ra không cần (chưa chạy lại để xác nhận) |
| Grok | `cli-chat-proxy.grok.com:443` (bắt buộc) | Chỉ tên miền này thấy được; Grok **có** tôn trọng biến proxy |

Tên miền đăng nhập và làm mới token chưa thu được vì đã đăng nhập từ trước. Danh sách này **chưa đủ làm danh sách cho phép chính thức**.

## Phát hiện cho thiết kế đo lường
Với Claude, mỗi lượt có thể trả phần trăm mức dùng thật của cả hai cửa sổ ngay trong luồng `stream-json`, nên lớp đo L2 (đọc % thật) **khả thi cho Claude** mà không cần lượt gọi riêng. Với Grok chỉ có token và chi phí (L1). Con số của Claude nằm trong một sự kiện không có tài liệu chính thức; cần test hợp đồng để phát hiện khi CLI đổi định dạng.

## Còn chưa chạy
Grok: G4 (ảnh vào), G6 (song song), G10 chỉ chạy lúc chưa đăng nhập; Claude và Grok: P3 (chạm giới hạn, cần bạn đồng ý riêng); `agy` (AG0–AG6, chưa đăng nhập); Codex (bạn không có tài khoản); P11 (LiteLLM).


## Đã áp dụng: vá cầu nối Claude trên dịch vụ đang chạy (19/11/2026, 18:05)

- Repo addp-autoseo: nhánh `fix/m09/ag-s27/dev2/bridge-khoa-connector`, commit `1bc868a` (chưa push, chưa PR): thêm `--strict-mcp-config --mcp-config '{"mcpServers":{}}'` vào `run_claude` và `run_search`, kèm `tests/Unit/ClaudeBridgeHardeningTest.php`.
- Triển khai: chép tệp đã commit (sha `ba504a3b`) vào `~/.local/share/claude-bridge/claude_bridge.py` rồi `systemctl --user restart claude-bridge.service`. Trước đó không có yêu cầu nào đang chạy (0 tiến trình con, 0 kết nối). Bản cũ (sha `ce5953ad`) được sao lưu cạnh đó: `claude_bridge.py.bak-20261119-pre-strict-mcp`.
- Kiểm chứng sau triển khai, qua HTTP thật: không khóa trả 401; có khóa liệt kê `opus, sonnet, haiku`; câu bẫy gọi công cụ Claude Docs trả `KHONGCO` thay vì để mô hình phát lệnh gọi; `/v1/search` hoạt động.
- Về `/v1/search` trả 0 kết quả ở một lượt: so sánh cơ chế trên 3 lượt mỗi bên cho thấy mô hình dùng WebSearch và WebFetch giống hệt nhau, số công cụ lúc khởi tạo giảm từ 10 xuống 2 (bỏ 8 công cụ Docs, giữ đúng 2 công cụ web), số kết quả 1/1/1 (cũ) và 1/1/2 (mới). Trong tổng thể các lượt đo, bản mới có hai lượt 0 kết quả còn bản cũ không có lượt nào; mẫu quá nhỏ để kết luận có liên quan hay không, và không có cơ chế nào giải thích được. **Cần theo dõi tỉ lệ 0 kết quả của `/v1/search` trong vài ngày**; nếu tăng rõ rệt thì quay lại bản cũ.
- Đường lui: `cp ~/.local/share/claude-bridge/claude_bridge.py.bak-20261119-pre-strict-mcp ~/.local/share/claude-bridge/claude_bridge.py && systemctl --user restart claude-bridge.service`.


---

# Đợt 3: Antigravity `agy` (thay Gemini CLI), 19/11/2026

Phiên bản `agy` 1.5.12, gói của tài khoản: Google AI Pro. Đây là tài khoản **cá nhân của bạn** (xem ghi chú đầu tiên về keychain). Tổng cộng khoảng 12 lượt gọi nhỏ.

## 1. Thông tin đăng nhập là TOÀN CỤC trên máy phát triển, không tách theo `HOME`
- Trong thư mục `HOME` trống (4 thư mục khác nhau), `agy` vẫn xác thực thành công, kể cả khi không còn tiến trình `agy` nào chạy. Theo dõi lời gọi hệ thống cho thấy nó **kết nối thẳng `/run/user/<uid>/bus`** (D-Bus của phiên đồ họa, tới keychain), và cứng đường dẫn này nên lần thử "gỡ biến D-Bus" của tôi không có tác dụng thật.
- Hệ quả: **đổi `HOME` không tách được tài khoản `agy` trên máy có keychain**; các lượt gọi `agy` trên máy này (kể cả ở tab "thư mục thử") đã dùng chính tài khoản Google AI Pro trong keychain của bạn. Tôi không biết lần "đăng nhập" ở tab đầu có thật là đăng nhập mới hay `agy` tự nhận đăng nhập có sẵn (màn chào hiện ngay tài khoản).
- Không có tệp nào trong thư mục thật `~/.config/antigravity` hay `~/.gemini` bị sửa trong thời gian thăm dò (kiểm tra bằng giờ sửa).

## 2. Đăng nhập không có trình duyệt, trong container không keychain
- Trong container sạch (không D-Bus, `HOME` tạm), `agy --print` in `Authentication required. Please visit the URL to log in:` kèm đường dẫn Google (chuyển hướng về `https://antigravity.google/oauth-callback`), rồi **chờ mã dán vào trong 60 giây**, sau đó thoát mã 1 với JSON `{"status":"ERROR","error":"authentication failed or timed out","finished_reason":"error"}`. 60 giây là quá ngắn cho việc đăng nhập thủ công.
- Chạy `agy` ở chế độ tương tác (TUI) trong container: màn "Select login method: Google OAuth / Use a Google Cloud project", không thấy giới hạn thời gian; đã đăng nhập thành công bằng cách dán mã.
- **Token lưu ở một tệp thường:** `$HOME/.gemini/antigravity-cli/antigravity-oauth-token` (590 byte, quyền `0600`), JSON với ba khóa: `auth_method`, `id_token_email`, `token`. **Không mã hóa** (JSON thuần): coi như bí mật, bảo vệ bằng quyền tệp và volume riêng.
- Đường dẫn đăng nhập yêu cầu các phạm vi quyền rộng (`cloud-platform`, `userinfo`, `aicode`, `cclog`, `experimentsandconfigs`): nên dùng **tài khoản Google riêng cho gateway**.

## 3. Token sống qua container mới và chạy được khi cứng hóa
Container mới, cùng volume token, với `--read-only`, `--cap-drop ALL`, `--security-opt no-new-privileges`, `--pids-limit 256`, `--memory 1g`, `--cpus 2`, `/tmp` là tmpfs: `status: SUCCESS`, 32 giây tổng. **Mô hình "một volume xác thực riêng cho mỗi tài khoản" khả thi cho `agy`.**

## 4. Mô hình lưu trữ đề xuất cho runner `agy` (đã thử và chạy)
`agy` ghi nội dung phiên (`brain/<hội thoại>/implementation_plan.md`, `conversations`, `conversation_summaries.db`, nhật ký `log/`) vào **cùng thư mục** chứa token. Cách tách đã kiểm chứng: **`HOME` là `tmpfs`** (mất khi container thoát), chỉ **chép tệp token vào lúc khởi động** và chép ngược khi thoát nếu CLI làm mới nó. Kết quả: `status: SUCCESS`, trả lời "xin chào", token không đổi, **0 tệp phiên còn lại** trên volume token (khoảng 64 tệp phát sinh chỉ nằm trong tmpfs). Lưu ý: gắn riêng một tệp vào thư mục chưa có khiến Docker tạo thư mục cha thuộc root và `agy` không ghi nhật ký được; phải dùng cách chép như trên.

## 5. Quyền và công cụ
| | Kết quả |
|---|---|
| Mặc định không tương tác | Cấu hình `toolPermission=request-review`. Mô hình **vẫn cố gọi công cụ** (`read_file`), bị **tự từ chối** vì chế độ không tương tác không hỏi được. JSON có trường **`denied_actions`** liệt kê các lần cố gọi bị chặn: tín hiệu tốt để phát hiện lời nhắc độc hại. Thông báo gợi ý thêm quy tắc `permissions.allow` hoặc `--dangerously-skip-permissions` (**cấm dùng cờ này**) |
| Tệp mồi ngoài thư mục làm việc | **Không lộ** ở cả 3 chế độ thử (mặc định, `--mode plan`, `--mode plan --sandbox`); đã tìm chuỗi mồi trong câu trả lời và trong toàn bộ nhật ký, dữ liệu phiên (0 tệp) |
| `id`, ghi tệp | Không chạy, không tạo tệp |
| Hạn chế | Rào chắn **duy nhất** vẫn là hệ thống xin quyền (giống Claude): công cụ tồn tại và mô hình cố gọi. Có các khóa cài đặt `disabledTools`, `permissions`, `mcpServers`, `sandboxMode` (thấy trong tệp thực thi, **chưa thử** ngữ nghĩa); cờ `--settings` nhận JSON cho riêng phiên |
| `--mode plan` | **Không dùng cho trò chuyện**: agent tạo tệp kế hoạch `implementation_plan.md` thay vì trả lời, và câu trả lời bị trống |
| Prompt thường (không công cụ) | Trả lời bình thường ("xin chào"), `denied: None` |

## 6. Số liệu
- **G1 đạt:** JSON có `usage` (`input_tokens`, `output_tokens`, `thinking_tokens`, `cache_read_tokens`, `total_tokens`).
- **G2:** không thấy thông tin phần trăm hạn mức trong đầu ra (chưa tìm kỹ).
- **Chi phí nền cao:** khoảng 9.600 đến 12.000 token cho một lượt "trả lời một từ".
- **Độ trễ:** khoảng 23 đến 54 giây cho lượt tầm thường (chế độ `plan` suy luận nhiều); khoảng 25 đến 32 giây ở chế độ mặc định.

## 7. Còn chưa làm với `agy`
Tên miền cần ra ngoài (chưa ghi qua bộ ghi tên miền; **có cơ chế tự cập nhật** thử ghi tệp `.update_test…` cạnh tệp thực thi); cách **tắt tự cập nhật**; ảnh vào và ra; song song cùng volume; thử `disabledTools` và `permissions.deny`; nhiều tài khoản (mỗi tài khoản một volume, đã đủ cơ sở nhưng chưa thử với hai tài khoản Google).
