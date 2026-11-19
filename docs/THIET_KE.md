# Cổng AI (AI Gateway) — Thiết kế hệ thống

Trạng thái: **bản thiết kế, chưa có dòng code nào**. Ngày soạn: 19/11/2026.
Tài liệu đi kèm: [GIAO_DIEN.md](GIAO_DIEN.md) (UX, nghệ thuật, màn hình) và [KE_HOACH_BUILD.md](KE_HOACH_BUILD.md) (kế hoạch, kiểm thử, nghiệm thu).

## 1. Mục tiêu và phạm vi

Đưa các gói AI đăng ký (Claude, Codex/ChatGPT, Gemini — đều dùng qua CLI) lên server và biến chúng thành **một API dùng chung** cho các ứng dụng của tổ chức (SEO Studio và các ứng dụng sau này), kèm công cụ quản lý.

Đây là sự tiếp nối của hai cầu nối hiện có (`scripts/claude_bridge.py`, `scripts/codex_bridge.py`): vẫn là "CLI → API OpenAI-tương thích", nhưng gộp chung, nhiều tài khoản, đo lường theo ứng dụng, và bảo mật chặt hơn.

Nhu cầu đã chốt trong buổi thảo luận:

| # | Nhu cầu | Cách đáp ứng |
|---|---|---|
| N1 | Dùng nhiều hãng, mỗi hãng có thể có **nhiều tài khoản** | Mô hình Account + Pool (mục 4) |
| N2 | **Phân biệt rõ theo tài khoản** để không nhầm | Nhãn tài khoản, header `X-Gateway-Account`, nhật ký ghi tài khoản, màu/biểu tượng riêng trên giao diện |
| N3 | Quản lý **theo từng ứng dụng**: token, giới hạn, lưu lượng | Mô hình App + Policy + Limit + UsageEvent (mục 4, 6) |
| N4 | Theo dõi hạn mức (kiểu "đánh dấu trước/sau rồi trừ") | Đo lường 4 lớp (mục 6), kết quả phụ thuộc bước thăm dò CLI |
| N5 | **Giới hạn quyền của AI** để kẻ xấu không điều khiển được server | Mô hình đe dọa và cách ly (mục 7) |
| N6 | Không chỉ văn bản: còn **phân tích ảnh, sinh ảnh**, và các tính năng sau này | Danh mục khả năng dạng dữ liệu, mặc định từ chối (mục 5) |
| N7 | Không cứng hóa theo ứng dụng/tính năng hiện tại | Chính sách theo app lưu trong DB, thêm khả năng không sửa lõi |
| N8 | Giao diện đẹp, dễ dùng, chuyên nghiệp, **màu chủ đạo xanh dương**, khác SEO Studio | Xem GIAO_DIEN.md |
| N9 | Dùng công nghệ tương tự SEO Studio nếu hợp | Mặt điều khiển dùng Laravel + Filament (mục 3) |

Ngoài phạm vi (ghi rõ để khỏi hiểu nhầm):

- Không bán hay chia sẻ tài khoản cho bên ngoài tổ chức. Chỉ phục vụ ứng dụng nội bộ.
- Không đọc hay thay đổi hạn mức thật của hãng. Gateway chỉ quan sát và tự đặt hạn mức nội bộ.
- Không tự đăng nhập hộ tài khoản AI. Đăng nhập là việc làm tay của người quản trị.

## 2. Điều chưa biết (phải làm rõ ở bước thăm dò, không được đoán)

Thiết kế dưới đây dựa trên các giả định sau. Mỗi giả định phải được xác nhận trên server thật trước khi coi là đúng (Giai đoạn 0 trong kế hoạch).

