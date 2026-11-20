# Nối SEO Studio với Cổng AI và sửa lỗi "HTTP 404 / Lỗi kết nối"

Dành cho: **quản lý hạ tầng** (xác định địa chỉ, tường lửa, allowlist) và người cấu hình SEO Studio.
Soạn ngày 20/11/2026. Bổ sung cho `docs/BAN_GIAO_TRIEN_KHAI.md` mục 4.

## 1. Triệu chứng và nguyên nhân

SEO Studio, nhà cung cấp "OpenAI-tương thích" trỏ tới Cổng AI báo **"Lỗi kết nối"** và **"Nhà cung cấp AI từ chối yêu cầu (HTTP 404)"**; thông báo cập nhật model ghi "hãng/cầu nối này không có API danh sách model".

**Nguyên nhân thường gặp nhất: dán nhầm địa chỉ trang quản trị làm địa chỉ API.** Cổng AI có **hai địa chỉ khác nhau**:

| | Giao diện quản trị | API cho ứng dụng |
|---|---|---|
| Ai dùng | Người quản trị, bằng trình duyệt | SEO Studio và ứng dụng khác, bằng khóa `gw_…` |
| Ví dụ | `https://aigw.addp.vn/ung-dung/1` | `http://<IP-máy-AI>:8771/v1` |
| Cổng | `WEB_PORT` (mặc định 8080) | `GATEWAY_PORT` (mặc định 8771) |
| Có `/v1/models`, `/healthz`? | **Không** (trả 404 "Không tìm thấy trang") | **Có** |

Địa chỉ trên thanh trình duyệt khi đang xem giao diện **không phải** địa chỉ API. Khi SEO Studio lấy danh sách model nó gọi `<địa chỉ>/models`; nếu địa chỉ là trang giao diện thì ra 404.

## 2. Quản lý làm trên máy AI

**2.1 Xác định địa chỉ API:**
```bash
cd /opt/aigw/ai-gateway
grep -E '^(GATEWAY_BIND|GATEWAY_PORT|WEB_MODE)=' deploy/.env
```
Địa chỉ API = `http://<GATEWAY_BIND>:<GATEWAY_PORT>/v1` (dùng `https://` nếu `WEB_MODE=tls`).

- `GATEWAY_BIND=127.0.0.1` (mặc định) nghĩa là **chỉ chính máy AI gọi được**. Server SEO Studio ở máy khác sẽ không tới được. Phải đặt **IP nội bộ của máy AI** (ví dụ `10.0.5.20`), rồi `scripts/aigw up`.
- Nếu công ty có reverse proxy/tên miền riêng cho API, dùng địa chỉ đó, miễn là chuyển tiếp **chỉ** `/v1/*` và `/healthz` tới `<GATEWAY_BIND>:<GATEWAY_PORT>`.

**2.2 Kiểm tra từ chính máy AI:**
```bash
curl -s http://<GATEWAY_BIND>:<GATEWAY_PORT>/healthz          # phải ra {"ok":true,"version":"0.2.0"}
curl -s -o /dev/null -w '%{http_code}\n' http://<GATEWAY_BIND>:<GATEWAY_PORT>/internal/state   # phải ra 404 (không lộ ra ngoài)
```

**2.3 Tường lửa:** server SEO Studio phải tới được `<GATEWAY_BIND>:<GATEWAY_PORT>` (chỉ IP đó, theo mẫu `deploy/firewall/docker-user.sh.example`). Kiểm tra **từ server SEO Studio** (không phải từ máy AI):
```bash
curl -s -m 5 http://<IP-máy-AI>:<GATEWAY_PORT>/healthz
```
Ra `{"ok":true,...}` là thông. Treo hoặc "connection refused" là tường lửa hoặc `GATEWAY_BIND` sai.

## 3. Cấu hình phía SEO Studio

