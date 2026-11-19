# Cổng AI — Thiết kế giao diện (UX + định hướng nghệ thuật)

Trạng thái: **bản thiết kế, chưa dựng và chưa nhìn thấy giao diện thật**. Mọi nhận xét về hình thức dưới đây là ý định thiết kế, chỉ được coi là đạt sau khi dựng, chụp màn hình thật và đo (xem mục 9).
Tài liệu liên quan: [THIET_KE.md](THIET_KE.md), [KE_HOACH_BUILD.md](KE_HOACH_BUILD.md).

## 0. Nguyên tắc dựng: dùng thư viện chuyên nghiệp, chỉ tùy biến thương hiệu

Theo yêu cầu: **không tự vẽ lại những thứ đã có thư viện làm tốt**.

| Cần | Dùng sẵn | Tự làm (chỉ phần này) |
|---|---|---|
| Khung trang, đăng nhập, điều hướng, phân quyền | Filament 3 (panel, `topNavigation`, user menu, 2FA) | Theme, logo, thứ tự menu |
| Bảng dữ liệu, lọc, sắp xếp, tìm kiếm, phân trang, hành động hàng loạt | Filament Tables | Cột tùy biến (thanh hạn mức, chip trạng thái) |
| Form, wizard thêm app/tài khoản, validation | Filament Forms + Wizard | Văn bản hướng dẫn |
| Trang chi tiết | Filament Infolists | — |
| Thông báo, hộp thoại xác nhận, modal | Filament Notifications, Actions, Modals | Nội dung chữ |
| Biểu đồ đường/cột/vòng | Filament Chart widgets (Chart.js) | Bảng màu, kiểu đường |
| Biểu tượng | Heroicons (đi kèm Filament) | — |
| Tương tác nhẹ | Livewire + Alpine (đi kèm Filament) | — |
| Kiểu dáng | Tailwind CSS qua theme tùy biến của Filament | Token màu, font, bo góc, khoảng cách |

Phần tự làm được giữ ở mức tối thiểu và đều xây trên linh kiện Filament: **Dải trạng thái toàn hệ thống**, **Vòng đo (gauge)**, **Dải hạn mức (quota ribbon)**, **Chip trạng thái**. Không thêm thư viện UI thứ hai trừ khi Filament không đáp ứng được (phải ghi lý do vào PR).

Tùy biến theo thứ tự ưu tiên: (1) cấu hình panel và token màu; (2) theme CSS; (3) component Blade mỏng dựa trên linh kiện Filament; (4) chỉ khi bắt buộc mới viết HTML thủ công.

Khác SEO Studio ở đâu: SEO Studio đang dùng màu teal "Editorial Ledger" với menu dọc theo nhóm. Cổng AI dùng **menu ngang trên cùng**, **xanh dương**, **font khác**, **mật độ dữ liệu cao kiểu bảng điều khiển vận hành**. Cùng bộ công nghệ nên đội không phải học thêm gì, nhưng nhìn và cảm giác dùng là một sản phẩm khác.

## 1. Kế hoạch UX

| Hạng mục | Nội dung |
|---|---|
| Chủ đề | Công cụ quản lý các gói AI dùng chung cho ứng dụng nội bộ |
| Người dùng chính | Quản trị/vận hành (1–3 người, kỹ thuật, dùng hằng ngày, chủ yếu màn hình máy tính) |
| Người dùng phụ | Chủ ứng dụng (chỉ xem mức dùng và khóa của app mình); người xem |
| Việc chính cần làm nhanh | (1) Biết **ngay** hệ thống có ổn không; (2) thấy tài khoản nào sắp đầy/đã hỏng; (3) biết app nào đang dùng nhiều; (4) thêm app/tài khoản đúng và an toàn; (5) tìm nguyên nhân khi có lỗi/429 |
| Mục tiêu nghiệp vụ | Không gián đoạn việc chạy bài; không nhầm tài khoản; kiểm soát mức dùng; không rò bí mật |
| Tín hiệu thành công | Quản trị trả lời được "hệ thống có ổn không?" trong < 5 giây từ trang đầu; thêm một app mới trong < 3 phút; xác định được nguyên nhân một lỗi 429 trong < 1 phút |
| Giả định (chưa kiểm chứng với người dùng) | Người dùng là người rành kỹ thuật; dùng tiếng Việt; không cần dùng trên điện thoại hằng ngày nhưng phải xem được trạng thái và bấm tạm dừng khi khẩn cấp |

