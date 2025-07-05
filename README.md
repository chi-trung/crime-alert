<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="220" alt="Laravel Logo">
</p>

<p align="center">
  <img width="120" src="https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExcWx4Y2d0eWQ4dWQ1dG5tZ3ZzZ2J5eGJ4Y2V6d2VtYzB6bW1xYyZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/26tn33aiTi1jkl6H6/giphy.gif">
</p>

<h1 align="center">🚨 <b>Crime Alert Web</b> 🚨</h1>
<p align="center">Website cảnh báo tội phạm, truy nã, tin tức pháp luật - xây dựng với <b>Laravel</b></p>

<p align="center">
  <img src="https://img.shields.io/github/stars/chi-trung/crime-alert-web?style=social" alt="Stars">
  <img src="https://img.shields.io/github/forks/chi-trung/crime-alert-web?style=social" alt="Forks">
  <img src="https://img.shields.io/github/issues/chi-trung/crime-alert-web" alt="Issues">
  <img src="https://img.shields.io/github/license/chi-trung/crime-alert-web" alt="License">
</p>

---

## 👋 Giới thiệu
**Crime Alert Web** là ứng dụng web giúp cảnh báo tội phạm, cập nhật tin tức pháp luật và danh sách truy nã mới nhất từ các nguồn uy tín (VnExpress, Bộ Công An).

---

## ✨ <b>Tính năng chính</b>

| Tính năng                | Mô tả                                                                 |
|--------------------------|-----------------------------------------------------------------------|
| 🚨 Cảnh báo tội phạm     | Gửi, duyệt, tìm kiếm, lọc, xem bản đồ, chỉnh sửa, xóa cảnh báo        |
| 📝 Báo cáo tội phạm      | Gửi, duyệt, xem, xóa báo cáo tội phạm                                 |
| 👮‍♂️ Truy nã             | Hiển thị, tìm kiếm danh sách người bị truy nã                         |
| 💬 Bình luận & Like      | Bình luận, like/unlike bài viết và bình luận                          |
| 📢 Chia sẻ trải nghiệm   | Gửi, duyệt, xem, xóa bài chia sẻ                                      |
| 📰 Tin tức                | Crawl, hiển thị tin tức pháp luật                                     |
| 🤖 Chatbot AI            | Hỗ trợ AI với 4 model (Gemini, OpenAI, DeepSeek, OpenRouter)         |
| 💬 Hỗ trợ trực tuyến     | Chat real-time giữa user và admin                                     |
| 🔔 Thông báo             | Hệ thống notification cho like, comment, hỗ trợ                      |
| 📊 Dashboard             | Thống kê chi tiết cho admin và user                                   |
| 🗺️ Bản đồ tương tác      | Hiển thị cảnh báo trên bản đồ Leaflet                                 |
| 👤 Tài khoản              | Đăng ký, đăng nhập, xác thực email, đổi mật khẩu, xóa tài khoản      |

---

## 🛠️ <b>Công nghệ sử dụng</b>
- <img src="https://img.shields.io/badge/Laravel-FF2D20?logo=laravel&logoColor=white"/> **Laravel** (PHP framework)
- <img src="https://img.shields.io/badge/MySQL-4479A1?logo=mysql&logoColor=white"/> **MySQL** (hoặc MariaDB)
- <img src="https://img.shields.io/badge/Tailwind_CSS-38B2AC?logo=tailwind-css&logoColor=white"/> **Tailwind CSS** (frontend styling)
- <img src="https://img.shields.io/badge/Leaflet-199900?logo=leaflet&logoColor=white"/> **Leaflet.js** (bản đồ)
- <img src="https://img.shields.io/badge/Guzzle-6DB33F?logo=php&logoColor=white"/> **Guzzle** (HTTP client crawl dữ liệu)
- <img src="https://img.shields.io/badge/Symfony%20Process-000000?logo=symfony&logoColor=white"/> **Symfony Process** (chạy đa tiến trình)
- <img src="https://img.shields.io/badge/Symfony%20DomCrawler-000000?logo=symfony&logoColor=white"/> **Symfony DomCrawler** (phân tích HTML)
- <img src="https://img.shields.io/badge/Spatie%20Permission-000000?logo=laravel&logoColor=white"/> **Spatie Laravel Permission** (phân quyền)
- <img src="https://img.shields.io/badge/AI%20APIs-000000?logo=openai&logoColor=white"/> **AI APIs** (Gemini, OpenAI, DeepSeek, OpenRouter)

