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

---

# Đợt 2: các màn còn thiếu, phân quyền, 2FA (19/11/2026)

Mức đạt: **Đã viết code** và **test giả lập đạt** (34 test PHPUnit, CSDL trong bộ nhớ); đã xem bằng trình duyệt một phần. Chưa tích hợp staging, chưa có người thật nghiệm thu.

## Đã làm
- Form Thêm/Sửa tài khoản AI (chỉ thông tin mô tả), Sửa ứng dụng (khả năng, model, pool, hạn mức), Hệ số model, Cài đặt (ngưỡng, giữ dữ liệu, email, webhook mã hóa), Người dùng (4 vai trò), trang lỗi tiếng Việt.
- Phân quyền: ma trận ở `app/Enums/Role.php`, mặc định từ chối; chủ ứng dụng chỉ thấy ứng dụng được gán (truy vấn lọc, ứng dụng người khác trả 404); hành động nguy hiểm ẩn với vai trò không đủ quyền.
- Giới hạn mạng cho giao diện (`ADMIN_ALLOWED_CIDRS`); ở production để trống thì khóa hết.
- 2FA (TOTP) bằng `jeffgreco13/filament-breezy` 2.6.6 (dòng tương thích Filament 3); mật khẩu tối thiểu 12 ký tự ở cả màn Người dùng và Hồ sơ.

## Test tự động (đạt)
- Ma trận quyền của 4 vai trò trên 17 đường dẫn (mở được / bị 403).
- Chủ ứng dụng: không thấy và không sửa được ứng dụng của người khác; nhật ký chỉ của ứng dụng mình; chưa được gán ứng dụng nào thì không thấy gì; người dùng không có vai trò hợp lệ bị chặn.
- Giới hạn mạng: ngoài dải bị 403, trong dải vào được, production không cấu hình thì khóa hết.
- Địa chỉ runner: từ chối IP nội bộ, localhost, metadata, đường dẫn, https, tên ngoài, có tài khoản nhúng, cổng đặc quyền. **Test này bắt được một lỗi thật trong code của tôi** (`isset` với nhiều biến để lọt `user:pw@`), đã sửa.
- Webhook: mã hóa khi lưu, không xuất hiện ở kiểm toán, từ chối http, IP, localhost, tên nội bộ; để trống thì giữ nguyên.
- Không hạ quyền hoặc xóa quản trị viên cuối cùng; khóa API chỉ lưu bản băm.

## Đã xem trên trình duyệt thật
Người dùng, Cài đặt, form tài khoản, Sửa ứng dụng, Hệ số model, Hồ sơ của tôi (có mục 2FA), góc nhìn chủ ứng dụng (chỉ hai mục menu, chỉ một ứng dụng, không dải trạng thái, không nút sửa), trang 403 tiếng Việt.

## Chưa làm / chưa biết (nói thẳng)
- **Luồng 2FA chưa thử thật**: chưa bật 2FA, chưa đăng nhập bằng mã, chưa thử `REQUIRE_2FA=true`, chưa thử mã khôi phục.
- Gói 2FA thuộc dòng 2.x: bản mới của gói nhắm Filament 4/5. Là phụ thuộc bên thứ ba cần theo dõi; nếu sau này nâng Filament thì phải đổi gói.
- Chưa quét axe, chưa xem chế độ tối và điện thoại cho các màn **mới** (chỉ đã làm cho các màn đợt 1).
- Chưa thử lưu thay đổi bằng tay ở form Sửa ứng dụng và Sửa tài khoản (có test cho tạo tài khoản, chưa có test cho sửa ứng dụng).
- Giới hạn mạng mới được test ở mức đơn vị; chưa thử sau reverse proxy thật (`TRUSTED_PROXIES`).
- Nút "Kiểm tra ngay" và mọi thao tác trên tài khoản AI **chưa nối gateway**; chỉ ghi nhận.
- Vai trò `operator` có quyền dừng khẩn cấp (quyết định thiết kế của tôi, bạn có thể muốn đổi).
- Chưa có chức năng vô hiệu hóa người dùng (chỉ xóa).
