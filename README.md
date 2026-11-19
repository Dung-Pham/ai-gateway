# Cổng AI (AI Gateway)

Đưa các gói AI đăng ký (Claude, Codex/ChatGPT, Gemini — dùng qua CLI) lên server thành **một API dùng chung** cho các ứng dụng nội bộ, kèm công cụ quản lý: nhiều tài khoản, hạn mức và lưu lượng theo từng ứng dụng, giới hạn quyền của AI.

**Trạng thái (19/11/2026): có khung giao diện (`control/`) chạy với dữ liệu mẫu giả. Chưa có gateway, chưa gọi CLI AI nào.**

| Tài liệu | Nội dung |
|---|---|
| [docs/THIET_KE.md](docs/THIET_KE.md) | Kiến trúc, dữ liệu, đo lường, bảo mật, API, vận hành |
| [docs/GIAO_DIEN.md](docs/GIAO_DIEN.md) | UX, định hướng nghệ thuật (xanh dương), màn hình |
| [docs/KE_HOACH_BUILD.md](docs/KE_HOACH_BUILD.md) | Các giai đoạn, nghiệm thu, rủi ro |
| [docs/THAM_DO_CLI.md](docs/THAM_DO_CLI.md) | Lệnh thăm dò CLI (Giai đoạn 0), **chưa chạy** |
| [docs/CHUAN_BI_SERVER.md](docs/CHUAN_BI_SERVER.md) | Cấu hình nằm ở đâu trong giao diện; cần chuẩn bị gì cho server |
| [docs/KIEM_CHUNG_GIAO_DIEN.md](docs/KIEM_CHUNG_GIAO_DIEN.md) | Kết quả kiểm chứng khung giao diện: đã thấy, đã đo, chưa làm |
| [control/README.md](control/README.md) | Cách chạy khung giao diện |

Quyết định đã chốt: triển khai bằng Docker Compose; repo riêng tách khỏi addp-autoseo; gateway Python/FastAPI + điều khiển Laravel/Filament; mỗi tài khoản AI một container runner.

Quy tắc: không commit bí mật hay dữ liệu phiên đăng nhập; không push khi chưa được duyệt; mỗi mục "xong" phải ghi đúng mức kiểm chứng (đã viết code / test giả lập / staging / người thật nghiệm thu).
