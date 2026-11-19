# Cổng AI — Kế hoạch build

Trạng thái: **kế hoạch, chưa bắt đầu giai đoạn nào**. Ngày soạn: 19/11/2026.
Đọc cùng [THIET_KE.md](THIET_KE.md) và [GIAO_DIEN.md](GIAO_DIEN.md).

## 1. Quy ước làm việc

- **Mỗi mục "xong" phải nêu rõ đang ở mức nào** trong bốn mức của AGENTS.md: `Đã viết code` → `Test giả lập đạt` → `Tích hợp staging đạt` → `Người thật nghiệm thu`. Thiếu môi trường hoặc tài khoản thì ghi `Chưa chạy` / `Bị chặn tích hợp`, không điền giả.
- **Không push, không mở PR, không xóa nhánh nếu chưa được bạn duyệt** cho từng đợt. Mọi thay đổi để ở nhánh cục bộ cho đến khi bạn đồng ý.
- **Không đưa bí mật vào repo**: không `.env`, không khóa, không thư mục phiên đăng nhập, không dump CSDL.
- Phạm vi người làm: bạn làm hết hạ tầng và giao diện (đã ghi nhận trước đây), nên không chia theo Dev1/Dev2 và không soạn PR cho Dev1.
- Kích cỡ việc: **S** ≈ vài giờ, **M** ≈ 1–2 ngày, **L** ≈ 3–5 ngày. Đây là ước lượng thô, sẽ chỉnh sau Giai đoạn 0 khi có dữ liệu thật.

## 2. Việc cần bạn quyết định trước khi code

| # | Câu hỏi | Gợi ý | Ảnh hưởng |
|---|---|---|---|
| Q1 | Đặt dự án ở đâu? | **Đã chốt (19/11): repo riêng `~/addp-autoseo/ai-gateway`**, đã `git init` | Cấu trúc thư mục, CI |
| Q2 | Server chạy Docker Compose hay systemd? | **Đã chốt (19/11): Docker Compose** | Giai đoạn 1 |
| Q3 | Quy mô thật: mấy ứng dụng, mỗi hãng mấy tài khoản, tải đỉnh? | — | Cỡ pool, RAM server |
| Q4 | Cho phép tôi/bạn chạy lệnh thăm dò trên server bằng tài khoản thật không? | Danh sách lệnh đã soạn ở [THAM_DO_CLI.md](THAM_DO_CLI.md), **chờ bạn duyệt**; chỉ dùng tài khoản thử, bạn tự chạy hoặc duyệt từng lệnh | Giai đoạn 0 |
| Q5 | Chấp nhận mô hình hai phần (gateway Python + điều khiển Laravel)? | Có (lý do ở THIET_KE mục 3) | Toàn bộ |
| Q6 | Có thử LiteLLM trước khi tự viết lớp khóa/ngân sách/nhật ký không? | Thử 1–2 ngày ở Giai đoạn 0, quyết định bằng bằng chứng | Giai đoạn 1–2 |

## 3. Các giai đoạn

### Giai đoạn 0 — Thăm dò và chốt kỹ thuật (M–L)
Mục đích: biến G1–G7 trong THIET_KE mục 2 từ giả định thành sự thật hoặc phương án thay thế.

| Việc | Đầu ra |
|---|---|
| 0.1 Với mỗi CLI (Claude, Codex, Gemini) trên **tài khoản thử**: kết quả JSON có `usage` không? | Bảng ghi mẫu đầu ra (đã che nội dung) và kết luận G1 |
| 0.2 Đọc % hạn mức/phiên bằng cách không tương tác được không? Tốn hạn mức không? | Kết luận G2, cách đọc, tần suất an toàn |
| 0.3 Cố ý chạm giới hạn (hoặc mô phỏng bằng dữ liệu hãng đã công bố) để xem thông báo và giờ reset | Mẫu thông báo → bộ nhận dạng lỗi (G3) |
| 0.4 Nhận ảnh làm đầu vào: có cách nào không cần bật công cụ đọc tệp? Sinh ảnh có không? | G4, G5 |
| 0.5 Hai tiến trình cùng thư mục cấu hình có hỏng phiên đăng nhập không? | G6 → quyết định khóa theo tài khoản |
| 0.6 Cờ chế độ chỉ-trò-chuyện/sandbox chỉ đọc của Codex/Gemini; danh sách tên miền hãng cần cho phép qua proxy | G7, allowlist chính xác |
| 0.7 Thử nhanh LiteLLM làm lớp khóa/ngân sách phía trước các cầu nối hiện có | Quyết định tự viết hay dùng |
| 0.8 Đo RAM/độ trễ mỗi lượt gọi CLI | Giới hạn song song thực tế |
| 0.9 Cập nhật THIET_KE theo kết quả; ghi rõ khả năng nào **không hỗ trợ được** | Bản thiết kế v1.1 |

