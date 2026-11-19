# Cổng AI — Cấu hình nằm ở đâu, và cần chuẩn bị gì cho server

Ngày soạn: 19/11/2026. Trạng thái: **kế hoạch**. Hiện mới có khung giao diện với dữ liệu mẫu giả; chưa có gateway, runner, proxy hay file Compose, nên **chưa có gì để triển khai lên server**. Tài liệu này là danh sách cần chuẩn bị và bản đồ chỗ cấu hình.

## A. Cấu hình nằm ở đâu

Nguyên tắc: **bí mật và thao tác đụng vào server làm bằng tay ở server, không làm trên web.** Giao diện chỉ quản lý chính sách và trạng thái.

### A1. Việc làm trong giao diện

| Việc cần cấu hình | Chỗ trong giao diện | Trạng thái khung hiện tại |
|---|---|---|
| Thêm ứng dụng, cấp khả năng, pool, hạn mức ban đầu | Ứng dụng → Thêm ứng dụng (wizard 3 bước) | **Có**, đã chạy thử với dữ liệu giả |
| Tạo khóa API mới, thu hồi khóa | Ứng dụng → (một ứng dụng) → tab Khóa API | **Có** |
| Tạm ngưng / bật lại một ứng dụng | Ứng dụng (nút ở danh sách và trang chi tiết) | **Có** |
| Gom tài khoản vào pool, thứ tự ưu tiên, cách chọn | Định tuyến → Thêm pool / Sửa | **Có** |
| Bật/tắt một khả năng toàn hệ thống | Hệ thống → Khả năng | **Có** |
| Tạm dừng / bật lại một tài khoản AI | Tài khoản AI (danh sách và chi tiết) | **Có** |
| Dừng khẩn cấp toàn bộ ứng dụng | Hệ thống → Dừng khẩn cấp | **Có** |
| Xem kiểm toán mọi thao tác | Hệ thống → Kiểm toán | **Có** |
| Sửa hạn mức, model được phép, pool của một ứng dụng đã tạo | Ứng dụng → (một ứng dụng) → Sửa | **Có** (đã xem trên màn hình; chưa thử lưu thay đổi bằng tay) |
| Đăng ký một tài khoản AI mới (nhãn, hãng, gói, số lượt song song tối đa) | Tài khoản AI → Thêm tài khoản | **Có**: form chỉ nhập mô tả, không nhận mật khẩu hay token; tài khoản mới ở trạng thái "Chờ đăng nhập"; địa chỉ runner chỉ nhận tên nội bộ `runner-<nhãn>` (có test chống SSRF) |
| Sửa số lượt song song, gói, ghi chú của tài khoản AI | Tài khoản AI → (một tài khoản) → Sửa | **Có** (nhãn và hãng không đổi được sau khi tạo) |
| Hệ số quy đổi theo model (`model_weights`) | Hệ thống → Hệ số model | **Có** |
| Ngưỡng cảnh báo (80%, 100%, tần suất kiểm tra phiên), giữ dữ liệu | Hệ thống → Cài đặt | **Có** (chỉ lưu cấu hình; chưa có gì dùng các giá trị này vì chưa có gateway) |
| Kênh nhận cảnh báo (email, webhook) | Hệ thống → Cài đặt | **Có chỗ nhập** (email, webhook https; webhook mã hóa khi lưu và không hiện lại). **Chưa có thành phần nào gửi cảnh báo thật** và bạn chưa quyết dùng kênh nào |
| Người dùng quản trị, vai trò | Hệ thống → Người dùng | **Có** (4 vai trò, có test ma trận quyền) |
| 2FA (TOTP) | Menu người dùng → Hồ sơ của tôi | **Có giao diện** (gói `filament-breezy` 2.6.6). **Chưa thử** luồng bật và đăng nhập với mã. Biến `REQUIRE_2FA=true` để bắt buộc |
| Giới hạn truy cập giao diện theo dải mạng | Biến `ADMIN_ALLOWED_CIDRS` (không nằm trên web, có chủ đích) | **Có** (có test; chưa thử sau reverse proxy thật). Ở production mà để trống thì **khóa hết** |
| Danh mục khả năng mới (ví dụ âm thanh) | không có trên giao diện | Có chủ đích: là việc của lập trình viên |

