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

## 🎯 <b>Chi tiết các tính năng chính</b>

### 🤖 **Chatbot AI**
- Hỗ trợ 4 model AI: **Gemini**, **OpenAI**, **DeepSeek**, **OpenRouter**
- Trả lời tự động về an ninh, cảnh báo, hướng dẫn cộng đồng
- Tích hợp sẵn API keys, sẵn sàng sử dụng

### 💬 **Hỗ trợ trực tuyến**
- Chat real-time giữa user và admin
- Tạo ticket hỗ trợ, theo dõi trạng thái
- Thông báo tự động khi có tin nhắn mới

### 👍 **Like System**
- Like/unlike cảnh báo, chia sẻ trải nghiệm, bình luận
- Thông báo khi có người like bài viết của bạn
- Đếm số like real-time

### 🔔 **Hệ thống thông báo**
- Thông báo khi có like, comment mới
- Thông báo tin nhắn hỗ trợ
- Đánh dấu đã đọc, xem tất cả thông báo

### 📊 **Dashboard thống kê**
- **Admin:** Thống kê tổng quan, biểu đồ, phân tích xu hướng
- **User:** Thống kê cá nhân, bài viết đã đăng, tương tác

### 🗺️ **Bản đồ tương tác**
- Hiển thị cảnh báo trên bản đồ Leaflet
- Tìm kiếm theo vị trí, lọc theo khu vực
- Tương tác trực quan với dữ liệu địa lý

---

## ✨ <b>Bảng tính năng</b>
| Tính năng                | Mô tả                                                                 |
|--------------------------|-----------------------------------------------------------------------|
| 🚨 Cảnh báo tội phạm     | Gửi, duyệt, tìm kiếm, lọc, xem bản đồ, chỉnh sửa, xóa cảnh báo        |
| 📝 Báo cáo tội phạm      | Gửi, duyệt, xem, xóa báo cáo tội phạm                                 |
| 👮‍♂️ Truy nã             | Hiển thị, tìm kiếm danh sách người bị truy nã                         |
| 💬 Bình luận             | Bình luận, chỉnh sửa, xóa bình luận vào cảnh báo                     |
| 👍 Like system           | Like/unlike cảnh báo, chia sẻ trải nghiệm và bình luận               |
| 📢 Chia sẻ trải nghiệm   | Gửi, duyệt, xem, xóa bài chia sẻ                                      |
| 📰 Tin tức                | Crawl, hiển thị tin tức pháp luật                                     |
| 🤖 Chatbot AI            | Hỗ trợ AI với nhiều model (Gemini, OpenAI, DeepSeek, OpenRouter)     |
| 💬 Hỗ trợ trực tuyến     | Chat trực tuyến giữa user và admin                                    |
| 🔔 Thông báo             | Hệ thống notification cho like, comment, hỗ trợ                      |
| 👤 Tài khoản              | Đăng ký, đăng nhập, xác thực email, đổi mật khẩu, xóa tài khoản      |
| 📊 Dashboard             | Thống kê chi tiết cho admin và user                                   |
| 🗺️ Bản đồ tương tác      | Hiển thị cảnh báo trên bản đồ Leaflet                                 |

---