Không bịa nghiên cứu người dùng: các giả định trên được xác nhận bằng một buổi dùng thử ngắn ở Giai đoạn UI (xem kế hoạch).

### Cấu trúc thông tin (menu ngang)

```
Tổng quan | Tài khoản AI | Ứng dụng | Định tuyến | Nhật ký | Cảnh báo | Hệ thống
```

| Mục | Nội dung |
|---|---|
| **Tổng quan** | Dải trạng thái; lưu lượng; ứng dụng dùng nhiều; tài khoản cần chú ý; sự cố gần đây |
| **Tài khoản AI** | Danh sách (nhóm theo hãng) và trang chi tiết từng tài khoản |
| **Ứng dụng** | Danh sách và chi tiết app: khóa, khả năng, hạn mức, mức dùng |
| **Định tuyến** | Pool, thứ tự ưu tiên, ghim tài khoản, tên model ảo |
| **Nhật ký** | Nhật ký yêu cầu (số liệu, không nội dung), lọc nhiều chiều |
| **Cảnh báo** | Cảnh báo đang mở, sự cố đã đóng, quy tắc |
| **Hệ thống** | Danh mục khả năng, hệ số model, người dùng, kiểm toán, cài đặt, dừng khẩn cấp |

Điều hướng sâu nhất 2 cấp. Tìm kiếm toàn cục bằng `Ctrl+K` (có sẵn trong Filament): gõ tên app/tài khoản để nhảy tới.

## 2. Định hướng nghệ thuật: "Bảng tín hiệu"

**Khái niệm một câu:** phòng điều khiển yên tĩnh. Nền sáng mát, nhiều chỗ thở, số liệu rõ như đồng hồ đo; màu xanh dương chỉ dùng cho hành động và trọng tâm, còn trạng thái dùng bộ màu riêng (xanh lá, hổ phách, đỏ) kèm biểu tượng, không dựa vào mỗi màu.

**Điều khiến nó đúng với bài toán này (không phải mẫu chung):**
- Dải trạng thái cố định ngay dưới menu, hiện tóm tắt sống của toàn hệ thống ở mọi trang: `● 5 tài khoản sống · 1 đang nghỉ · 0 hết phiên · 3 ứng dụng hoạt động`. Nhìn là biết.
- Mỗi tài khoản có **nhãn và biểu tượng hãng cố định** hiển thị giống hệt ở mọi nơi (danh sách, nhật ký, biểu đồ), nhằm trực tiếp giải quyết yêu cầu "phân biệt rõ để đỡ nhầm".
- **Dải hạn mức**: một thanh ngang chia đoạn theo ứng dụng, cho thấy ai đang dùng bao nhiêu của một tài khoản.

### Bảng màu (token gốc; chốt sau khi đo)

Tên và giá trị là đề xuất. Giá trị tương phản ghi dưới đây là **tính toán sơ bộ của tôi**, phải được xác nhận bằng công cụ đo trước khi chốt.