| Giả định | Nếu sai thì sao |
|---|---|
| G1. Mỗi CLI xuất được kết quả dạng JSON có thông tin token (`usage`) | Phải ước lượng token từ độ dài chữ và gắn nhãn "ước lượng" |
| G2. Đọc được phần trăm hạn mức (phiên/tuần) bằng cách không tương tác | Mất lớp đo "% thật"; chỉ còn đếm nội bộ và bắt lỗi 429 |
| G3. Khi chạm giới hạn, CLI trả thông báo/mã lỗi nhận dạng được, có kèm giờ reset | Phải dùng thời gian nghỉ cố định rồi thử lại |
| G4. CLI nhận ảnh làm đầu vào mà **không cần bật công cụ đọc tệp** (ví dụ qua đầu vào dạng luồng) | Phải tìm cách an toàn khác, hoặc tắt khả năng ảnh vào cho hãng đó |
| G0. Mỗi hãng còn đăng nhập được bằng gói đăng ký qua CLI | **Gemini CLI: sai** (CLI báo ngừng hỗ trợ đăng nhập cá nhân, chuyển sang Antigravity `agy`); các hãng khác chưa thử |
| G5. Có gói nào đó sinh được ảnh qua CLI | Sinh ảnh chuyển sang đường khác (API key riêng) hoặc không hỗ trợ |
| G6. Hai tiến trình CLI cùng dùng một thư mục cấu hình không làm hỏng việc làm mới token | Phải khóa theo tài khoản, chỉ một tiến trình ghi một lúc |
| G7. Có cờ chế độ chỉ-trò-chuyện/sandbox chỉ đọc cho Codex và Gemini | Phải tăng cường cách ly ở tầng container |

## 3. Kiến trúc tổng thể

Hai mặt tách biệt (control plane và data plane), cộng thêm một "runner" cho mỗi tài khoản AI.

```
 Ứng dụng nội bộ (SEO Studio, ...)           Quản trị viên
        │  Bearer API key của app                  │  đăng nhập + 2FA, mạng nội bộ/VPN
        ▼                                          ▼
┌──────────────────────┐   đọc chính sách    ┌──────────────────────────┐
│  GATEWAY (data plane)│◄────────────────────│ ĐIỀU KHIỂN (control plane)│
│  Python / FastAPI    │   ghi usage_events  │ Laravel 12 + Filament 3   │
│  - xác thực API key  │────────────────────►│ - quản trị app/tài khoản  │
│  - chính sách, hạn mức│                    │ - bảng điều khiển, cảnh báo│
│  - định tuyến, retry │                     │ - kiểm toán, cài đặt      │
│  - ghi số liệu       │                     └────────────┬─────────────┘
└─────────┬────────────┘                                  │
          │ mạng nội bộ, không có đường ra internet       ▼
          │                                      PostgreSQL + Redis
          ▼
┌───────────────────────────────┐   ┌──────────────────────┐
│ RUNNER (mỗi tài khoản 1 cái)  │   │ RUNNER-SEARCH (riêng) │
│ - chạy CLI trong sandbox      │   │ - tìm web, chặn IP nội│
│ - volume cấu hình riêng 0600  │   │   bộ (chống SSRF)     │
│ - chỉ ra được tên miền hãng   │   └──────────────────────┘
└───────────────┬───────────────┘
                ▼
   Proxy chuyển tiếp có danh sách tên miền cho phép  →  internet (Anthropic / OpenAI / Google)
```

### Lựa chọn công nghệ và lý do

| Thành phần | Chọn | Lý do |
|---|---|---|
| Gateway (data plane) | **Python 3.12 + FastAPI + uvicorn** | Tái sử dụng logic của hai cầu nối Python hiện có; xử lý bất đồng bộ tốt cho các yêu cầu dài và streaming; PHP-FPM không hợp với yêu cầu chạy hàng phút |
| Điều khiển (control plane) | **Laravel 12 + Filament 3.3** (Laravel 11 có cảnh báo bảo mật chưa vá; xem KIEM_CHUNG_GIAO_DIEN.md) | Đội đã quen; Filament cho bảng, form, biểu đồ, phân quyền nhanh; giao diện được thay hoàn toàn bằng theme riêng (xem GIAO_DIEN.md) |
| CSDL | **PostgreSQL** | Dùng chung hai mặt; vai trò DB riêng cho gateway (chỉ đọc bảng chính sách, chỉ INSERT bảng usage) |
| Bộ nhớ đệm, đếm giới hạn | **Redis** | Bộ đếm tốc độ, khóa slot song song, cache chính sách, hàng đợi |
| Runner | Cùng một image, mỗi tài khoản một container, API HTTP nhỏ nội bộ | **Gateway không cần Docker socket** (socket Docker tương đương quyền root, trái với yêu cầu N5) |
| Proxy ra ngoài | Proxy chuyển tiếp có allowlist tên miền | Chặn dữ liệu bị gửi ra ngoài nếu AI bị lừa |
| Số liệu | Endpoint Prometheus + log JSON | Tích hợp cảnh báo, không lộ nội dung prompt |
| Triển khai | Docker Compose trên server (hoặc systemd nếu bạn chọn khác — **cần bạn xác nhận**) | Mỗi runner một service; thêm tài khoản bằng một script của người vận hành |

