# Cổng AI — Cách ly khỏi server chính

Ngày soạn: 19/11/2026. Trạng thái: **thiết kế, chưa dựng, chưa kiểm thử**. Bổ sung cho [THIET_KE.md](THIET_KE.md) mục 7.

## 1. Bài toán

Server chính chạy những thứ rất quan trọng. Cổng AI chạy các CLI AI nhận nội dung từ bên ngoài (có thể do kẻ xấu cài lệnh vào). Giả định xấu nhất cần chịu được:

1. AI bị lừa làm theo lệnh của kẻ xấu (prompt injection).
2. Kẻ xấu khai thác lỗi trong CLI hoặc môi trường chạy và **thực thi được mã bên trong runner**.
3. Từ đó thử thoát ra ngoài container để chạm vào server chính.

Mục tiêu: dù (1) và (2) xảy ra, thiệt hại **dừng ở máy AI**, không chạm được server chính, dữ liệu hay bí mật của nó.

**Container không phải ranh giới bảo mật đầy đủ**: các container dùng chung nhân Linux với máy chủ, và lỗi thoát khỏi container có tồn tại. Với server "rất quan trọng" không nên chỉ dựa vào container.

## 2. Khuyến nghị: máy riêng cho Cổng AI

Chạy **toàn bộ Cổng AI** (gateway, runner, proxy, cơ sở dữ liệu và giao diện quản trị của nó) trên **một máy tách biệt** ("máy AI"): một máy ảo (VM) riêng hoặc một máy vật lý/VPS nhỏ riêng. Server chính chỉ nói chuyện với máy AI qua **một cổng API duy nhất**.

```
 Mạng nội bộ / Internet
        │
        │  chỉ cho phép: máy AI → ra tên miền của hãng (qua proxy allowlist)
        │                 quản trị viên → vào giao diện và SSH (qua VPN/bastion)
        │                 server chính → vào CỔNG API của máy AI (1 cổng, 1 địa chỉ)
        ▼
┌──────────────────────────────── MÁY AI (VM/máy riêng) ────────────────────────────────┐
│  Gateway (API) ── Runner Claude ─┐                                                    │
│  Giao diện quản trị              ├─ mạng Docker nội bộ, không có đường ra trực tiếp  │
│  Postgres + Redis riêng          ├─ Runner Gemini ─┐                                  │
│                                  └─ Runner Grok ───┴─► Proxy allowlist ─► hãng AI    │
└───────────────────────────────────────────────────────────────────────────────────────┘
        ▲
        │  chỉ chiều này: server chính gọi sang (khóa API riêng của từng ứng dụng)
 SERVER CHÍNH (SEO Studio, ứng dụng khác): không có thông tin đăng nhập của máy AI,
                                          máy AI không có thông tin đăng nhập của nó
```

Vì sao đây là lớp mạnh nhất: kẻ chiếm được runner, thậm chí thoát khỏi container, mới chỉ ở trên **máy AI**, nơi không có thứ gì đáng giá ngoài chính các phiên đăng nhập AI. Ranh giới là máy ảo hoặc máy vật lý, chứ không phải nhân dùng chung.

## 3. Các lớp phòng thủ, từ ngoài vào trong

### L1. Tách máy
- Máy AI **không** chạy trên cùng Docker daemon, cùng mạng Docker hay cùng cơ sở dữ liệu/Redis với server chính.
- Cơ sở dữ liệu và Redis của Cổng AI nằm trên máy AI, không dùng chung với ứng dụng nào khác.
- Ổ đĩa máy AI mã hóa (có chứa phiên đăng nhập và có thể có nội dung đi qua).

### L2. Tường lửa (làm ở hai phía, không chỉ một)

| Chiều | Quy tắc |
|---|---|
| Server chính → máy AI | Chỉ mở **một cổng API**, chỉ từ địa chỉ của các ứng dụng được phép |
| Máy AI → server chính và mạng nội bộ | **Chặn tất cả** (dải riêng 10/8, 172.16/12, 192.168/16, địa chỉ metadata 169.254.169.254, địa chỉ của server chính). Chỉ trả lời cho các kết nối đã được mở từ ngoài vào |
| Máy AI → internet | Chỉ tên miền của các hãng AI qua proxy allowlist (danh sách lấy từ thăm dò P9, G9). Mở thêm tạm thời khi dựng image, rồi đóng |
| Quản trị viên → máy AI | SSH và giao diện quản trị chỉ từ VPN hoặc máy bastion, có xác thực mạnh |
| Internet → máy AI | Không có đường vào |

### L3. Không chia sẻ bí mật
- Máy AI **không** giữ khóa SSH, mật khẩu cơ sở dữ liệu, khóa đám mây hay bất kỳ thông tin đăng nhập nào của server chính.
- Server chính **chỉ** giữ một khóa API cho mỗi ứng dụng gọi Cổng AI (thu hồi được ngay). Không có chiều ngược lại.
- Không gắn thư mục chia sẻ (NFS/SMB) hay volume chung giữa hai máy.