1. **Cho phép địa chỉ nội bộ:** thêm `<IP-máy-AI>:<GATEWAY_PORT>` vào `AI_PRIVATE_BASE_URL_ALLOWLIST` trong môi trường container SEO Studio, ví dụ `AI_PRIVATE_BASE_URL_ALLOWLIST=10.0.5.20:8771`. Biến môi trường chỉ được nhận khi **tạo lại container**. Không có bước này SEO Studio chặn mọi địa chỉ IP nội bộ (và báo "chưa được phép"). Nếu dùng tên miền công khai có TLS thì không cần.
2. **Sửa nhà cung cấp** (Đội AI → Tích hợp AI, hoặc `claude-ai-gw`): loại "OpenAI-tương thích", địa chỉ `http://<IP-máy-AI>:<GATEWAY_PORT>/v1` (có `/v1`, **không** có `/ung-dung/1`).
3. **Khóa API:** dùng khóa `gw_…` của ứng dụng (ví dụ AUTO SEO) tạo trên giao diện Cổng AI → Ứng dụng → Khóa API. Khóa **chỉ hiện đầy đủ một lần** lúc tạo; trang chi tiết chỉ hiện tiền tố. Mất thì tạo khóa mới rồi **Thu hồi** khóa cũ. Không dán khóa vào chat/email/lịch sử shell.
4. **Model:** pool của ứng dụng chỉ có Claude thì dùng tên không tiền tố (`haiku`, `sonnet`). Pool nhiều hãng thì dùng `claude:haiku`, `codex:gpt-6-luna`...
5. Bấm **Kiểm tra kết nối**.

Lưu ý: `GET /v1/models` của Cổng AI trả mẫu dạng `claude:*` (không liệt kê từng model). SEO Studio có thể hiện một mục lạ hoặc báo không cập nhật được danh sách; hãy giữ model nhập tay (`haiku`...). *Chưa kiểm chứng* cách SEO Studio xử lý mẫu này khi địa chỉ đúng.

## 4. Thử bằng tay (không qua SEO Studio)

Chạy từ server SEO Studio. Nhập khóa bằng `read -s` để không lưu vào lịch sử:
```bash
read -s -p "Khóa gw_: " K; echo
curl -s -m 60 -H "Authorization: Bearer $K" -H 'Content-Type: application/json' \
  -d '{"model":"haiku","messages":[{"role":"user","content":"xin chào"}]}' \
  http://<IP-máy-AI>:<GATEWAY_PORT>/v1/chat/completions
```
Thành công: JSON có `choices[0].message.content`, và trên giao diện Cổng AI → Nhật ký có một dòng của ứng dụng đó.

## 5. Bảng lỗi

| Hiện tượng | Nguyên nhân | Cách xử lý |
|---|---|---|
| `HTTP 404` / HTML "Không tìm thấy trang - Cổng AI" | Địa chỉ trỏ vào giao diện | Dùng địa chỉ API (mục 1, 2.1) |
| Treo, `connection refused`, timeout | `GATEWAY_BIND=127.0.0.1`, tường lửa chặn, sai cổng | Mục 2.1 và 2.3 |
| SEO Studio báo địa chỉ "chưa được phép" | Chưa có trong `AI_PRIVATE_BASE_URL_ALLOWLIST` hoặc chưa tạo lại container | Mục 3.1 |
| `401 invalid_api_key` | Khóa sai, đã thu hồi, hết hạn | Tạo khóa mới (mục 3.3) |
| `403 model_not_allowed` | Ứng dụng không được dùng model đó | Giao diện → Ứng dụng → Sửa: model được phép |
| `403 app_suspended` | Ứng dụng đang tạm ngưng | Bật lại ở trang ứng dụng |
| `429 app_rpm` / `app_daily_units` | Chạm hạn mức của ứng dụng | Xem/nới hạn mức ở trang ứng dụng |
| `503 no_account_available` | Tài khoản AI "Hết phiên"/"Đang nghỉ", hoặc pool rỗng | Bấm "Kiểm tra ngay"; đăng nhập lại nếu hết phiên (`scripts/aigw login`) |
| `400 model_requires_provider` | Pool nhiều hãng mà dùng tên không tiền tố | Dùng `claude:haiku`... |

## 6. Điều chưa kiểm chứng

Tài liệu soạn từ mã và bản diễn tập; **tôi không thấy cấu hình thật trên máy của quản lý** nên địa chỉ cụ thể (`IP`, cổng, tên miền) phải do quản lý xác định. Các lệnh `curl` ở mục 2 và 4 đã chạy đúng ở bản diễn tập trên máy dev. Chưa chạy trên đúng môi trường `aigw.addp.vn`.