| Vai trò | Sáng | Tối | Ghi chú |
|---|---|---|---|
| Xanh chủ đạo 600 (nút, liên kết, trọng tâm) | `#1E5BC6` | `#6EA2FF` | Chữ trắng trên `#1E5BC6` ≈ 6,2:1 (tính sơ bộ) |
| Xanh đậm 700 (hover, nhấn) | `#1749A0` | `#9BBEFF` | |
| Xanh nhạt 50 / 100 (nền chọn, chip) | `#EEF4FF` / `#DCE8FF` | `#12264D` / `#1A3366` | |
| Xanh điểm nhấn phụ (biểu đồ thứ hai) | `#0C7692` | `#4CC3E0` | Cyan, để phân biệt với chủ đạo |
| Chữ chính (mực xanh navy) | `#0B1B33` | `#E8EEF8` | |
| Chữ phụ | `#4A5A73` | `#9FB0C9` | Phải đạt AA trên nền tương ứng |
| Nền trang | `#F4F7FB` | `#0A1220` | |
| Nền thẻ | `#FFFFFF` | `#101A2E` | |
| Viền/đường kẻ | `#D9E2EF` | `#233250` | |
| Trạng thái: ổn | `#17804F` | `#4FD08A` | + biểu tượng dấu tích |
| Trạng thái: chú ý | `#A8650F` | `#F0B35A` | + biểu tượng tam giác |
| Trạng thái: lỗi | `#C0392B` | `#FF7B6E` | + biểu tượng X |
| Trạng thái: trung tính/tạm dừng | `#5B6B84` | `#8FA1BD` | + biểu tượng tạm dừng |

Vì xanh dương là màu chủ đạo, **trạng thái "ổn" dùng xanh lá, không dùng xanh dương**, để không lẫn giữa "hành động/liên kết" và "tình trạng".

Màu phân biệt hãng (chỉ dùng cho vòng nhỏ quanh biểu tượng và đường trên biểu đồ, không dùng cho chữ): Claude / Codex / Gemini mỗi hãng một sắc riêng nhưng cùng độ bão hòa, và luôn đi cùng biểu tượng + chữ nhãn.

### Chữ
- **Be Vietnam Pro** cho giao diện và tiêu đề (thiết kế cho tiếng Việt, dấu rõ). **IBM Plex Mono** cho số liệu, khóa, mã yêu cầu, thời gian.
- Phông **tự lưu trên server** (không gọi CDN ngoài) để giao diện nội bộ không phụ thuộc internet và không rò thông tin.
- Số liệu trong bảng dùng chữ số đều chiều rộng (tabular) để cột thẳng hàng.
- Thang cỡ chữ: 12 / 13 / 14 (nền) / 16 / 20 / 24 / 32. Tiêu đề không quá ba cấp trên một trang.

### Bố cục, khoảng cách, hình khối
- Lưới 12 cột, nội dung tối đa ~1440px, khoảng cách theo bội số của 4px.
- Bo góc nhỏ (6–8px), viền mảnh thay vì đổ bóng nặng; bóng chỉ cho lớp nổi (menu, modal).
- Mật độ: bảng ở chế độ gọn, hàng cao ~40px, có chế độ "thoải mái" bật tắt được.
- Chuyển động ngắn (120–200ms), chỉ để báo thay đổi trạng thái; tôn trọng `prefers-reduced-motion`.

## 3. Các màn hình chính (phác thảo)