### L4. Cứng hóa container trên máy AI (lớp thứ hai, phòng khi một mình lớp máy chưa đủ)
- Mỗi tài khoản AI một container riêng, chạy bằng user không đặc quyền; `cap_drop: ALL`; `no-new-privileges`; hệ tệp gốc chỉ đọc; `tmpfs` cho thư mục tạm; giới hạn bộ nhớ, CPU, số tiến trình, thời gian.
- Không `privileged`, không gắn Docker socket, không gắn thư mục máy chủ, không dùng mạng hoặc PID của máy chủ.
- Dùng Docker **rootless** hoặc `userns-remap` để root trong container không phải root trên máy.
- Mạng Docker nội bộ **không có đường ra internet**; đường ra duy nhất là proxy allowlist.
- Cân nhắc runtime cách ly mạnh hơn cho runner (ví dụ gVisor hoặc Kata Containers) nếu các CLI chạy được trên đó. **Chưa kiểm chứng** tương thích với từng CLI; chỉ quyết sau khi thử.

### L5. Cách ly từng yêu cầu
- Mỗi yêu cầu chạy trong thư mục làm việc tạm riêng, xóa ngay sau khi xong. Không có trạng thái dùng chung giữa các ứng dụng.
- Riêng Grok tự lưu nội dung hội thoại ra đĩa: phải xóa thư mục phiên của yêu cầu đó (xem G11 trong THAM_DO_CLI).

### L6. Giám sát và phản ứng
- Cảnh báo khi runner mở kết nối ra ngoài danh sách, khi có tiến trình lạ, khi CPU/RAM bất thường, khi một ứng dụng tăng lượng dùng đột biến.
- Có thể tắt khẩn cấp từng ứng dụng, từng tài khoản AI, hoặc dừng toàn bộ Cổng AI. Nếu nghi ngờ bị xâm nhập: **tắt máy AI**, server chính vẫn chạy bình thường (chỉ mất tính năng AI) và các phiên đăng nhập AI được thu hồi từ phía hãng.

## 4. Nếu chưa thể có máy riêng

Xếp theo độ an toàn giảm dần:

| Phương án | Ranh giới | Nhận xét |
|---|---|---|
| **A. Máy riêng / VPS nhỏ riêng** | Máy khác nhau | Khuyến nghị. Chi phí thấp so với rủi ro |
| **B. Máy ảo (KVM, Proxmox…) riêng trên cùng máy vật lý** | Hypervisor | Chấp nhận được. Tường lửa vẫn phải chặn máy ảo AI → mạng chính |
| **C. Container rootless + gVisor/Kata + tường lửa chặn mạng chính, trên cùng máy** | Nhân (đã làm cứng) | Yếu hơn A và B. Chỉ dùng cho thử nghiệm, không dùng cho dữ liệu thật |
| **D. Docker thường trên chính server đang chạy việc quan trọng** | Nhân dùng chung | **Không khuyến nghị** với server của bạn |

## 5. Những việc không được làm

- Không đặt Cổng AI trên cùng Docker daemon hoặc cùng mạng Docker với dịch vụ quan trọng.
- Không dùng chung Postgres/Redis/ổ đĩa với server chính.
- Không cho máy AI SSH ngược vào server chính, kể cả để "tiện triển khai".
- Không mở cổng quản trị của máy AI ra internet.
- Không để kẻ xấu có đường kiểm soát cấu hình tường lửa từ giao diện web.

## 6. Cách kiểm chứng (bằng chứng chạy thật, theo AGENTS.md mục 1.2)

Trên **staging có cấu trúc giống thật**, đứng **trong một runner** và thử, kết quả phải là bị chặn:

1. Kết nối tới địa chỉ server chính (mọi cổng thường dùng) → phải thất bại.
2. Kết nối tới các dải riêng và địa chỉ metadata → phải thất bại.
3. Kết nối tới một tên miền không nằm trong danh sách → phải thất bại.
4. Tìm Docker socket, đọc thư mục máy chủ, đọc volume của tài khoản khác → không thấy.
5. Chạy `id`, thử leo quyền, xem capabilities → user không đặc quyền, không có capability.
6. Từ **máy AI**, quét cổng tới server chính → phải bị tường lửa chặn.
7. Từ server chính, thử vào mọi cổng của máy AI ngoài cổng API → phải bị chặn.
8. Dừng máy AI → server chính và ứng dụng vẫn chạy (chỉ lỗi khi gọi AI, được xử lý êm).

Mức hiện tại: **chưa chạy bất kỳ bước nào**. Chỉ khi có log của các bước trên mới được ghi "đạt".

## 7. Câu hỏi cần bạn trả lời

1. Có thể có một máy ảo hoặc VPS riêng cho máy AI không? Ước lượng cấu hình sau khi đo ở P8/G8.
2. Server chính và máy AI nằm cùng mạng nội bộ hay khác nhà cung cấp? Điều này quyết định cách đặt tường lửa.
3. Ai quản trị tường lửa của hai phía, để không ai phải mở rộng quy tắc theo yêu cầu từ giao diện web?
