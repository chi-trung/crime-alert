---

## Tutorial: Hướng dẫn chi tiết cho người mới bắt đầu

### Bước 1: Tải mã nguồn về máy

- Nếu dùng Git:
```bash
git clone <https://github.com/chi-trung/crime-alert-web>
cd crime-alert-web
```
- Hoặc tải file ZIP trên GitHub, giải nén và mở thư mục dự án.

### Bước 2: Cài đặt các package cần thiết
```bash
composer install
npm install
```

### Bước 3: Tạo file môi trường và cấu hình
- Copy file `.env.example` thành `.env`:
```bash
cp .env.example .env
```
- Mở file `.env` và chỉnh sửa các thông số:
  - Kết nối database (DB_DATABASE, DB_USERNAME, DB_PASSWORD)
- Tạo key ứng dụng:
```bash
php artisan key:generate
```

### Bước 4: Tạo database và migrate
- Tạo database trống (nếu chưa có) trong MySQL hoặc MariaDB.
- Chạy migration:
```bash
php artisan migrate
```

### Bước 5: Build frontend (nếu dùng Vite/Tailwind)
```bash
npm run dev
```

### Bước 6: Chạy server và crawl dữ liệu tự động
- Chạy lệnh:
```bash
php artisan serve:all
```
- Truy cập: http://127.0.0.1:8000
- Lệnh này sẽ tự động crawl tin tức và danh sách truy nã mới nhất.

### Bước 7: Các lệnh crawl thủ công (nếu muốn)
- Chỉ crawl tin tức:
```bash
php artisan crawl:news
```
- Chỉ crawl danh sách truy nã:
```bash
php artisan crawl:wanted-list
```

---

## Lưu ý bổ sung
- Nếu gặp lỗi không vào được server, hãy kiểm tra lại file `routes/console.php` không được override lệnh `serve`.
- Nếu cần hỗ trợ, hãy tạo issue hoặc liên hệ quản trị repo.