### 3.1 Tổng quan
```
┌ Cổng AI   Tổng quan  Tài khoản AI  Ứng dụng  Định tuyến  Nhật ký  Cảnh báo  Hệ thống   [Ctrl+K]  👤 ┐
├─ ● 5 sống · 1 nghỉ · 0 hết phiên · 3 ứng dụng ───────────────────── Cập nhật 12 giây trước ────────┤
│ [Yêu cầu hôm nay] [Đơn vị đã dùng] [Tỉ lệ lỗi] [Chờ xử lý]   ← 4 thẻ số, mỗi thẻ có đường nhỏ so với hôm qua │
│ ┌ Lưu lượng 24 giờ (đường, tách theo ứng dụng) ───────────┐ ┌ Cần chú ý ─────────────────────────┐ │
│ │                                                          │ │ ⚠ claude-runner-02 nghỉ đến 14:20  │ │
│ └──────────────────────────────────────────────────────────┘ │ ⚠ App "X" đã dùng 86% ngày         │ │
│ ┌ Tài khoản (lưới thẻ, mỗi thẻ: nhãn, vòng đo, slot, hạn) ─┐ └────────────────────────────────────┘ │
│ ┌ Ứng dụng dùng nhiều nhất (thanh ngang + % hạn mức) ──────┐                                        │
└──────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Tài khoản AI
- **Danh sách:** bảng gọn, nhóm theo hãng. Cột: nhãn (+ biểu tượng hãng), trạng thái, mức dùng (dải/vòng, ghi nguồn "đọc từ CLI" hoặc "ước lượng"), slot đang dùng/tối đa, hết cooldown lúc, phiên đăng nhập còn/hết, hành động nhanh (tạm dừng, kiểm tra).
- **Chi tiết:** đầu trang có tên, trạng thái và các nút `Tạm dừng`, `Rút dần (drain)`, `Kiểm tra ngay`. Tab: *Mức dùng* (theo app, theo giờ, dải hạn mức), *Lịch sử mẫu %* (nếu có), *Sự cố*, *Cấu hình*, *Hướng dẫn đăng nhập lại* (hiện lệnh SSH cần chạy, **không nhận mật khẩu/token trên web**).

### 3.3 Ứng dụng
- **Danh sách:** tên, trạng thái, khả năng được cấp (chip), mức dùng hôm nay/tháng so với hạn mức (thanh), lần dùng cuối.
- **Chi tiết:** tab *Tổng quan* (mức dùng, đồ thị), *Khóa API* (tạo, xoay vòng, thu hồi; khóa chỉ hiện một lần có nút sao chép), *Khả năng & mô hình* (công tắc từng khả năng, danh sách model), *Hạn mức* (rpm, song song, ngày, tháng; mềm/cứng), *Định tuyến* (pool/tài khoản ghim), *Nhật ký*. Nút `Tạm ngưng ứng dụng` luôn ở vị trí cố định, có xác nhận.
- **Thêm ứng dụng (wizard 3 bước):** 1) Thông tin → 2) Khả năng và hạn mức (mặc định chặt, gợi ý mẫu) → 3) Xem lại + tạo khóa (hiện một lần). Mục tiêu < 3 phút.

### 3.4 Định tuyến
- Trình soạn pool: kéo thả thứ tự ưu tiên, trọng số; bảng "ứng dụng → pool → dự phòng" để thấy toàn cảnh; trình duyệt tên model ảo.

### 3.5 Nhật ký
- Bảng có lọc nhiều chiều (app, tài khoản, khả năng, trạng thái, khoảng thời gian), cột có mã yêu cầu để tra theo `X-Gateway-Request-Id`. Mở một dòng thấy chuỗi: app → quy tắc → tài khoản → thử lại → kết quả. **Không có nội dung prompt.**

### 3.6 Cảnh báo và Hệ thống
- Cảnh báo: danh sách đang mở xếp theo mức độ, có nút xác nhận đã biết/giải quyết, liên kết thẳng tới đối tượng.
- Hệ thống: danh mục khả năng (xem và bật/tắt, mức rủi ro), hệ số model, người dùng và vai trò, nhật ký kiểm toán, **Dừng khẩn cấp** (khu vực riêng, màu cảnh báo, xác nhận hai bước).

## 4. Mẫu tương tác và nội dung chữ

- Nhãn tiếng Việt đơn giản, đồng nhất thuật ngữ: *Tài khoản AI*, *Ứng dụng*, *Khả năng*, *Hạn mức*, *Đơn vị quy đổi*. Giải thích bằng tooltip ngắn (ví dụ "Đơn vị quy đổi là đơn vị nội bộ để so sánh mức dùng giữa các model").
- **Luôn ghi nguồn con số**: nhãn nhỏ `đọc từ CLI`, `đếm nội bộ`, `ước lượng`, kèm thời điểm cập nhật. Dự báo luôn ghi là dự báo.
- Hành động nguy hiểm (thu hồi khóa, tạm ngưng app, dừng khẩn cấp) có hộp xác nhận nêu rõ hậu quả; có thể hoàn tác thì ghi rõ cách hoàn tác.
- Trạng thái rỗng có hướng dẫn việc kế tiếp ("Chưa có ứng dụng nào. Thêm ứng dụng đầu tiên").
- Lỗi nói rõ nguyên nhân và cách xử lý, không hiện chuỗi kỹ thuật thô.
- Số lớn có định dạng theo vi-VN, thời gian theo múi giờ cấu hình và có tooltip giờ đầy đủ.

## 5. Đủ trạng thái cho mọi thành phần
Mỗi thành phần tùy biến phải có: mặc định, hover, focus (vòng focus rõ), active, disabled, đang tải (skeleton), rỗng, lỗi, thành công. Dữ liệu cập nhật tự động (làm mới nền) phải có chỉ báo thời gian cập nhật và không làm nhảy bố cục.

## 6. Chế độ sáng/tối
Cả hai chế độ đều bắt buộc và phải đạt tiêu chuẩn như nhau. Mặc định theo hệ điều hành, có công tắc trong menu người dùng.

## 7. Khả năng tiếp cận và đáp ứng kích thước

Ngưỡng tối thiểu, đo thật cả hai chế độ:
- Chữ đạt tương phản WCAG AA; trạng thái không chỉ dựa vào màu.
- Mọi phần tử tương tác có vòng focus nhìn thấy; thao tác hoàn toàn bằng bàn phím; thứ tự Tab khớp thứ tự nhìn.
- Mục tiêu chạm ≥ 44px trên điện thoại.
- Không cuộn ngang ở 375px; trên màn nhỏ, menu ngang thu thành menu bấm mở, bảng chuyển sang danh sách thẻ hoặc cuộn ngang có chủ đích trong khung bảng.
- Tôn trọng `prefers-reduced-motion`.
- Không lỗi console, không yêu cầu mạng thất bại.

Màn hình ưu tiên: máy tính. Điện thoại phải đủ để xem trạng thái và bấm tạm dừng/dừng khẩn cấp.

## 8. Cách dựng với Filament (để kế hoạch bám theo)

- Panel riêng `->topNavigation()`, `->colors([...])` với bậc 50–950 sinh từ `#1E5BC6`, `->font('Be Vietnam Pro', provider: LocalFontProvider::class)`, `->darkMode()`, `->globalSearchKeyBindings(['ctrl+k'])`.
- Một theme Filament tùy biến (Tailwind) chứa token mục 2; **mọi giá trị màu/khoảng cách lấy từ token**, không viết cứng trong component.
- Component tùy biến đặt ở `resources/views/components/`: `status-strip`, `account-gauge`, `quota-ribbon`, `status-chip`, và chỉ dựa trên linh kiện Filament.
- Widget trang Tổng quan dùng Filament Widgets (Stats Overview, Chart, Table widget).
- Tên hiển thị tiếng Việt cho mọi Resource/Page/Action.

