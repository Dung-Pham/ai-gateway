# Cổng AI — Kiểm chứng khung giao diện (19/11/2026)

Phạm vi: **khung giao diện Filament với dữ liệu mẫu giả** (`control/`). Chưa có gateway, chưa có CLI nào được gọi. Mọi số liệu trong giao diện là dữ liệu giả, có nhãn "DỮ LIỆU MẪU".

Theo AGENTS.md mục 1.2, mức đạt hiện tại: **Đã viết code** và **chạy được trên môi trường dev cục bộ (Docker, SQLite)**. Chưa có test tự động, chưa tích hợp staging, chưa có người vận hành thật dùng thử.

## Đã nhìn thấy trên màn hình thật (trình duyệt tích hợp, 127.0.0.1:8090)

| Màn hình | Kết quả |
|---|---|
| Đăng nhập | Hiển thị đúng, màu xanh dương, phông Be Vietnam Pro |
| Tổng quan | Dải trạng thái, 4 thẻ số, biểu đồ 24 giờ, "Cần chú ý", lưới tài khoản (vòng đo), mức dùng theo ứng dụng |
| Tài khoản AI | Danh sách; trang chi tiết tài khoản hết phiên và tài khoản đang nghỉ |
| Ứng dụng | Danh sách, chi tiết (khả năng, hạn mức, khóa API, nhật ký) |
| Thêm ứng dụng | Wizard 3 bước chạy từ đầu đến cuối: nhập thông tin, chọn khả năng và pool, xem lại, tạo; khóa API hiện một lần; lưu vào CSDL và chuyển sang trang chi tiết |
| Định tuyến, Nhật ký, Cảnh báo | Hiển thị đúng |
| Khả năng, Kiểm toán, Dừng khẩn cấp | Hiển thị đúng (chỉ xem ảnh chụp, chưa bấm thử hành động dừng khẩn cấp) |
| Chế độ tối | Tổng quan xem và quét axe: không lỗi tương phản |
| Điện thoại 375px | Tổng quan: không cuộn ngang, menu thu thành ngăn kéo, thẻ xếp dọc |

## Đo được

- **Tương phản (axe-core, WCAG 2.2 AA):** sau khi chỉnh bảng màu, các trang Tổng quan, Tài khoản AI, Ứng dụng, Nhật ký, Định tuyến không còn lỗi tương phản ở chế độ sáng; Tổng quan không lỗi ở chế độ tối. Lỗi ban đầu: chữ xám phụ 4,41:1, huy hiệu xanh lá 3,14:1, hổ phách 3,07:1, đỏ 4,41:1; đã sửa bằng thang màu riêng trong `AdminPanelProvider`.
- **Cuộn ngang:** không ở 375px và 1440px (đo `scrollWidth`).
- **Vùng chạm ≥ 44px trên điện thoại:** Tổng quan: 0 phần tử nhỏ hơn 44px sau khi chỉnh CSS và liên kết widget.
- **Yêu cầu mạng:** toàn bộ là `127.0.0.1`; phông tự lưu, không gọi CDN ngoài.
- **Lỗi console:** không còn lỗi thật (lỗi 404 thoáng qua là tệp công cụ kiểm thử tạm đã xóa).

## Chưa làm / chưa biết

- **Chưa chấm điểm theo thang phê bình thiết kế**: bộ tiêu chí của skill design nằm ở `/root/ai-design-studio`, phiên này không đọc được. Không có con số điểm nào.
- Chưa quét axe ở chế độ tối trên các trang ngoài Tổng quan; chưa quét axe trang Khả năng, Kiểm toán, Dừng khẩn cấp, wizard; chưa đo vùng chạm trên các trang ngoài Tổng quan.
- Chưa kiểm tra bàn phím toàn diện (thứ tự Tab, vòng focus) ngoài những gì axe bắt được.
- Chưa kiểm tra `prefers-reduced-motion` bằng thao tác; CSS đã có quy tắc nhưng chưa thử.
- Chưa kiểm tra máy tính bảng (768px).
- Chưa có buổi dùng thử với người vận hành thật.
- Phân vai (admin, operator, viewer, app_owner), 2FA, giới hạn mạng nội bộ: **chưa làm** (hiện mọi người dùng đã tạo đều vào được).

## Lỗi tiếp cận còn lại, do thư viện Filament 3.3 sinh ra (chưa sửa)

1. Menu thả xuống "Hệ thống" ở thanh trên: `ul` chứa `div`, `li` không nằm trong `ul` (axe: list, listitem, mức serious).
2. Nút "bỏ bộ lọc" (biểu tượng) ở bảng không có tên truy cập (axe: button-name).

Hướng xử lý nếu cần: thêm `aria-label` bằng một đoạn JS nhỏ qua render hook, hoặc gộp ba mục "Hệ thống" thành một trang có tab để bỏ menu thả xuống. Chưa làm.

## Việc kỹ thuật đã quyết trong quá trình dựng

- **Laravel 12, không phải 11**: `composer audit` báo 5 cảnh báo cho `laravel/framework` 11 (chỉ vá ở nhánh 12 trở lên). Bản 12 không còn cảnh báo. SEO Studio vẫn đang ở Laravel 11; việc nâng cấp đó nằm ngoài phạm vi.
- Filament 3.3.57, PHP 8.2 trong container `addp-autoseo-app`.
- Menu ngang, `breadcrumbs(false)`, phông Be Vietnam Pro + IBM Plex Mono tự lưu, theme Tailwind riêng.
- Dải trạng thái toàn hệ thống nền navy ở mọi trang, cập nhật mỗi 15 giây.

## Cách chạy lại

Xem [control/README.md](../control/README.md).
