# Cổng AI — mặt điều khiển (Laravel 12 + Filament 3)

Đây là **khung giao diện với dữ liệu mẫu giả**. Chưa nối gateway, chưa gọi CLI AI nào. Xem [../docs/KIEM_CHUNG_GIAO_DIEN.md](../docs/KIEM_CHUNG_GIAO_DIEN.md) để biết đã kiểm chứng gì và chưa kiểm chứng gì.

## Chạy trên máy dev (Docker, không cần cài PHP)

Dùng image `addp-autoseo-app` (PHP 8.2 + Composer) có sẵn trên máy. Chạy từ thư mục `control/`:

```bash
cp .env.example .env
```

Đặt trong `.env`: `APP_KEY` (tạo bằng `php artisan key:generate`), `GATEWAY_DEMO=true`, `GATEWAY_DEMO_ADMIN_EMAIL`, `GATEWAY_DEMO_ADMIN_PASSWORD` (tự đặt giá trị thử, **không commit**).

```bash
docker run --rm -u 1000:1000 -v "$PWD":/app -w /app -e COMPOSER_HOME=/tmp/composer --entrypoint sh addp-autoseo-app:latest -c 'composer install && touch database/database.sqlite && php artisan migrate:fresh --seed --force'
```

```bash
npm install && npm run fonts && npm run build
```

```bash
docker run -d --name aigw-dev -u 1000:1000 -v "$PWD":/app -w /app -p 127.0.0.1:8090:8000 --entrypoint sh addp-autoseo-app:latest -c 'php artisan serve --host=0.0.0.0 --port=8000'
```

Mở `http://127.0.0.1:8090` và đăng nhập bằng tài khoản thử đã đặt trong `.env`.

Làm mới dữ liệu mẫu theo giờ hiện tại, giữ nguyên tài khoản đăng nhập:

```bash
docker exec aigw-dev php artisan gateway:demo-reset
```

## Cấu trúc chính

| Đường dẫn | Nội dung |
|---|---|
| `database/migrations/2026_11_19_000001_create_gateway_tables.php` | Lược đồ theo THIET_KE mục 4 (không lưu bí mật của tài khoản AI) |
| `database/seeders/DemoDataSeeder.php` | Dữ liệu mẫu giả (6 tài khoản, 4 ứng dụng, ~2.600 lượt gọi) |
| `app/Providers/Filament/AdminPanelProvider.php` | Panel: menu ngang, thang màu xanh dương, phông tự lưu |
| `app/Filament/Pages`, `Widgets`, `Resources` | Các màn hình |
| `resources/css/filament/admin/theme.css` | Theme Tailwind và token riêng |
| `resources/views/components/gw` | Linh kiện tùy biến: vòng đo, dải hạn mức, nhãn hãng |

## Lưu ý

- Mọi người dùng đã tạo đều vào được panel (`User::canAccessPanel`); phân vai và 2FA chưa làm.
- `.env`, `database.sqlite`, `node_modules`, `public/build` đều được git bỏ qua.
