<?php

return [
    'welcome' => 'Chào mừng',
    'login' => 'Đăng nhập',
    'register' => 'Đăng ký',
    'logout' => 'Đăng xuất',
    'profile' => 'Hồ sơ',
    'dashboard' => 'Bảng điều khiển',
    'settings' => 'Cài đặt',
    'save' => 'Lưu',
    'cancel' => 'Hủy',
    'delete' => 'Xóa',
    'edit' => 'Sửa',
    'create' => 'Tạo mới',
    'back' => 'Quay lại',
    'success' => 'Thành công',
    'error' => 'Lỗi',
    'warning' => 'Cảnh báo',
    'info' => 'Thông tin',
    'confirmation' => 'Xác nhận',
    'are_you_sure' => 'Bạn có chắc chắn không?',
    'yes' => 'Có',
    'no' => 'Không',
    'home' => 'Trang chủ',
    'search' => 'Tìm kiếm',
    'submit' => 'Gửi',
    'email' => 'Email',
    'password' => 'Mật khẩu',
    'confirm_password' => 'Xác nhận mật khẩu',
    // Issue #335: Breeze scaffold leaves confirm-password's button on the
    // raw __('Confirm') key, which has no vi entry and silently falls back
    // to the English string. Both keys below close the three untranslated
    // auth pages; the notice covers the "secure area" paragraph too.
    'confirm' => 'Xác nhận',
    'forgot_password_notice' => 'Bạn quên mật khẩu? Không sao cả. Chỉ cần cho chúng tôi biết địa chỉ email của bạn và chúng tôi sẽ gửi một liên kết đặt lại mật khẩu để bạn tự chọn mật khẩu mới.',
    'confirm_secure_area_notice' => 'Đây là khu vực bảo mật của ứng dụng. Vui lòng xác nhận mật khẩu của bạn trước khi tiếp tục.',
    'remember_me' => 'Ghi nhớ đăng nhập',
    'forgot_password' => 'Quên mật khẩu?',
    'reset_password' => 'Đặt lại mật khẩu',
    'send_password_reset_link' => 'Gửi liên kết đặt lại mật khẩu',
    'verify_email' => 'Xác thực Email của bạn',
    'verify_email_sent' => 'Đã gửi lại email xác thực!',
    // Issue #380: this is the verify-email page body, not a "before you
    // continue" prompt — the old wording was inherited from Breeze's English
    // string and never matched the Vietnamese page it was written for.
    'verify_email_notice' => 'Cảm ơn bạn đã đăng ký!<br>Vui lòng kiểm tra email và nhấn vào liên kết xác thực.<br>Nếu bạn chưa nhận được email, hãy nhấn nút bên dưới để gửi lại.',
    'verify_email_resend' => 'Gửi lại email xác thực',
    'verify_email_success' => 'Email của bạn đã được xác thực!',
    // Issue #382: the user reopened an already-consumed verification link.
    // Not an error and not a success — the state did not change.
    'verify_email_already' => 'Email của bạn đã được xác thực trước đó.',
];