## 🛠️ <b>Công nghệ sử dụng</b>
- <img src="https://img.shields.io/badge/Laravel-FF2D20?logo=laravel&logoColor=white"/> **Laravel** (PHP framework)
- <img src="https://img.shields.io/badge/MySQL-4479A1?logo=mysql&logoColor=white"/> **MySQL** (hoặc MariaDB)
- <img src="https://img.shields.io/badge/Tailwind_CSS-38B2AC?logo=tailwind-css&logoColor=white"/> **Tailwind CSS** (frontend styling)
- <img src="https://img.shields.io/badge/Leaflet-199900?logo=leaflet&logoColor=white"/> **Leaflet.js** (bản đồ)
- <img src="https://img.shields.io/badge/Guzzle-6DB33F?logo=php&logoColor=white"/> **Guzzle** (HTTP client crawl dữ liệu)
- <img src="https://img.shields.io/badge/Symfony%20Process-000000?logo=symfony&logoColor=white"/> **Symfony Process** (chạy đa tiến trình crawl + serve)
- <img src="https://img.shields.io/badge/Symfony%20DomCrawler-000000?logo=symfony&logoColor=white"/> **Symfony DomCrawler** (phân tích HTML khi crawl)
- <img src="https://img.shields.io/badge/Spatie%20Permission-000000?logo=laravel&logoColor=white"/> **Spatie Laravel Permission** (phân quyền)
- <img src="https://img.shields.io/badge/AI%20APIs-000000?logo=openai&logoColor=white"/> **AI APIs** (Gemini, OpenAI, DeepSeek, OpenRouter)

---

## ⚙️ <b>Yêu cầu hệ thống</b>
- PHP >= 8.x
- Composer >= 2.x
- Node.js >= 16.x và npm

---

## 🚀 <b>Hướng dẫn chạy server</b>

Chạy lệnh sau để khởi động server và tự động crawl dữ liệu:

```bash
php artisan serve:all
```

> Lệnh này sẽ vừa chạy server Laravel vừa tự động crawl tin tức pháp luật và danh sách truy nã mới nhất. Không cần chạy thêm lệnh nào khác.

---

## 📦 <b>Cài đặt các package cần thiết</b>

Chạy các lệnh sau để cài đặt đầy đủ package cho backend và frontend:

```bash
composer install
npm install
```

> **Lưu ý:** Các package cần thiết đã được định nghĩa trong `composer.json` và sẽ tự động được cài đặt khi chạy `composer install`.

### 🔧 **Cấu hình AI APIs (tùy chọn)**

Để sử dụng tính năng Chatbot AI, thêm các API key vào file `.env`:

```env
# Gemini API (đã có sẵn key mặc định)
GEMINI_API_KEY=your_gemini_api_key

# OpenAI API (đã có sẵn key mặc định)
OPENAI_API_KEY=your_openai_api_key

# DeepSeek API (đã có sẵn key mặc định)
DEEPSEEK_API_KEY=your_deepseek_api_key

# OpenRouter API
OPENROUTER_API_KEY=your_openrouter_api_key
```

> **Lưu ý:** Project đã có sẵn các API key mặc định cho Gemini, OpenAI và DeepSeek. Bạn có thể thay thế bằng key của mình nếu muốn.

---

## 📖 <b>Hướng dẫn chi tiết cho người mới bắt đầu</b>

### 1️⃣ Tải mã nguồn về máy
- **Cách 1: Dùng Git (khuyên dùng)**
  ```bash
  git clone https://github.com/chi-trung/crime-alert-web.git
  cd crime-alert-web
  ```
- **Cách 2: Tải file ZIP trên GitHub**
  - Nhấn nút "Code" > "Download ZIP"
  - Giải nén và mở thư mục dự án

> **Lưu ý:** Không cần cài Laravel thủ công. Chỉ cần chạy `composer install`, Laravel và các package sẽ tự động được cài đặt theo composer.json.

### 2️⃣ Cài đặt các package cần thiết
```bash
composer install
npm install
```

### 3️⃣ Tạo file môi trường và cấu hình
```bash
cp .env.example .env
php artisan key:generate
```
- Sửa file `.env` cho đúng thông tin database.

---

### 🐳 **Sử dụng Docker cho MySQL và kết nối với Laravel**

**Khuyến nghị:** Nếu bạn chưa có MySQL trên máy, hãy dùng Docker để khởi động nhanh dịch vụ database.

#### 1️⃣ Khởi động MySQL bằng Docker
Trong thư mục dự án, chạy:
```bash
docker-compose up -d
```
- Lệnh này sẽ tạo và chạy container MySQL với các thông số:
  - **Database:** laravel
  - **User:** laravel
  - **Password:** secret
  - **Root Password:** root
