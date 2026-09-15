<h1 align="center">🚨 <b>Crime Alert Web</b> 🚨</h1>
<p align="center">Website cảnh báo tội phạm, truy nã, tin tức pháp luật - xây dựng với <b>Laravel</b></p>

<p align="center">
  <img src="https://img.shields.io/github/stars/chi-trung/crime-alert?style=social" alt="Stars">
  <img src="https://img.shields.io/github/forks/chi-trung/crime-alert?style=social" alt="Forks">
  <img src="https://img.shields.io/github/issues/chi-trung/crime-alert" alt="Issues">
  <img src="https://img.shields.io/github/license/chi-trung/crime-alert" alt="License">
  <img src="https://github.com/chi-trung/crime-alert/actions/workflows/ci.yml/badge.svg?branch=main" alt="CI">
</p>

---

## 👋 Giới thiệu
**Crime Alert Web** là ứng dụng web giúp cảnh báo tội phạm, cập nhật tin tức pháp luật và danh sách truy nã mới nhất từ các nguồn uy tín (VnExpress, Bộ Công An).

---

## ✨ <b>Tính năng chính</b>

| Tính năng                | Mô tả                                                                 |
|--------------------------|-----------------------------------------------------------------------|
| 🚨 Cảnh báo tội phạm     | Gửi, duyệt, tìm kiếm, lọc, xem bản đồ, chỉnh sửa, xóa cảnh báo        |
| 👮‍♂️ Truy nã             | Hiển thị, tìm kiếm danh sách người bị truy nã                         |
| 💬 Bình luận & Like      | Bình luận, like/unlike bài viết và bình luận                          |
| 📢 Chia sẻ trải nghiệm   | Gửi, duyệt, xem, xóa bài chia sẻ                                      |
| 📰 Tin tức                | Crawl, hiển thị tin tức pháp luật                                     |
| 🤖 Chatbot AI            | Hỗ trợ AI với 4 provider (Gemini, OpenAI, DeepSeek, OpenRouter)      |
| 💬 Hỗ trợ trực tuyến     | Chat real-time giữa user và admin                                     |
| 🔔 Thông báo             | Hệ thống notification cho like, comment, hỗ trợ                      |
| 📊 Dashboard             | Thống kê chi tiết cho admin và user                                   |
| 🗺️ Bản đồ tương tác      | Hiển thị cảnh báo trên bản đồ Leaflet                                 |
| 👤 Tài khoản              | Đăng ký, đăng nhập, xác thực email, đổi mật khẩu, xóa tài khoản      |

---

## 🛠️ <b>Công nghệ sử dụng</b>
- <img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white"/> **Laravel 12** (PHP >= 8.2)
- <img src="https://img.shields.io/badge/MySQL-4479A1?logo=mysql&logoColor=white"/> **MySQL / MariaDB** (hoặc SQLite cho môi trường dev)
- <img src="https://img.shields.io/badge/Blade-FF2D20?logo=laravel&logoColor=white"/> **Blade** + CSS/JS tĩnh trong `public/css`, `public/js` — **không cần Node.js**
- <img src="https://img.shields.io/badge/Leaflet-199900?logo=leaflet&logoColor=white"/> **Leaflet.js** (bản đồ, qua CDN)
- <img src="https://img.shields.io/badge/Guzzle-6DB33F?logo=php&logoColor=white"/> **Guzzle** (HTTP client crawl dữ liệu)
- <img src="https://img.shields.io/badge/Symfony%20DomCrawler-000000?logo=symfony&logoColor=white"/> **Symfony DomCrawler** (phân tích HTML)
- <img src="https://img.shields.io/badge/AI%20APIs-000000?logo=openai&logoColor=white"/> **AI APIs** (Gemini, OpenAI, DeepSeek, OpenRouter — cấu hình qua `.env`)

---

## ⚙️ <b>Yêu cầu hệ thống</b>
- PHP >= 8.2 (kèm các extension `pdo_mysql`/`pdo_sqlite`, `mbstring`, `fileinfo`, `curl`, `openssl`)
- Composer >= 2.x

Không cần Node.js/npm — toàn bộ CSS/JS là file tĩnh, thư viện (Bootstrap, Leaflet, icons) nạp qua CDN.

---

## 🚀 <b>Cài đặt</b>

### 1️⃣ Clone và cài dependencies
```bash
git clone https://github.com/chi-trung/crime-alert.git
cd crime-alert
composer install
```

### 2️⃣ Khởi tạo môi trường
```bash
composer setup
```
Lệnh này tự động: copy `.env.example` → `.env` (nếu chưa có), generate `APP_KEY`, tạo symlink `public/storage` (để hiển thị ảnh cảnh báo đã upload), và chạy `migrate`.

### 3️⃣ Database
Mặc định `.env` dùng **SQLite** (`database/database.sqlite`) — không cần làm gì thêm.