**Cổng ra:** mỗi giả định có kết luận "đúng/sai + bằng chứng". Chưa qua cổng thì không bắt đầu Giai đoạn 2 trở đi (Giai đoạn 1 và UI có thể chạy song song ở phần không phụ thuộc).

### Giai đoạn 1 — Nền tảng và lõi gateway (L)

| Việc | Cỡ |
|---|---|
| 1.1 Khung repo, Compose, MariaDB, Redis, CI chạy test | M |
| 1.2 Migration theo THIET_KE mục 4 (Laravel sở hữu); vai trò DB riêng cho gateway (đọc chính sách, chỉ INSERT usage) | M |
| 1.3 Image runner: user không đặc quyền, hệ tệp chỉ đọc, `no-new-privileges`, bỏ capabilities, giới hạn tài nguyên, CLI ghim phiên bản | M |
| 1.4 Proxy chuyển tiếp có allowlist; mạng nội bộ không có đường ra trực tiếp | M |
| 1.5 Gateway FastAPI: xác thực khóa (HMAC), nạp chính sách + cache, `/v1/chat/completions` (không stream), `/v1/models`, `/healthz`, `/metrics` | L |
| 1.6 Adapter Claude (chuyển từ `claude_bridge.py`), runner HTTP nội bộ | M |
| 1.7 Ghi `usage_events` (không ghi nội dung) | S |
| 1.8 Test: đơn vị + hợp đồng (CLI giả), kiểm tra không rò nội dung vào log | M |

**Cổng ra:** SEO Studio trên staging gọi được Claude qua gateway bằng khóa riêng; mọi lượt ghi đúng app và tài khoản. Mức đạt: `Tích hợp staging`.

### Giai đoạn 2 — Nhiều tài khoản, định tuyến, hạn mức (L)

| Việc | Cỡ |
|---|---|
| 2.1 Pool, chiến lược chọn, slot song song (khóa Redis có hạn) | M |
| 2.2 Cooldown khi chạm giới hạn (L4); thử lại một lần trước khi có đầu ra; phát hiện hết phiên | M |
| 2.3 Hạn mức theo app: rpm, song song, đơn vị/ngày, đơn vị/tháng; mềm/cứng; mã lỗi và `Retry-After` | M |
| 2.4 Đo lường L1 (đơn vị quy đổi + `model_weights`) | M |
| 2.5 Đo lường L2/L3 (lấy mẫu %, chia tỉ lệ, hiệu chỉnh) — **chỉ làm theo kết luận G2** | M–L |
| 2.6 Adapter Codex, Gemini (chuyển `codex_bridge.py`) | M mỗi cái |
| 2.7 Streaming (SSE) | M |
| 2.8 Rollup theo giờ/ngày, kiểm tra hiệu năng truy vấn | S |
| 2.9 Test: mô phỏng 429, hết phiên, chạy song song, retry; test tải nhẹ | M |

**Cổng ra:** kịch bản "tài khoản A chạm giới hạn → chuyển sang B → app không biết" chạy đúng trên staging, có log chứng minh.

### Giai đoạn 3 — Giao diện điều khiển (L) — chạy song song với GĐ2 sau khi chốt schema ở GĐ1