Quyết định này khác với giả định "viết hết bằng Laravel": PHP có thể làm control plane tốt nhưng không nên là nơi giữ các kết nối dài và quản lý tiến trình CLI.

## 4. Mô hình dữ liệu

Laravel sở hữu migration. Tên bảng bằng tiếng Anh, nhãn hiển thị bằng tiếng Việt.

| Bảng | Cột chính | Ghi chú |
|---|---|---|
| `providers` | id, code (`claude`,`codex`,`gemini`), name | Danh mục hãng |
| `accounts` | id, provider_id, **label** (duy nhất, ví dụ `claude-runner-01`), plan, status (`active`,`paused`,`cooldown`,`auth_expired`,`draining`), cooldown_until, max_concurrency, runner_url, notes, last_auth_check_at | Không lưu mật khẩu/token. Chỉ lưu trạng thái |
| `pools` / `pool_members` | pool: name, strategy; member: account_id, priority, weight | Một account có thể thuộc nhiều pool |
| `capabilities` | code (`text`,`image_in`,`image_out`,`web_search`, …), risk_level, handler, default_limits, metering_rule | **Danh mục khả năng** (mục 5) |
| `apps` | id, name, slug, owner, status (`active`,`suspended`), created_at | |
| `app_keys` | app_id, key_prefix, key_hash (HMAC), last_used_at, expires_at, revoked_at | Khóa hiển thị đúng một lần khi tạo. Nhiều khóa mỗi app để xoay vòng |
| `app_policies` | app_id, capability_code, allowed_models, route (pool_id hoặc account_id ghim), fallback_pool_id, enabled | Không có dòng = **không được dùng** |
| `app_limits` | app_id, capability_code (nullable = tất cả), rpm, max_concurrency, units_per_day, units_per_month, hard_or_soft | Soft = cảnh báo, Hard = từ chối 429 |
| `usage_events` | id, ts, app_id, key_id, account_id, capability, model, input_units, output_units, weighted_units, latency_ms, status, error_class, request_id, retry_of | **Không lưu nội dung prompt/phản hồi** mặc định |
| `usage_rollups` | bucket (giờ/ngày), app_id, account_id, capability, … | Tổng hợp theo lịch để bảng điều khiển nhanh |
| `quota_samples` | account_id, ts, window (`5h`,`week`), pct_used, source, raw_ok | Chỉ có nếu G2 đúng |
| `model_weights` | model, weight, effective_from | Hệ số quy đổi đơn vị theo model |
| `alerts` / `incidents` | type, subject, opened_at, resolved_at, details | |
| `audit_logs` | actor, action, subject, before, after, ip, ts | Mọi thao tác quản trị |
| `admin_users` | Filament user + vai trò (`admin`, `operator`, `viewer`, `app_owner`) | `app_owner` chỉ xem app của mình |

## 5. Danh mục khả năng và chính sách (không cứng hóa)

Nguyên tắc: **khả năng là dữ liệu, mặc định từ chối**.

- Mỗi khả năng khai báo: loại đầu vào/đầu ra, mức rủi ro, bộ xử lý (runner nào), giới hạn mặc định, quy tắc đo.
- Khởi đầu có bốn khả năng: `text`, `image_in`, `image_out`, `web_search`. Các khả năng sau này (âm thanh, PDF, embedding…) được thêm bằng cách thêm một dòng và một bộ xử lý, **không sửa lõi gateway**.
- Ứng dụng mới không có khả năng nào cho đến khi quản trị viên cấp. Mỗi lần cấp có kiểm toán.
- Khả năng rủi ro cao (`web_search`, các khả năng cần ra mạng hoặc đọc tệp) chạy ở runner riêng và phải bật chủ động.
- Gateway **chỉ nhận nội dung do app gửi lên**; không bao giờ tự tải URL, không nhận đường dẫn tệp. Nguồn gốc của ảnh/tệp (người dùng, web, kho nội bộ) do app chịu trách nhiệm; gateway coi mọi đầu vào là không tin cậy.

## 6. Đo lường và hạn mức

Không có API hạn mức chính thức cho gói đăng ký, nên đo bằng bốn lớp, mỗi lớp có độ tin cậy khác nhau và giao diện phải hiển thị đúng nguồn gốc của từng con số.