- MySQL sẽ lắng nghe ở cổng `3306` trên máy bạn.

#### 2️⃣ Cấu hình kết nối trong file `.env` của Laravel

Thêm hoặc sửa các dòng sau trong file `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret
```
> **Lưu ý:** Nếu chạy Laravel trong container khác, hãy dùng `DB_HOST=mysql` (tên service trong docker-compose).

#### 3️⃣ Tắt dịch vụ Docker khi không dùng nữa
```bash
docker-compose down
```

---

### 4️⃣ Tạo database và migrate
- Đảm bảo MySQL đã chạy (bằng Docker hoặc cài sẵn)
```bash
php artisan migrate
```

### 5️⃣ Build frontend (nếu dùng Vite/Tailwind)
```bash
npm run dev
```

### 6️⃣ Chạy server và crawl dữ liệu tự động
```bash
php artisan serve:all
```
- Truy cập: http://127.0.0.1:8000

> Lệnh này sẽ vừa chạy server Laravel vừa tự động crawl tin tức pháp luật và danh sách truy nã mới nhất. Không cần chạy thêm lệnh nào khác.

---

## 🕵️‍♂️ <b>Các lệnh crawl dữ liệu</b>
- **Crawl tin tức pháp luật:**
  ```bash
  php artisan crawl:news
  ```
- **Crawl danh sách truy nã:**
  ```bash
  php artisan crawl:wanted-list
  ```

> Nếu muốn tự động hóa crawl khi chạy server, hãy dùng lệnh **php artisan serve:all**.

---

## 🚀 <b>Hướng dẫn sử dụng các tính năng</b>

### 🤖 **Sử dụng Chatbot AI**
1. Truy cập trang chủ hoặc bất kỳ trang nào có chatbot
2. Nhập câu hỏi về an ninh, cảnh báo, pháp luật
3. Chọn model AI (Gemini, OpenAI, DeepSeek, OpenRouter)
4. Nhận câu trả lời tự động

### 💬 **Sử dụng hỗ trợ trực tuyến**
1. Đăng nhập vào tài khoản
2. Vào menu "Hỗ trợ" → "Tạo yêu cầu mới"
3. Nhập tiêu đề và nội dung vấn đề
4. Admin sẽ phản hồi qua chat

### 👍 **Tương tác với Like System**
- Click nút "👍" để like bài viết
- Click lại để unlike
- Số like sẽ cập nhật real-time
- Nhận thông báo khi có người like bài viết của bạn

### 🔔 **Quản lý thông báo**
- Click icon chuông để xem thông báo
- Click "Đánh dấu tất cả đã đọc"
- Click vào thông báo để chuyển đến trang liên quan

---

## ⚠️ **Lưu ý quan trọng**

<div align="center">

<table>
  <tr>
    <td width="40" align="center">🚫</td>
    <td><b>Không override lệnh <code>php artisan serve</code></b> trong <code>routes/console.php</code> hoặc các command khác để tránh lỗi không vào được server.</td>
  </tr>
  <tr>
    <td width="40" align="center">🔄</td>
    <td>Nếu muốn tự động hóa crawl khi chạy server, hãy dùng lệnh <b><code>php artisan serve:all</code></b>.</td>
  </tr>
  <tr>
    <td width="40" align="center">🤖</td>
    <td>Chatbot AI đã có sẵn API keys, có thể sử dụng ngay. Nếu muốn dùng key riêng, cập nhật trong file <code>.env</code>.</td>
  </tr>
  <tr>
    <td width="40" align="center">🛠️</td>
    <td>Nếu gặp lỗi, <b>kiểm tra lại cấu hình <code>.env</code> và database</b>.</td>
  </tr>
</table>

</div>

---

## 🤝 <b>Đóng góp & phát triển</b>
- Fork, tạo branch mới và gửi pull request nếu muốn đóng góp code.
- Nếu có vấn đề, vui lòng tạo issue trên GitHub.