### A2. Việc làm ở server, không làm trên web

| Việc | Cách | Vì sao không đưa lên web |
|---|---|---|
| Đăng nhập tài khoản AI (Claude, Codex, Gemini, Grok) | Người vận hành SSH vào runner của tài khoản đó và chạy lệnh đăng nhập của CLI | Web không được nhận mật khẩu, mã 2FA hay token |
| Thêm runner cho tài khoản mới | Một script của người vận hành tạo dịch vụ và volume riêng cho tài khoản (chưa viết) | Cần quyền Docker, mà gateway **không** được giữ quyền này |
| Bí mật hệ thống (`APP_KEY`, mật khẩu DB/Redis, khóa nội bộ gateway↔runner) | Tệp `.env` hoặc Docker secrets trên server | Không bao giờ vào kho mã hay giao diện |
| Danh sách tên miền được phép ra ngoài | Cấu hình proxy trên server, giá trị lấy từ bước thăm dò (P9, G9) | Đây là lớp chặn rò dữ liệu, không để đổi từ web |
| Phiên bản CLI | Ghim trong Dockerfile của runner | Nâng cấp phải qua kiểm thử bảo mật |

## B. Cần chuẩn bị gì để cấu hình được trên server

Cột "Ai cung cấp": **Bạn** = thông tin hoặc quyền chỉ bạn có; **Tôi** = việc tôi làm trong kho mã; **Chờ thăm dò** = chưa biết trước khi chạy THAM_DO_CLI.

### B1. Máy chủ và mạng

| Hạng mục | Yêu cầu | Trạng thái | Ai |
|---|---|---|---|
| Hệ điều hành và Docker | Linux, Docker Engine và Compose plugin | Đã chốt Docker Compose | Bạn |
| Cấu hình máy | Số nhân CPU, RAM, ổ đĩa (cho volume phiên đăng nhập và Postgres) | **Chưa biết**: RAM mỗi lượt gọi CLI phải đo ở P8/G8 trước | Chờ thăm dò |
| Tên miền hoặc tên nội bộ cho cổng quản trị và cho API | Ví dụ một tên nội bộ cho giao diện, một tên cho API | Chưa chọn | Bạn |
| Chứng chỉ HTTPS | Cho giao diện quản trị (và API nếu đi qua mạng) | Chưa có | Bạn |
| Đường vào giao diện quản trị | Chỉ từ mạng nội bộ hoặc VPN | Đã quyết ở thiết kế | Bạn |
| Đường ra internet cho runner | Chỉ tới tên miền của các hãng qua proxy có danh sách cho phép | Danh sách tên miền **chưa biết** | Chờ thăm dò |
| Đường ra internet khi **dựng image** | Tải CLI từ nguồn của hãng và gói phụ thuộc | Cần mở tạm lúc build | Bạn |
| Giờ hệ thống | Đồng bộ NTP, múi giờ thống nhất (cần cho cooldown, hạn mức theo ngày) | Chưa kiểm tra | Bạn |

### B2. Tài khoản AI (phần chỉ bạn làm được)

Mỗi tài khoản cần: **một nhãn không trùng** (ví dụ `claude-runner-01`), hãng, tên gói, số lượt song song tối đa mong muốn, và **một người có quyền đăng nhập** (có điện thoại/mail nhận mã 2FA) vào lúc đăng nhập.

| Hãng | Cách đăng nhập trên máy không có trình duyệt | Ghi chú |
|---|---|---|
| Claude | Cần xác nhận (CLI mở đường dẫn đăng nhập; chưa biết cách dán mã trên server) | Dùng tài khoản riêng cho chạy bài, không dùng tài khoản code (đã ghi nhận trước đây) |
| Codex | Cần xác nhận | Hiện bạn **không** có tài khoản Codex |
| Gemini | Cần xác nhận | |
| Grok | `grok login --device-auth` (theo `--help`: đăng nhập bằng mã thiết bị cho môi trường không có trình duyệt) | Tài liệu của CLI ghi token hết hạn sau 7 ngày; **chưa biết** có tự làm mới không. Nếu không, phải đăng nhập lại mỗi tuần |