| Lớp | Cách làm | Dùng để | Độ tin cậy |
|---|---|---|---|
| L1. Đếm theo lượt | Lấy `usage` từ kết quả JSON (nếu G1). Quy đổi ra **đơn vị quy đổi** = token vào/ra × hệ số theo model (`model_weights`) × hệ số khả năng. Nếu không có `usage` thì ước lượng và đánh dấu `estimated` | Chia mức dùng theo app, đặt hạn mức theo app | Cao về "ai dùng bao nhiêu"; không phải số của hãng |
| L2. Lấy mẫu % thật | Cứ vài phút đọc % phiên/tuần của từng tài khoản (nếu G2). Phần tăng thêm trong khoảng được chia cho các app theo tỉ lệ đơn vị quy đổi | Biết tài khoản "đã đầy bao nhiêu" | Trung bình; phụ thuộc định dạng CLI |
| L3. Hiệu chỉnh | Gộp L1 và L2 để học "bao nhiêu đơn vị ≈ 1%" cho từng tài khoản; dự báo "còn khoảng N lượt" | Cảnh báo sớm, chọn tài khoản | Ước lượng; luôn ghi "dự báo" |
| L4. Phản ứng khi chạm giới hạn | Bắt lỗi 429 / thông báo "resets …" (nếu G3), đặt `cooldown_until` | Chuyển tài khoản, ngừng nhận | Cao nhất (hãng tự báo) |

Phương pháp "đánh dấu trước/sau rồi trừ" của bạn chính là L2 làm theo từng yêu cầu. Chỉ dùng được khi yêu cầu chạy một mình trên tài khoản. Khi chạy song song thì dùng cách chia tỉ lệ của L2 ở trên. Cũng có thể thêm "chế độ hiệu chuẩn": tạm chạy tuần tự một tài khoản để đo hệ số chính xác hơn.

Giao diện **không** gọi con số L2/L3 là "hạn mức còn lại của hãng". Gọi là "Mức dùng (đọc từ CLI)" và "Dự báo", có chú thích nguồn và thời điểm cập nhật.

### Hạn mức theo ứng dụng
Kiểm tra theo thứ tự, dừng ở lần vượt đầu tiên: khóa bị thu hồi → app tạm ngưng → khả năng không được cấp → rpm → số yêu cầu song song → đơn vị/ngày → đơn vị/tháng. Trả `429` kèm mã lý do (`app_rpm`, `app_daily_units`, …) và header `Retry-After` khi tính được. Mức "soft" chỉ tạo cảnh báo.

## 7. Bảo mật: giới hạn quyền của AI (yêu cầu N5)

Nguyên tắc: **AI chỉ là bộ sinh nội dung. Nó không có tay chân.** Mọi lớp dưới đây giả định rằng prompt hoặc ảnh có thể bị kẻ xấu cài lệnh, và vẫn không gây hại được cho server.

### 7.1 Mô hình đe dọa

| Mối đe dọa | Kịch bản | Chặn bằng |
|---|---|---|
| Prompt injection → chạy lệnh | Nội dung gửi tới bảo AI "chạy lệnh X" | Công cụ CLI tắt; user không có shell/sudo; cờ nguy hiểm bị cấm bằng test |
| Prompt injection → đọc tệp/bí mật | Bảo AI đọc `.env`, khóa SSH, phiên đăng nhập tài khoản khác | Container chỉ thấy volume của chính nó; không gắn mã nguồn/`.env`; hệ tệp chỉ đọc |
| SSRF qua tìm web/tải ảnh | Bảo AI mở `http://169.254.169.254` hoặc dịch vụ nội bộ | Gateway không tự tải URL; `runner-search` riêng, chặn các dải IP nội bộ/loopback/metadata |
| Rò dữ liệu ra ngoài | AI bị lừa gửi dữ liệu tới máy chủ lạ | Runner chỉ ra được tên miền hãng qua proxy allowlist |
| Ảnh độc hại | Ảnh khổng lồ, SVG chứa mã, EXIF lạ, chữ trong ảnh chứa lệnh | Xem 7.3 |
| Đánh cắp API key của app | Lộ khóa | Băm HMAC, hiển thị một lần, hạn dùng, thu hồi tức thì, giới hạn theo IP/mạng nội bộ nếu có |
| Chiếm giao diện quản trị | Đăng nhập yếu | Chỉ mạng nội bộ/VPN, 2FA, tách khỏi cổng API, kiểm toán |
| Chiếm một container runner | Mã độc nhảy sang container khác | Mỗi tài khoản một container/user, không chia sẻ volume, không Docker socket, bỏ capabilities |
| Chuỗi cung ứng | CLI cập nhật mang hành vi mới | Ghim phiên bản; chỉ nâng cấp có chủ đích sau khi chạy lại bộ test bảo mật |
| Từ chối dịch vụ | Một app gửi dồn | Hạn mức theo app, giới hạn kích thước thân yêu cầu, giới hạn tài nguyên container |

