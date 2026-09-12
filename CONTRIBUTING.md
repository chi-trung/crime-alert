# Đóng góp vào Crime Alert Web

Cảm ơn bạn đã quan tâm! Project này làm việc theo quy trình GitHub Flow.

## Quy trình làm việc (bắt buộc)

```
Issue → Branch → Code → Commit → Push → Pull Request → Code Review → Merge
```

1. **Issue trước, code sau.** Mọi thay đổi đều phải có issue theo dõi
   (bug → label `bug`, tính năng mới → `enhancement`, bảo mật → `security`…).
   Nếu là sửa lỗi nhỏ (< 10 dòng) có thể tự tạo issue rồi làm luôn.
2. **Branch** đặt tên theo loại + số issue:
   - `fix/<số-issue>-<mô-tắn-ngắn>` — sửa lỗi
   - `feat/<số-issue>-<mô-tắn-ngắn>` — tính năng mới
   - `security/<số-issue>-<mô-tắn-ngắn>` — vá bảo mật
   - `refactor/<số-issue>-<mô-tắn-ngắn>` — cải tiến mã
   - `chore/<số-issue>-<mô-tắn-ngắn>` — linh tinh (docs, CI, deps)
3. **Commit** theo [Conventional Commits](https://www.conventionalcommits.org/):
   `fix(support): escape chat bubbles to prevent stored XSS (#2)`
4. Mỗi PR: nhỏ, một chủ đề, có mô tả linked issue (`Fixes #123`).
5. **CI phải xanh** (Pint + PHPUnit, chạy trên cả SQLite và MySQL) trước khi
   xin review.
6. **Không merge khi chưa review.** Chủ repo review; nếu là repo nhóm, cần
   ≥ 1 approval.

## Bắt đầu phát triển

```bash
git clone https://github.com/chi-trung/crime-alert.git
cd crime-alert
composer install
composer setup          # tạo .env, app key, storage link, migrate
php artisan serve       # http://127.0.0.1:8000
```

- Trang quản trị: `php artisan db:seed --class=AdminUserSeeder` tạo tài khoản
  admin (`admin@crime-alert.local` / `ChangeMe!123` — ĐỔI MẬT KHẨU NGAY).
- Cấu hình AI keys trong `.env` (xem `.env.example`). **Không bao giờ hardcode
  key vào source.**

## Kiểm tra trước khi commit

```bash
vendor/bin/pint          # format PHP theo chuẩn Laravel
php artisan test         # chạy PHPUnit
```

## Cấu trúc thư mục chính

| Thư mục | Nội dung |
|---|---|
| `app/Http/Controllers` | HTTP layer — mỏng, delegate xuống Services |
| `app/Services` | Logic nghiệp vụ (thống kê, duyệt bài, crawl…) |
| `app/Models` | Eloquent models |
| `app/Policies` | Phân quyền theo model |
| `resources/views` | Blade templates (Bootstrap 5) |
| `routes/web.php` | Chỉ khai báo route, KHÔNG viết logic |
| `tests/Feature`, `tests/Unit` | Test |
