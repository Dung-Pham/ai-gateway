# Cổng AI — Gateway, runner và proxy (Giai đoạn 1): đã dựng gì, đã kiểm chứng gì

Ngày: 19/11/2026. Mức đạt theo AGENTS.md mục 1.2: **Đã viết code** → **Test giả lập đạt** → **Tích hợp trên máy dev đạt (CLI thật, Docker Compose, proxy thật)**. **Chưa** tích hợp staging, **chưa** có máy AI tách biệt, **chưa** có người thật nghiệm thu.

## Kiến trúc đã dựng

```
Ứng dụng ──(Bearer gw_…)──► GATEWAY ──(bí mật riêng mỗi runner)──► RUNNER (1 tài khoản AI) ──► CLI (claude | grok | agy)
                              │  chính sách, hạn mức,                  │ thư mục tạm riêng mỗi yêu cầu          │
                              │  định tuyến, ghi usage_events          │ chỉ token nằm trên volume              ▼
                              ▼                                         └── mạng ai_internal (KHÔNG ra internet) ──► EGRESS-PROXY ──► tên miền allowlist
                        CSDL của control/ (hợp đồng contracts/control_schema.sql)
```

| Thành phần | Thư mục | Điểm chính |
|---|---|---|
| Runner | `runner/` | Chỉ thư viện chuẩn. Mỗi yêu cầu: thư mục tạm riêng, `HOME` tạm, môi trường tối thiểu, token chép vào lúc khởi động và ghi ngược nếu CLI làm mới, xóa sạch khi xong. Ghim SHA-256 của CLI. **Ngắt mạch** khi CLI để lộ công cụ/connector |
| Gateway | `gateway/` | FastAPI, API tương thích OpenAI (`/v1/chat/completions`, `/v1/models`), khóa băm HMAC khớp `AppKey::issue`, chính sách theo ứng dụng, hạn mức (rpm, song song, đơn vị/ngày, đơn vị/tháng; cứng/mềm), chọn tài khoản theo pool, thử lại tối đa 1 lần khi hãng báo giới hạn/hết phiên, ghi `usage_events`, `/internal/state`, `/metrics` |
| Egress proxy | `egress-proxy/` | CONNECT chỉ tới `host:port` trong allowlist, từ chối IP trần và tên phân giải về địa chỉ nội bộ |
| Triển khai | `deploy/` | Compose: `read_only`, `cap_drop: ALL`, `no-new-privileges`, giới hạn tài nguyên, mạng `internal: true` |
| Vận hành | `scripts/gwctl.py` | Tạo bí mật và thư mục xác thực cho runner (không có trong giao diện web, có chủ đích) |

Cờ cứng hóa từng CLI (đã kiểm chứng ở KET_QUA_THAM_DO, đã mã hóa thành test): Claude `--strict-mcp-config`; Grok `--disallowed-tools` đủ 24 công cụ; agy không dùng `--dangerously-skip-permissions` và `--mode plan`.

## Bằng chứng

### Test tự động (CLI/runner GIẢ, định dạng đầu ra lấy từ mẫu thật)
- `runner`: 54 test. `gateway`: 87 test. `egress-proxy`: 43 test. **Tổng 184**. Chạy lại: `scripts/test_all.sh` (kèm kiểm tra hợp đồng dữ liệu và `pip-audit`: không có lỗ hổng đã biết trong 20 gói đã khóa phiên bản).
- Đã bao phủ: xác thực khóa (thiếu/sai/thu hồi/hết hạn/ứng dụng bị ngưng), vector băm khớp PHP thật, chính sách và model, hạn mức cứng/mềm, định tuyến và dự phòng, cooldown, hết phiên, ngắt mạch, chống SSRF ở URL runner và proxy, không ghi nội dung vào log/CSDL, dọn thư mục tạm kể cả khi lỗi, ghi ngược token, ghim SHA-256, gateway chỉ ghi `usage_events` và `app_keys.last_used_at`.

### Tích hợp trên máy dev (CLI thật, Compose thật, proxy thật)
| Kiểm tra | Kết quả |
|---|---|
| Claude, Grok, agy thật qua gateway → runner → proxy | **Cả ba trả lời đúng**, có số token, đơn vị quy đổi, tiêu đề `X-Gateway-Account` |
| Runner cứng hóa (hệ tệp chỉ đọc, 0 capability, giới hạn tài nguyên) chạy được cả ba CLI | **Đạt** |
| Thư mục tạm sau lượt chạy | **Trống** (cả ba) |
| Trong runner: ra internet trực tiếp, metadata, cổng máy chủ (22, cầu nối cũ) | **Đều bị chặn** |
| Qua proxy tới tên miền ngoài allowlist, `169.254.169.254`, `127.0.0.1` | **403 / từ chối** |
| `usage_events` trong CSDL của control | Ghi đúng, kể cả lượt lỗi (0 đơn vị) |
| Gateway công bố `%` hạn mức thật của Claude (`/internal/state`) | **Có** (cửa sổ 5 giờ và tuần) |

### Allowlist tối thiểu đo được (đã thử bỏ từng tên)
`api.anthropic.com:443` (Claude), `cli-chat-proxy.grok.com:443` (Grok), `daily-cloudcode-pa.googleapis.com:443` + `www.googleapis.com:443` (agy). **Cố ý chặn**: `http-intake.logs.us5.datadoghq.com` và `mcp-proxy.anthropic.com` (Claude vẫn chạy), `play.googleapis.com`, `lh3.googleusercontent.com`, `aicode.googleapis.com` (agy vẫn chạy).