### 7.2 Biện pháp bắt buộc cho mọi khả năng (kể cả khả năng sau này)

1. **Cách ly tiến trình**: mỗi tài khoản một container riêng, user không đặc quyền, `no-new-privileges`, bỏ toàn bộ capabilities, hệ tệp gốc chỉ đọc (chỉ ghi vào thư mục cấu hình của chính nó và `tmpfs`), giới hạn CPU/RAM/số tiến trình/thời gian chạy. Không gắn Docker socket, mã nguồn ứng dụng, `.env`, khóa SSH.
2. **Tắt quyền trong CLI**: Claude giữ `--tools ""` và không nạp cấu hình/hook. Codex/Gemini chạy chế độ sandbox chỉ đọc hoặc chỉ trò chuyện (cờ chính xác chốt ở bước thăm dò). **Cấm** mọi cờ bỏ qua xác nhận (kiểu `--dangerously-skip-permissions`, `--yolo`); test tự động quét cấu hình để chắc chắn không có.
3. **Mạng**: runner chỉ liên lạc với gateway (vào) và proxy allowlist (ra). Chặn `127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.169.254` và các dải nội bộ của công ty.
4. **Đầu vào là dữ liệu**: nội dung đi qua stdin hoặc thân HTTP, **không bao giờ ghép vào đối số dòng lệnh**; không dùng shell; tên model theo danh sách cho phép; giới hạn kích thước.
5. **Đầu ra là dữ liệu không tin cậy**: gateway không thực thi, không diễn giải thành lệnh; chỉ trả văn bản hoặc JSON có cấu trúc, ảnh đã kiểm tra.
6. **Không ghi nội dung**: log và `usage_events` chỉ chứa số liệu. Bật ghi nội dung (để gỡ lỗi) phải là hành động riêng, có thời hạn tự tắt, có kiểm toán.
7. **Bí mật**: thư mục phiên đăng nhập quyền `0600` trong volume riêng, sao lưu có mã hóa; đăng nhập OAuth chỉ làm tay qua SSH, giao diện web không nhận mật khẩu/token.
8. **Dừng khẩn cấp**: nút tắt cho từng app, từng khóa, từng tài khoản, và toàn bộ gateway.
9. **Cách ly ở tầng máy**: chạy toàn bộ Cổng AI trên một máy (VM/VPS) tách biệt với server chính, tường lửa hai chiều, không chia sẻ bí mật; container chỉ là lớp thứ hai. Xem [CACH_LY_MAY_CHU.md](CACH_LY_MAY_CHU.md).

### 7.3 Xử lý ảnh (đầu vào và đầu ra)
- Chỉ nhận dữ liệu ảnh do app gửi; không tải từ URL.
- Kiểm tra loại bằng nội dung (không tin đuôi tệp). Cho phép PNG, JPEG, WebP, GIF; **cấm SVG**. Giới hạn dung lượng và số điểm ảnh.
- Giải mã rồi mã hóa lại trong một tiến trình riêng, xóa EXIF; lưu tạm theo yêu cầu và xóa ngay sau khi xong.
- Chữ trong ảnh là dữ liệu. Vì công cụ đã tắt nên nó chỉ có thể làm sai nội dung trả lời, không chạm được tới server.
- Ảnh sinh ra: kiểm tra và mã hóa lại trước khi trả; gateway không lưu lâu dài (app tự lưu ở kho của app).

### 7.4 Kiểm thử bảo mật
Một bộ test gồm: prompt/ảnh cố ý yêu cầu đọc `/etc/passwd`, chạy lệnh, gọi mạng nội bộ, gọi địa chỉ metadata; kiểm tra không có cờ nguy hiểm; kiểm tra container không thấy volume tài khoản khác. Theo AGENTS.md mục 1.2, kết quả chỉ được ghi "đạt" khi **chạy thật trên staging**; chạy mô phỏng chưa đủ.

## 8. API

Giữ định dạng OpenAI-tương thích để mọi SDK dùng được, có phiên bản (`/v1`).

