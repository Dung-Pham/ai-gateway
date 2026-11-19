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
- `runner`: 105 test. `gateway`: 137 test. `egress-proxy`: 43 test. **Tổng 285**. Chạy lại: `scripts/test_all.sh` (kèm kiểm tra hợp đồng dữ liệu và `pip-audit`: không có lỗ hổng đã biết trong 20 gói đã khóa phiên bản).
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

Hệ quả: **mức "tìm nhẹ" đã được dựng** (xem mục dưới). Mức "tìm đầy đủ" với `WebFetch` **chưa dựng**: nếu cần, phải là runner **riêng** sau một proxy "web công khai" (cho mọi tên miền công khai cổng 80/443, chặn mọi địa chỉ nội bộ sau khi phân giải; bộ lọc đã có sẵn trong `egress-proxy/`, chỉ cần thêm chế độ không allowlist tên miền), dùng tài khoản riêng, và coi mọi nội dung trang là dữ liệu không tin cậy.

## Kiểm tra: WebFetch của cầu nối cũ có chặn địa chỉ nội bộ không? (19/11/2026)

**Kết luận: KHÔNG có bộ lọc địa chỉ nội bộ thật; chỉ có kiểm tra theo tên, và nó bị vượt qua được.** Cầu nối cũ (`claude_bridge.py`) cho Claude dùng `WebFetch` trên server chính, không container, không proxy.

Cách đo (`scripts/webfetch_ssrf_probe.py`): dựng máy chủ "mồi" chỉ trả một chuỗi vô hại trên các địa chỉ nội bộ của máy; chạy Claude đúng cờ của cầu nối, bảo nó tải từng URL; bằng chứng lấy từ nhật ký kết nối của máy mồi và `strace -e connect` (không dựa vào việc mô hình tự từ chối).

| URL | Kết quả |
|---|---|
| `http://localhost:…`, `http://[::1]:…` | **Bị từ chối trước khi kết nối** ("WebFetch từ chối URL localhost"): kiểm tra theo **tên** |
| `http://127.0.0.1:…` | **Đã kết nối** (máy mồi nhận TLS ClientHello) |
| `http://192.168.1.18:…` (địa chỉ LAN) | **Đã kết nối** |
| `http://172.17.0.1:…` (cầu Docker) | **Đã kết nối** |
| `http://169.254.169.254/…` (metadata) | **Đã `connect()`** tới `169.254.169.254:443` (bị từ chối vì không có dịch vụ) |
| `http://127.0.0.1.nip.io:…` (tên công khai trỏ về loopback) | **Đã kết nối tới 127.0.0.1**: kiểm tra theo tên **bị vượt qua** |

Chi tiết quan trọng: mọi kết nối đều là **HTTPS** (với `http://` có cổng rõ ràng, máy mồi vẫn nhận TLS ClientHello; metadata bị kết nối ở cổng 443). Vì vậy dịch vụ nội bộ **chỉ nói HTTP** (siêu dữ liệu đám mây, đa số trang quản trị nội bộ) **không đọc được** qua đường này trong thử nghiệm của tôi.

**Chưa chứng minh được** (không nên khẳng định quá): việc đọc **nội dung** từ một dịch vụ nội bộ. Máy mồi của tôi không nói TLS nên không hoàn tất bắt tay. Chưa thử: dịch vụ HTTPS nội bộ thật với chứng chỉ mà CLI chấp nhận; chuyển hướng (redirect) từ trang công khai sang URL nội bộ; đường lời nhắc độc hại thật từ nội dung trang tìm được.

Rủi ro còn lại (đã suy luận, chưa khai thác thử): (1) tên nội bộ có chứng chỉ công khai hợp lệ trỏ về IP riêng thì đọc được; (2) có thể **dò mạng nội bộ**: thông báo lỗi khác nhau ("Socket is closed" so với "ECONNREFUSED") cho biết máy/cổng nào mở; (3) GET tới điểm cuối HTTPS nội bộ có tác dụng phụ. Mức độ: **trung bình, không phải lỗ hổng đọc dữ liệu hiển nhiên**, nhưng thiếu hẳn một lớp kiểm soát cơ bản và nằm trên server chính.

Cách xử lý (chưa làm, chờ bạn quyết): (a) đổi `run_search` của cầu nối cũ sang **chỉ `WebSearch`** như gateway mức nhẹ (mẫu thử cho kết quả tương đương); hoặc (b) chuyển SEO Studio sang gateway; (c) tạm thời chặn đường ra của dịch vụ cầu nối tới dải riêng/loopback/link-local bằng systemd (`IPAddressDeny=`/`IPAddressAllow=`, cần thử vì cầu nối cũng phải nhận kết nối từ mạng Docker).

## Khả năng `web_search` mức nhẹ (đã dựng, 19/11/2026)

`POST /v1/search`, **tương thích cầu nối cũ**: nhận `{query, max_results (1–8), include_domains, exclude_domains}`, trả `{"results":[{title,url,content}]}`; lỗi theo định dạng của gateway (`429` kèm `Retry-After`, `502`, `504`).