Chưa biết tần suất hết phiên của từng hãng; kết quả P10/G10 và theo dõi vài ngày sẽ trả lời. Cần quyết ai nhận cảnh báo "hết phiên" và ai làm đăng nhập lại.

### B3. Bí mật hệ thống (tôi sinh mẫu, bạn giữ giá trị thật)

| Bí mật | Dùng làm gì | Lưu ở đâu |
|---|---|---|
| `APP_KEY` của Laravel | Mã hóa phiên, và hiện cũng làm "tiêu" băm khóa API của ứng dụng | `.env` trên server |
| Mật khẩu PostgreSQL, Redis | Kết nối dữ liệu | `.env` / Docker secrets |
| Khóa nội bộ gateway ↔ runner | Runner chỉ nhận lệnh từ gateway | Docker secrets |
| Vai trò DB riêng cho gateway | Chỉ đọc bảng chính sách, chỉ ghi `usage_events` | Postgres |
| Khóa mã hóa bản sao lưu | Mã hóa sao lưu volume phiên đăng nhập | Ngoài server (người giữ) |
| Tài khoản quản trị đầu tiên | Đăng nhập giao diện, bật 2FA | Bạn đặt khi cài |

Không thứ nào ở trên được vào kho mã hay giao diện web.

### B4. Thông tin về ứng dụng và vận hành

| Hạng mục | Cần để | Trạng thái |
|---|---|---|
| Danh sách ứng dụng sẽ dùng, bộ phận phụ trách | Tạo trong wizard | Bạn: hiện biết SEO Studio; các app khác chưa nêu |
| Khả năng mỗi app cần (văn bản, ảnh vào/ra, tìm web) | Cấp quyền và chọn runner | Bạn |
| Khối lượng dự kiến (yêu cầu/giờ, giờ cao điểm) | Đặt hạn mức và số tài khoản trong pool | Bạn (ước lượng được) |
| Cách mỗi app gọi: đổi `base_url` và khóa | SEO Studio: màn "Đội AI → Tích hợp AI" và `AI_PRIVATE_BASE_URL_ALLOWLIST` | Cần thêm địa chỉ gateway vào danh sách cho phép |
| Kênh nhận cảnh báo | Email, Telegram, Slack… | **Chưa quyết**; giao diện chưa có chỗ cấu hình |
| Thời gian giữ nhật ký số liệu và nhật ký kiểm toán | Dọn dữ liệu | Chưa quyết |
| Nơi lưu và lịch sao lưu | Phục hồi sau sự cố | Chưa quyết |

### B5. Thứ tôi phải viết trước khi cấu hình được gì trên server

1. Image runner (CLI ghim phiên bản, user không đặc quyền, hệ tệp chỉ đọc, giới hạn tài nguyên).
2. Gateway (FastAPI), proxy chuyển tiếp có danh sách tên miền, và file Docker Compose.
3. Script "thêm tài khoản" của người vận hành.
4. ~~Form "Thêm tài khoản" và các màn hình còn thiếu~~ **Đã làm** (xem bảng A1).
5. ~~Phân vai, 2FA, giới hạn theo mạng~~ **Đã làm ở mức khung**; còn thử luồng 2FA thật và thử sau reverse proxy.

Cho tới khi có các mục trên, "cấu hình trên server" chưa làm được; bạn chỉ có thể chuẩn bị các mục B1–B4.

## C. Trình tự đề xuất

1. Chạy thăm dò CLI (cả Grok) để biết tên miền, hạn mức, hành vi đăng nhập, RAM. Kết quả điền vào B1 và B2.
2. Bạn chốt B1 (máy, tên miền, HTTPS, VPN) và B4 (kênh cảnh báo, giữ dữ liệu, sao lưu).
3. Tôi viết B5 theo kế hoạch Giai đoạn 1 trở đi.
4. Dựng thử trên **staging** trước, đăng nhập một tài khoản thử, rồi mới nối tài khoản thật.