| Endpoint | Khả năng | Ghi chú |
|---|---|---|
| `POST /v1/chat/completions` | `text`, `image_in` | Hỗ trợ `stream`; ảnh vào qua phần `image_url` dạng dữ liệu nhúng |
| `POST /v1/images/generations` | `image_out` | Chỉ bật khi G5 đạt |
| `POST /v1/search` | `web_search` | Giữ tương thích với endpoint đang dùng ở cầu nối Claude |
| `GET /v1/models` | — | Chỉ liệt kê model mà app được phép dùng |
| `GET /healthz`, `GET /metrics` | — | Chỉ mạng nội bộ |

Header phản hồi: `X-Gateway-Account` (nhãn tài khoản đã xử lý), `X-Gateway-Request-Id`, `X-Gateway-Units` (đơn vị quy đổi đã tính). Tên model theo dạng `claude:sonnet`, `codex:<model>`, `gemini:<model>`; cũng chấp nhận tên "ảo" do quản trị đặt (ví dụ `writer-default`) để đổi model phía sau mà app không phải sửa.

Lỗi trả theo định dạng OpenAI (`error.code`, `error.message`) với các mã riêng: `app_suspended`, `capability_not_granted`, `limit_exceeded`, `no_account_available`, `account_cooldown`, `upstream_error`.

## 9. Định tuyến và độ tin cậy

1. Xác thực khóa → nạp chính sách (cache Redis, hết hạn khi quản trị đổi) → kiểm tra hạn mức app.
2. Chọn ứng viên: trong route của app (pool hoặc tài khoản ghim), lọc tài khoản `active`, hỗ trợ khả năng, còn slot song song; chọn theo chiến lược (mặc định: mức dùng thấp nhất, rồi ưu tiên theo `priority`).
3. Giữ slot (khóa Redis có hạn), gọi runner.
4. Lỗi hạn mức (L4) trước khi có đầu ra: đặt cooldown cho tài khoản và **thử lại một lần** sang tài khoản khác. Không thử lại khi đã bắt đầu stream hoặc khi hết thời gian chờ mơ hồ (tránh tốn hạn mức vô ích; lỗi được báo cho app).
5. Hết phiên đăng nhập (`auth_expired`): tài khoản tự bị loại khỏi pool, mở sự cố và cảnh báo.
6. Ghi `usage_events` bằng INSERT-only.

Kiểm tra sức khỏe: định kỳ gọi thử rất nhẹ cho từng tài khoản để phát hiện hết phiên sớm (tần suất chốt sau thăm dò, vì gọi thử cũng tốn hạn mức).

## 10. Vận hành

- Thêm tài khoản: người vận hành chạy script tạo service runner + volume, SSH vào đăng nhập CLI một lần, rồi bấm "Kiểm tra" trên giao diện.
- Sao lưu: Postgres hằng ngày; volume phiên đăng nhập mã hóa. Có quy trình khôi phục và diễn tập.
- Cảnh báo: tài khoản hết phiên, tài khoản vào cooldown, app vượt 80%/100% hạn mức, lỗi 5xx tăng, runner không phản hồi, proxy chặn kết nối lạ.
- Nâng cấp CLI: ghim phiên bản trong image; thử trên staging, chạy bộ test thăm dò + bảo mật rồi mới nâng.
- Nhật ký kiểm toán không xóa được từ giao diện.

## 11. Phân quyền

| Vai trò | Quyền |
|---|---|
| `admin` | Toàn quyền, kể cả dừng khẩn cấp, quản lý người dùng |
| `operator` | Quản lý tài khoản/pool/app, không đổi người dùng, không xem kiểm toán đầy đủ |
| `viewer` | Chỉ xem bảng điều khiển và nhật ký |
| `app_owner` | Chỉ xem mức dùng và khóa của app mình; không thấy tài khoản AI hay app khác |

## 12. Rủi ro còn lại (nói thẳng)

- Độ chính xác đo "% hạn mức thật" phụ thuộc vào G2 và có thể vỡ khi CLI đổi định dạng. Đó là lý do có bộ test thăm dò chạy định kỳ.
- Mỗi yêu cầu khởi động một tiến trình CLI nên chậm và tốn RAM; số yêu cầu song song bị giới hạn bởi RAM trước khi bị giới hạn bởi hạn mức.
- Trạng thái nằm trên một máy; chưa thiết kế chạy nhiều gateway song song (đủ cho quy mô tổ chức, nhưng nên biết).
- Cân nhắc dùng gateway mã nguồn mở (ví dụ LiteLLM) cho phần khóa/ngân sách/nhật ký thay vì tự viết: **chưa kiểm chứng**; xem quyết định ở Giai đoạn 0.