| Việc | Cỡ |
|---|---|
| 3.1 Panel Filament, theme xanh dương, font tự lưu, token, chế độ sáng/tối (GIAO_DIEN mục 0, 2, 8) | M |
| 3.2 Linh kiện tùy biến: dải trạng thái, vòng đo, dải hạn mức, chip trạng thái | M |
| 3.3 Resource: Tài khoản AI, Ứng dụng (kèm Khóa, Khả năng, Hạn mức), Định tuyến | L |
| 3.4 Wizard Thêm ứng dụng; trang hướng dẫn đăng nhập lại tài khoản | M |
| 3.5 Trang Tổng quan (widget) | M |
| 3.6 Nhật ký, Cảnh báo, Hệ thống (danh mục khả năng, hệ số model, người dùng, kiểm toán, dừng khẩn cấp) | L |
| 3.7 Vai trò: admin, operator, viewer, app_owner; 2FA; chỉ truy cập từ mạng nội bộ/VPN | M |
| 3.8 Kiểm chứng giao diện theo GIAO_DIEN mục 9 (chụp, nhìn, đo, chấm, tối đa 2 vòng) | M |
| 3.9 Buổi dùng thử với người vận hành thật, ghi số đo | S |

**Cổng ra:** quản trị làm được các việc chính ở GIAO_DIEN mục 1 với số đo thật; bảng điểm thiết kế kèm bằng chứng; không lỗi console; AA tương phản đo được ở cả hai chế độ.

### Giai đoạn 4 — Khả năng ảnh và tìm web (M–L) — chỉ làm phần mà GĐ0 xác nhận khả thi

| Việc | Cỡ |
|---|---|
| 4.1 Khung danh mục khả năng (bảng `capabilities`, bộ xử lý gắn theo khả năng) | M |
| 4.2 `image_in`: kiểm tra loại bằng nội dung, cấm SVG, giới hạn dung lượng/điểm ảnh, mã hóa lại, xóa EXIF, xóa tệp tạm ngay | M |
| 4.3 `image_out`: kiểm tra và mã hóa lại đầu ra, không lưu lâu dài | M |
| 4.4 `web_search` ở `runner-search` riêng, chặn dải IP nội bộ/loopback/metadata | M |
| 4.5 Đo riêng theo khả năng; hạn mức riêng cho ảnh | S |
| 4.6 Test bảo mật riêng cho ảnh và tìm web (ảnh có chữ chứa lệnh, ảnh lớn, SVG, URL nội bộ) | M |

### Giai đoạn 5 — Hardening, vận hành, nghiệm thu (M–L)

| Việc | Cỡ |
|---|---|
| 5.1 Bộ test bảo mật theo THIET_KE 7.4, **chạy thật trên staging**, lưu log làm bằng chứng | M |
| 5.2 Cảnh báo (hết phiên, cooldown, vượt 80/100% hạn mức, lỗi 5xx, runner chết, kết nối bị proxy chặn) | M |
| 5.3 Sao lưu/khôi phục MariaDB và volume phiên đăng nhập (có mã hóa); **diễn tập khôi phục** | M |
| 5.4 Runbook: thêm tài khoản, đăng nhập lại, nâng cấp CLI, xoay khóa, xử lý sự cố, dừng khẩn cấp | M |
| 5.5 Rà soát bảo mật độc lập (code review + thử xâm nhập nội bộ trong phạm vi staging) | M |
| 5.6 Thử tải thực tế; ghi giới hạn thật của một máy | S |

**Cổng ra:** mọi mục THIET_KE mục 7 có bằng chứng chạy thật; diễn tập khôi phục thành công.

### Giai đoạn 6 — Chuyển ứng dụng sang gateway (M)

| Việc | Cỡ |
|---|---|
| 6.1 Đăng ký SEO Studio, cấp khả năng tối thiểu, đặt hạn mức theo số liệu thật của vài ngày chạy | S |
| 6.2 Đổi `base_url` và khóa trong "Đội AI → Tích hợp AI"; cập nhật `AI_PRIVATE_BASE_URL_ALLOWLIST` | S |
| 6.3 Chạy song song một tuần: gateway và cầu nối cũ; so sánh kết quả, tỉ lệ lỗi, độ trễ | M |
| 6.4 Tắt hai cầu nối cũ khi đạt tiêu chí; giữ đường lui trong 1–2 tuần | S |
| 6.5 Người thật nghiệm thu | S |

## 4. Phụ thuộc và thứ tự