Dùng **MySQL**: sửa trong `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crime_alert
DB_USERNAME=root
DB_PASSWORD=
```
sau đó `php artisan migrate:fresh --seed`.

### 4️⃣ Seed dữ liệu + tài khoản admin
```bash
php artisan migrate:fresh --seed
```
`AdminUserSeeder` tạo tài khoản admin (email/mật khẩu đọc từ `ADMIN_EMAIL` / `ADMIN_PASSWORD` trong `.env`, mặc định `admin@crime-alert.local` / `ChangeMe!123`). **Đổi mật khẩu ngay sau lần đăng nhập đầu.** Từ issue #273, seeder từ chối (throw) nếu email cấu hình đã thuộc về tài khoản **không phải admin** — ai đó đăng ký trước email default thì deploy sẽ fail loudly, không âm thầm thăng cấp họ; hãy đặt `ADMIN_EMAIL` khác trước khi seed.

### 5️⃣ Chạy
```bash
php artisan serve
```
Mở http://127.0.0.1:8000. Muốn server + crawl tin tức/truy nã tự động cùng lúc:
```bash
php artisan serve:all
```

---

## 🔧 <b>Cấu hình Chatbot AI</b>

Key **không** hardcode trong source — cấu hình hoàn toàn qua `.env`:

```env
CHATBOT_PROVIDER=openrouter   # gemini | openai | deepseek | openrouter

GEMINI_API_KEY=
***
DEEPSEEK_API_KEY=
***

# Tùy chọn: model + header giới thiệu với OpenRouter
#OPENROUTER_MODEL=
#OPENROUTER_REFERER=
# Tùy chọn: model cho Gemini (mặc định gemini-2.5-flash)
#GEMINI_MODEL=
```

Provider nào trống key thì chatbot trả lời lịch sự "không khả dụng" thay vì gọi API.

> ⚠️ **Bảo mật:** các key AI cũ từng bị commit vào lịch sử git của repo (đã xóa khỏi code hiện tại). Nếu bạn vận hành repo này, hãy **rotate toàn bộ key cũ** và chỉ dùng key mới đặt trong `.env` (đã được `.gitignore`).

---

## 🕵️‍♂️ <b>Crawl dữ liệu</b>
```bash
php artisan crawl:news          # Crawl tin tức pháp luật
php artisan crawl:wanted-list   # Crawl danh sách truy nã
```

Lệnh crawl đã được lên lịch tự động trong `routes/console.php`
(`crawl:news` mỗi 30 phút, `crawl:wanted-list` mỗi giờ, `withoutOverlapping`).
Để lịch chạy trên server, thêm cron entry theo hướng dẫn Laravel scheduler:
```cron
* * * * * cd /duong-dan/toi/crime-alert && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🧪 <b>Kiểm thử</b>
```bash
composer test        # = php artisan test (PHPUnit, chạy trên SQLite in-memory)
vendor/bin/pint      # format code style (Laravel Pint)
vendor/bin/pint --test   # kiểm tra style không sửa file
```

---

## 📁 <b>Cấu trúc project</b>

```
crime-alert/
├── app/
│   ├── Console/Commands/         # Artisan commands (crawl:news, crawl:wanted-list, serve:all)
│   ├── Http/Controllers/         # Controllers
│   ├── Http/Middleware/          # Middleware (admin, ...)
│   ├── Models/                   # Eloquent models
│   ├── Notifications/            # Notification classes
│   └── Services/                 # Logic dùng chung (DashboardStatsService, ...)
├── resources/views/              # Blade templates
├── routes/                       # web.php, auth.php, console.php
├── database/migrations/          # Cấu trúc CSDL
├── database/seeders/             # Dữ liệu mẫu + admin
├── public/css|js                 # Asset tĩnh (không có build step)
├── config/services.php           # Cấu hình provider AI
├── tests/                        # Feature + Unit tests
└── composer.json
```

---

## ⚠️ <b>Lưu ý quan trọng</b>
- **Không override lệnh `php artisan serve`** trong `routes/console.php` — dùng `serve:all` để chạy server kèm crawl.
- Ảnh cảnh báo hiển thị được nhờ symlink `public/storage` — `composer setup` đã tạo sẵn; nếu tự migrate thủ công thì chạy thêm `php artisan storage:link`.
- Phân quyền dùng cột `isAdmin` trên bảng `users` (không phải Spatie Permission).

---

## 🤝 <b>Đóng góp & phát triển</b>
- Fork, tạo branch mới và gửi pull request nếu muốn đóng góp code.
- Code chạy qua `composer test` và `vendor/bin/pint --test` trước khi merge (CI kiểm tra trên cả SQLite lẫn MySQL).
- Nếu có vấn đề, vui lòng tạo issue trên GitHub.

## 📄 License
[MIT](LICENSE)