---

## ⚙️ <b>Yêu cầu hệ thống</b>
- PHP >= 8.x
- Composer >= 2.x
- Node.js >= 16.x và npm

---

## 🚀 <b>Hướng dẫn cài đặt nhanh</b>

### 1️⃣ Clone và cài đặt
```bash
git clone https://github.com/chi-trung/crime-alert-web.git
cd crime-alert-web
composer install
npm install
```

### 2️⃣ Cấu hình môi trường
```bash
cp .env.example .env
php artisan key:generate
```

### 3️⃣ Database (chọn 1 trong 2 cách)

**Cách A: Dùng Docker (khuyên dùng)**
```bash
docker-compose up -d
```

**Cách B: MySQL có sẵn**
- Cập nhật thông tin database trong `.env`

### 4️⃣ Migrate và chạy
```bash
php artisan migrate
php artisan serve:all
```

> **Lưu ý:** Lệnh `php artisan serve:all` sẽ vừa chạy server vừa tự động crawl dữ liệu.

---

## 🔧 <b>Cấu hình AI APIs (tùy chọn)</b>

Project đã có sẵn API keys cho Chatbot AI. Nếu muốn dùng key riêng, thêm vào `.env`:

```env
GEMINI_API_KEY=your_gemini_api_key
OPENAI_API_KEY=your_openai_api_key
DEEPSEEK_API_KEY=your_deepseek_api_key
OPENROUTER_API_KEY=your_openrouter_api_key
```

---

## 🕵️‍♂️ <b>Các lệnh crawl dữ liệu</b>
```bash
php artisan crawl:news          # Crawl tin tức pháp luật
php artisan crawl:wanted-list   # Crawl danh sách truy nã
```

---

## 📁 <b>Cấu trúc project</b>

```
crime-alert-web/
├── 📁 app/                          # Logic chính của ứng dụng
│   ├── 📁 Console/Commands/         # Artisan commands
│   ├── 📁 Http/Controllers/         # Controllers
│   ├── 📁 Models/                   # Eloquent models
│   ├── 📁 Notifications/            # Notification classes
│   └── 📁 Providers/                # Service providers
├── 📁 resources/views/              # Blade templates
├── 📁 routes/                       # Route definitions
├── 📁 database/migrations/          # Database migrations
├── 📁 config/                       # Configuration files
├── 📁 public/                       # Public assets
├── 📁 storage/                      # Storage files
├── 📁 tests/                        # Test files
├── composer.json                    # Composer dependencies
├── package.json                     # NPM dependencies
├── docker-compose.yml              # Docker configuration
└── README.md                        # Project documentation
```

### 🔍 **Mô tả các thư mục chính:**
- **`app/`**: Logic chính của ứng dụng Laravel
- **`resources/views/`**: Giao diện người dùng (Blade templates)
- **`routes/`**: Định nghĩa các route của ứng dụng
- **`database/migrations/`**: Cấu trúc cơ sở dữ liệu

---

## ⚠️ <b>Lưu ý quan trọng</b>

<div align="center">

<table>
  <tr>
    <td width="40" align="center">🚫</td>
    <td><b>Không override lệnh <code>php artisan serve</code></b> trong <code>routes/console.php</code> để tránh lỗi server.</td>
  </tr>
  <tr>
    <td width="40" align="center">🔄</td>
    <td>Dùng lệnh <b><code>php artisan serve:all</code></b> để chạy server + crawl tự động.</td>
  </tr>
  <tr>
    <td width="40" align="center">🤖</td>
    <td>Chatbot AI đã có sẵn API keys, sẵn sàng sử dụng.</td>
  </tr>
  <tr>
    <td width="40" align="center">🛠️</td>
    <td>Nếu gặp lỗi, kiểm tra lại cấu hình <code>.env</code> và database.</td>
  </tr>
</table>

</div>

---

## 🤝 <b>Đóng góp & phát triển</b>
- Fork, tạo branch mới và gửi pull request nếu muốn đóng góp code.
- Nếu có vấn đề, vui lòng tạo issue trên GitHub.