```
GĐ0 ──► GĐ1 ──► GĐ2 ──► GĐ4 ──► GĐ5 ──► GĐ6
          └────► GĐ3 (song song với GĐ2, sau khi chốt schema) ─────────┘
```
Một mình bạn làm thì GĐ2 và GĐ3 nên làm tuần tự (xen kẽ theo màn hình), không nên chạy hai luồng cùng lúc để khỏi đụng schema.

## 5. Tiêu chí nghiệm thu tổng

1. Một app mới đăng ký, cấp khả năng và hạn mức, gọi API thành công, tất cả trong < 3 phút bằng giao diện.
2. Nhật ký chỉ rõ **tài khoản nào** xử lý **yêu cầu của app nào**, không nhầm.
3. Khi một tài khoản chạm giới hạn, yêu cầu tự chuyển sang tài khoản khác trong pool hoặc trả lỗi rõ ràng; không có yêu cầu nào bị mất mà không ghi.
4. Vượt hạn mức app → bị chặn đúng mã lý do; cảnh báo 80% hoạt động.
5. Các bài test bảo mật (THIET_KE 7.4) đạt trên staging, có log kèm theo.
6. Runner không đọc được tệp ngoài volume của nó, không ra được mạng nội bộ hay metadata, không có cờ nguy hiểm.
7. Giao diện đạt ngưỡng chất lượng sàn (GIAO_DIEN mục 7) ở cả sáng và tối, đo thật.
8. Khôi phục từ sao lưu thành công trong diễn tập.
9. Người vận hành thật nghiệm thu (mức thứ tư của AGENTS.md).

## 6. Rủi ro và cách giảm

| Rủi ro | Mức | Cách giảm |
|---|---|---|
| CLI không xuất `usage`/% (G1, G2 sai) | Cao | Thiết kế đã có phương án ước lượng và chỉ dựa vào L4; giao diện ghi rõ nguồn con số |
| CLI đổi định dạng sau cập nhật | Trung bình | Ghim phiên bản; test thăm dò chạy định kỳ; chỉ nâng cấp có chủ đích |
| Phiên đăng nhập hết hạn âm thầm | Cao | Kiểm tra sức khỏe định kỳ, cảnh báo, hướng dẫn đăng nhập lại trong giao diện |
| RAM server không đủ cho nhiều tiến trình CLI | Trung bình | Đo ở GĐ0 (0.8); giới hạn song song theo thực đo |
| Làm quá tay (nhiều tính năng chưa cần) | Trung bình | Chỉ làm GĐ2.5 (đo %), GĐ4 theo kết quả GĐ0 và nhu cầu thật |
| Lộ bí mật phiên đăng nhập | Cao | Volume riêng 0600, sao lưu mã hóa, không gắn vào container khác, kiểm toán truy cập |
| Giao diện đẹp trên giấy nhưng khó dùng thật | Trung bình | Buổi dùng thử (3.9) trước khi nghiệm thu |
| Phụ thuộc hai ngôn ngữ (Python + PHP) | Thấp–TB | Hợp đồng DB rõ, test hợp đồng, runbook; giữ phần Python nhỏ |

## 7. Việc chưa làm và chưa biết (nói thẳng)

- Chưa chạy bất kỳ lệnh thăm dò nào; mọi kết luận về CLI (usage, %, cờ, ảnh) hiện chỉ là giả định.
- Chưa kiểm chứng LiteLLM đáp ứng được những gì; chưa nên coi là đã chọn hay đã loại.
- Chưa dựng giao diện nào; mọi mô tả hình thức chưa được nhìn thấy trên màn hình thật.
- Chưa có số đo tải, RAM, độ trễ.
- Công cụ kiểm tra thiết kế của skill (`/root/ai-design-studio`) hiện không truy cập được từ phiên này.

## 8. Bước tiếp theo đề xuất

1. Bạn trả lời Q1–Q6 (ít nhất Q1, Q2, Q4).
2. Tôi soạn danh sách lệnh thăm dò cụ thể cho Giai đoạn 0 để bạn xem và duyệt trước khi chạy; tôi không tự chạy lệnh dùng tài khoản thật khi chưa được bạn đồng ý.
3. Song song, có thể bắt đầu dựng khung giao diện (GĐ3.1–3.2) với dữ liệu mẫu giả vì không phụ thuộc kết quả thăm dò.