| Lớp bảo vệ | Cách làm |
|---|---|
| Chỉ một công cụ | Claude chạy với `--tools WebSearch --allowedTools WebSearch --max-turns 6` cùng toàn bộ cờ cứng hóa của đường văn bản. **Không bao giờ có `WebFetch`** |
| Ngắt mạch | Nếu sự kiện khởi tạo của CLI cho thấy bất kỳ công cụ nào ngoài `WebSearch`, hoặc có máy chủ MCP: `policy_violation`, runner ngắt mạch cho mọi khả năng |
| Không cần ra internet | Tìm kiếm thực hiện phía hãng; proxy chỉ thấy `api.anthropic.com` (đã đo) |
| Làm sạch kết quả (runner **và** gateway, hai lần độc lập) | Chỉ `http(s)`, cổng 80/443, bỏ thông tin đăng nhập trong URL, bỏ IP trần, `localhost` và tên nội bộ (`.local`, `.internal`…), bỏ ký tự điều khiển và ký tự đảo chiều/ẩn, khử trùng URL, giới hạn độ dài và tối đa 8 kết quả |
| Bộ lọc tên miền xác định | `include_domains` / `exclude_domains` được áp dụng bằng mã, **không tin việc mô hình tự lọc** (đã thử thật: `include_domains=["vinmec.com"]` cho 4/4 kết quả thuộc vinmec.com) |
| Dữ liệu web là dữ liệu | Lời nhắc hệ thống yêu cầu coi kết quả tìm kiếm là dữ liệu; kể cả bị lừa, mô hình chỉ có `WebSearch` (không đọc tệp, không chạy lệnh, không tải trang) |
| Riêng tư | Nội dung truy vấn không vào log, không vào `usage_events` (có test) |
| Hạn mức riêng | Cửa sổ yêu cầu mỗi phút và hạn mức đơn vị ngày/tháng tính **riêng** cho `web_search`; hạn mức chung (`capability_code` rỗng) cộng mọi khả năng |

Hai lỗi thiết kế được phát hiện và sửa khi viết test cho khả năng này: bộ đếm yêu cầu mỗi phút trước đó dùng chung cho mọi khả năng, và hạn mức ngày/tháng của riêng một khả năng trước đó cộng cả khả năng khác.

**Đo thật** (Claude thật qua Compose, một truy vấn tiếng Việt, nên chỉ là mẫu nhỏ, không phải đánh giá thống kê):

| | Số kết quả | Độ dài đoạn trích | Thời gian |
|---|---|---|---|
| Gateway, mức nhẹ (chỉ `WebSearch`) | 4 | 291 đến 365 ký tự | khoảng 22 giây |
| Cầu nối cũ (có `WebFetch`) | 3 | 107 đến 204 ký tự | khoảng 29 giây |

Lưu ý chất lượng: tiêu đề đôi khi là siêu dữ liệu thô của trang (ví dụ "backup og meta"), vì mức nhẹ lấy từ kết quả tìm kiếm chứ không đọc trang. Chưa so sánh độ chính xác của nội dung.

**Chuyển SEO Studio sang gateway (chưa làm):** (1) đăng ký ứng dụng trong giao diện điều khiển, cấp cả `text` và `web_search`, tạo khóa; (2) trỏ nhà cung cấp "claude_bridge" sang địa chỉ gateway với khóa mới (đường `/v1/search` giữ nguyên); (3) tên model: gateway nhận `claude:sonnet`, còn dạng không tiền tố (`sonnet`) chỉ được chấp nhận khi pool của ứng dụng chỉ có một hãng, nên nên cho SEO Studio một pool chỉ có Claude; (4) thêm địa chỉ gateway vào `AI_PRIVATE_BASE_URL_ALLOWLIST`; (5) chạy song song với cầu nối cũ một thời gian trước khi tắt.

## Thử chuyển SEO Studio sang gateway (19/11/2026, bản thử `seo-plan-import`)

**Mức kiểm chứng:** code + test giả lập + chạy tích hợp trên máy dev. **Chưa** staging, **chưa** máy AI tách biệt, **chưa** có người nghiệm thu, **chưa** chuyển bản SEO Studio thật.

Đã làm:
1. Dựng stack chỉ có Claude (gateway + egress-proxy + `runner-claude-01`), CSDL riêng: một pool "chỉ Claude", một ứng dụng `seo-studio` có `text` + `web_search`, một khóa; gateway nghe trên cầu Docker (`GATEWAY_BIND=172.17.0.1`, cổng 18771), không mở ra mạng ngoài. Runner dùng token tài khoản chạy bài (`~/.claude-runner`), không dùng tài khoản đang code.
2. Từ container SEO Studio: `/v1/models`, `/v1/chat/completions` (model `haiku` không tiền tố) và `/v1/search` đều chạy.
3. Trong `seo-plan-import`: thêm `172.17.0.1:18771` vào `AI_PRIVATE_BASE_URL_ALLOWLIST` (phải tạo lại container vì biến môi trường không đổi được khi đang chạy), chạy `agent:claude-setup --base-url=http://172.17.0.1:18771/v1 --roles=writer,strategist` (khóa truyền qua biến `CLAUDE_BRIDGE_TOKEN`, không in ra).
4. Chạy `agent:run` cho một bài ("huyết áp thấp là gì"): ra bản nháp `needs_review`, cổng ghi 8 lượt `text` (sonnet) và 10 lượt `web_search`, 0 lỗi.