## 9. Cách kiểm chứng giao diện (để không "khen" khi chưa nhìn)

1. Dựng thẳng trên Filament (không dựng bản HTML tĩnh bỏ đi) với dữ liệu mẫu giả.
2. Mở bằng trình duyệt thật, chụp màn hình ở 375 / 768 / 1280 / 1440 px, cả sáng và tối, **rồi nhìn các ảnh đó**.
3. Đo: cuộn ngang, tương tác (chữ và thành phần), vòng focus, kích thước mục tiêu chạm, lỗi console/mạng; chạy kiểm tra bằng axe.
4. Chấm theo thang phê bình thiết kế, ghi điểm kèm bằng chứng ("điểm N/100 theo thang này", không phải khẳng định tuyệt đối).
5. Tối đa 2 vòng sửa; còn tồn tại thì báo rõ thay vì lặp mãi.
6. Một buổi dùng thử ngắn với người vận hành thật theo các việc ở mục 1, ghi lại số liệu thật.

Ghi chú công cụ: bộ công cụ của skill thiết kế (`vqa.mjs`, `a11y.mjs`, `tokens2css.mjs`) nằm ở `/root/ai-design-studio`, hiện tôi **không đọc được thư mục này** (bị từ chối quyền). Nếu không cấp quyền, bước 2–3 dùng trình duyệt tích hợp và axe-core trực tiếp; nếu có quyền thì dùng đúng các script đó.