### Phát hiện trong lúc dựng
- `--sandbox strict` của Grok cần `bwrap` và từ chối chạy (đóng an toàn) trong container đã cứng hóa: mặc định **tắt**, lớp kiểm soát là gỡ đủ công cụ + kiểm tra sau mỗi lượt + chính container.
- Gỡ hẳn công cụ Grok cho token đầu vào **giảm từ khoảng 5.000 xuống 656** so với `--tools ""`.
- Claude chỉ cần `.credentials.json` trong volume xác thực.

## Vì sao runner không ra internet, và tìm nguồn thì làm thế nào

**Vì sao khóa:** runner nhận nội dung từ bên ngoài và chạy một mô hình có thể bị lời nhắc độc hại điều khiển. Nếu runner ra internet tự do, kẻ xấu có thể (1) gửi dữ liệu ra ngoài, (2) dò các dịch vụ nội bộ qua chính máy runner (SSRF). Đường duy nhất ra ngoài là proxy có allowlist.

**Tìm nguồn (đã thí nghiệm với Claude thật, `scripts/search_egress_experiment.py`):**

| Cấu hình | Kết quả khi allowlist chỉ có `api.anthropic.com` |
|---|---|
| Chỉ công cụ `WebSearch` | **Chạy được**: trả 2 tài liệu thật (tiêu đề, URL, nội dung). Tìm kiếm thực hiện phía Anthropic, **runner không cần ra internet** |
| `WebSearch` + `WebFetch` | **Hỏng**: `WebFetch` tải trang **từ chính máy runner** (`vinmec.com`, `pharmacity.vn`…), bị chặn, mô hình trả `[]` |

Hệ quả cho thiết kế khả năng `web_search` (chưa dựng):
1. **Mức "tìm nhẹ"** (khuyến nghị làm trước): runner Claude chỉ bật `WebSearch`, dùng chung allowlist hiện tại, **không cần mở internet**. Chưa đánh giá chất lượng nội dung so với `WebFetch` (đoạn trích có thể ngắn hơn).
2. **Mức "tìm đầy đủ"** (cần `WebFetch`): runner **riêng** cho khả năng này, đặt sau một proxy "web công khai": cho phép mọi tên miền công khai trên cổng 80/443 nhưng **chặn mọi địa chỉ nội bộ** sau khi phân giải (bộ lọc này đã có trong `egress-proxy/`, chỉ cần thêm chế độ không allowlist tên miền), ghi log tên miền, giới hạn tốc độ. Dùng tài khoản riêng, không chia sẻ token với runner văn bản, và coi mọi nội dung trang là **dữ liệu không tin cậy**.
3. **Cảnh báo về cầu nối hiện tại:** `claude_bridge.py` đang cho Claude dùng `WebFetch` **ngay trên server chính, không có bộ lọc địa chỉ nội bộ nào**. Tôi **chưa kiểm tra** `WebFetch` có tự chặn địa chỉ riêng/metadata không. Nếu không, một trang web độc hại có thể khiến nó tải URL nội bộ. Nên kiểm tra sớm.

## Giới hạn đã biết (nói thẳng)
1. **Chỉ văn bản.** Chưa có stream, ảnh vào/ra, tìm web (gateway trả `stream_not_supported` / `capability_not_supported`). **SEO Studio chưa thể chuyển sang gateway cho tới khi có `web_search`**, vì nó đang dùng `/v1/search` của cầu nối.
2. **Một tiến trình gateway**; cooldown, tài khoản hết phiên, bộ đếm rpm nằm trong bộ nhớ, mất khi khởi động lại. Muốn mở rộng phải chuyển sang Redis.
3. **Chưa có kiểm tra sức khỏe định kỳ, cảnh báo, và nút "Kiểm tra ngay" của giao diện chưa nối vào gateway.** `/internal/state` đã có nhưng control/ chưa đọc.
4. **Chưa thử với PostgreSQL**, chỉ SQLite (gói `psycopg` đã khóa nhưng chưa chạy thật).
5. **Việc làm mới token** (ghi ngược vào volume) chỉ được test bằng CLI giả; chưa thấy CLI thật làm mới token trong lúc thử.
6. Prompt của agy đi qua đối số dòng lệnh (giới hạn 100.000 byte, thấy được bằng `ps` trong container).
7. Tài khoản thử: Claude là **tài khoản đang code** của bạn; agy dùng token của tài khoản Google AI Pro **cá nhân** của bạn. Bản sao token nằm ở `~/.probe/smoke/` (quyền 0600). Cần dọn khi xong.
8. **Mọi thứ chạy trên cùng một máy dev**: lớp cách ly mạnh nhất (máy AI tách biệt, tường lửa hai chiều) **chưa dựng và chưa kiểm thử**. Chỉ khi chạy các bước kiểm chứng trong CACH_LY_MAY_CHU.md mục 6 trên staging mới được coi là đạt.
9. Runner khai báo tĩnh trong Compose (mỗi tài khoản một dịch vụ); `gwctl.py new-runner` mới tạo bí mật và in hướng dẫn, chưa tự sinh dịch vụ.

## Chạy lại
```bash
scripts/test_all.sh                      # 184 test + hợp đồng + pip-audit
cp deploy/.env.example deploy/.env       # điền, rồi:
python3 scripts/gwctl.py init ./secrets --pepper-file <tệp chứa APP_KEY của control/>
python3 scripts/gwctl.py new-runner ./secrets claude claude-01   # lặp cho từng tài khoản
docker compose -f deploy/docker-compose.yml up -d
```