Đối chiếu với cầu nối cũ (đã chỉ còn WebSearch), cùng từ khóa, **mỗi bên một lần chạy** (mẫu quá nhỏ để kết luận): cổng ~2.470 từ, 7 nguồn được trích, 36.601 token, 5.607 VND; cầu nối cũ ~2.430 từ, 5 nguồn được trích, 33.333 token, 4.445 VND. Cả hai bài đều bị gắn cờ cần người có chuyên môn duyệt.

Kiểm tra nguồn của bài ở gateway: NHS, MedlinePlus, Cleveland Clinic, Vinmec, PMC tải được và chứa các từ khóa cốt lõi; PubMed 38123372 khớp tiêu đề bài trích. **Chưa xác minh được:** Mayo Clinic (403), StatPearls (reCAPTCHA; là nguồn cho mốc tâm thu <90 / huyết áp trung bình <65), Bộ Y tế (lỗi SSL). Chưa đối chiếu từng câu trích với từng nguồn. Danh sách nguồn cuối bài có 11 mục nhưng thân bài chỉ trích 7 (cả hai bản đều lệch kiểu này).

Phát hiện vận hành: systemd người dùng **không thực thi** `IPAddressDeny=`/`IPAddressAllow=` (cần quyền BPF của dịch vụ cấp hệ thống), nên không dùng làm lớp chặn cho cầu nối cũ; thay bằng bỏ WebFetch khỏi cầu nối cũ và dùng egress proxy của gateway.

## Giới hạn đã biết (nói thẳng)
1. **Chỉ văn bản và tìm web mức nhẹ.** Chưa có stream, ảnh vào/ra, tìm web đầy đủ (`WebFetch`). Gateway trả `stream_not_supported` / `capability_not_supported` cho phần chưa có.
2. **Một tiến trình gateway**; cooldown, tài khoản hết phiên, bộ đếm rpm nằm trong bộ nhớ, mất khi khởi động lại. Muốn mở rộng phải chuyển sang Redis.
3. **Giao diện đã nối gateway (19/11 tối):** lệnh `gateway:sync` mỗi 30 giây chép slot, nghỉ, hết phiên và % hạn mức vào bảng `accounts`; nút "Kiểm tra ngay" gọi `POST /internal/accounts/{label}/check`; cảnh báo hết phiên, ngắt mạch, mất kết nối. **Vẫn chưa có kiểm tra sức khỏe định kỳ chủ động** (chỉ khi bấm hoặc khi có lượt gọi thật). Xem `docs/BAN_GIAO_TRIEN_KHAI.md` cho bộ triển khai.
4. **MariaDB 10.6 đã chạy thật trong bản diễn tập 20/11** (migrate bằng Laravel, gateway đọc/ghi gồm cột JSON và hạn mức theo ngày, sao lưu/khôi phục); chưa chạy với tải hoặc nhiều ngày. Trước đó (19/11) bộ này đã diễn tập trên PostgreSQL, nay bỏ để thống nhất với SEO Studio.
5. **Việc làm mới token** (ghi ngược vào volume) chỉ được test bằng CLI giả; chưa thấy CLI thật làm mới token trong lúc thử.
6. Prompt của agy đi qua đối số dòng lệnh (giới hạn 100.000 byte, thấy được bằng `ps` trong container).
7. Tài khoản thử: Claude là **tài khoản đang code** của bạn; agy dùng token của tài khoản Google AI Pro **cá nhân** của bạn. Bản sao token nằm ở `~/.probe/smoke/` (quyền 0600). Cần dọn khi xong.
8. **Mọi thứ chạy trên cùng một máy dev**: lớp cách ly mạnh nhất (máy AI tách biệt, tường lửa hai chiều) **chưa dựng và chưa kiểm thử**. Chỉ khi chạy các bước kiểm chứng trong CACH_LY_MAY_CHU.md mục 6 trên staging mới được coi là đạt.
9. Runner khai báo tĩnh trong Compose (mỗi tài khoản một dịch vụ); `gwctl.py new-runner` mới tạo bí mật và in hướng dẫn, chưa tự sinh dịch vụ.

## Chạy lại
```bash
scripts/test_all.sh                      # 285 test + hợp đồng + pip-audit
cp deploy/.env.example deploy/.env       # điền, rồi:
python3 scripts/gwctl.py init ./secrets --pepper-file <tệp chứa APP_KEY của control/>
python3 scripts/gwctl.py new-runner ./secrets claude claude-01   # lặp cho từng tài khoản
docker compose -f deploy/docker-compose.yml up -d
```
