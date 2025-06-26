<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="220" alt="Laravel Logo">
</p>

<h1 align="center">🚨 Crime Alert Web 🚨</h1>
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

## ✨ Tính năng
- Crawl tin tức pháp luật tự động từ VnExpress
- Crawl danh sách truy nã từ Bộ Công An
- Hiển thị dữ liệu trực quan trên web
- Tìm kiếm, lọc thông tin nhanh chóng
- Giao diện hiện đại, responsive

## 🛠️ Công nghệ sử dụng
- **Laravel** (PHP framework)
- **MySQL** (hoặc MariaDB)
- **Bootstrap/Tailwind** (frontend)
- **Leaflet.js** (bản đồ)
- **Guzzle** (HTTP client crawl dữ liệu)
- **Symfony Process** (chạy đa tiến trình crawl + serve)

---

## 🚀 Hướng dẫn chạy server

Chạy lệnh sau để khởi động server và tự động crawl dữ liệu:

```bash
php artisan serve:all
```

> Lệnh này sẽ vừa chạy server Laravel vừa tự động crawl tin tức pháp luật và danh sách truy nã mới nhất. Không cần chạy thêm lệnh nào khác.

---

## 📖 Tutorial: Hướng dẫn chi tiết cho người mới bắt đầu

### Bước 1: Tải mã nguồn về máy
- Dùng Git hoặc tải ZIP trên GitHub, giải nén và mở thư mục dự án.

### Bước 2: Cài đặt các package cần thiết
```bash
composer install
npm install
```

### Bước 3: Tạo file môi trường và cấu hình
```bash
cp .env.example .env
php artisan key:generate
```
- Sửa file `.env` cho đúng thông tin database.

### Bước 4: Tạo database và migrate
- Tạo database trống (MySQL/MariaDB)
```bash
php artisan migrate
```

### Bước 5: Build frontend (nếu dùng Vite/Tailwind)
```bash
npm run dev
```

### Bước 6: Chạy server và crawl dữ liệu tự động
```bash
php artisan serve:all
```
- Truy cập: http://127.0.0.1:8000

---

## 🕵️‍♂️ Các lệnh crawl dữ liệu
- **Crawl tin tức pháp luật:**
  ```bash
  php artisan crawl:news
  ```
- **Crawl danh sách truy nã:**
  ```bash
  php artisan crawl:wanted-list
  ```

---

## ⚠️ Lưu ý
- **Không override lệnh `php artisan serve`** trong `routes/console.php` hoặc các command khác để tránh lỗi không vào được server.
- Nếu muốn tự động hóa crawl khi chạy server, hãy dùng lệnh `php artisan serve:all`.
- Nếu gặp lỗi, kiểm tra lại cấu hình `.env` và database.

---

## 🤝 Đóng góp & phát triển
- Fork, tạo branch mới và gửi pull request nếu muốn đóng góp code.
- Nếu có vấn đề, vui lòng tạo issue trên GitHub.
