// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.querySelector(`#${fieldId} ~ .password-toggle i`);
    if (field.type === "password") {
        field.type = "text";
        if(icon) icon.classList.remove('fa-eye');
        if(icon) icon.classList.add('fa-eye-slash');
    } else {
        field.type = "password";
        if(icon) icon.classList.remove('fa-eye-slash');
        if(icon) icon.classList.add('fa-eye');
    }
}

// Hiệu ứng đáp ứng yêu cầu mật khẩu
function checkPasswordStrength(password) {
    // Yêu cầu
    const lengthReq = document.getElementById('lengthReq');
    const uppercaseReq = document.getElementById('uppercaseReq');
    const numberReq = document.getElementById('numberReq');
    const specialReq = document.getElementById('specialReq');

    // Icon
    const lengthIcon = lengthReq.querySelector('i');
    const uppercaseIcon = uppercaseReq.querySelector('i');
    const numberIcon = numberReq.querySelector('i');
    const specialIcon = specialReq.querySelector('i');

    // Kiểm tra từng điều kiện
    const isLength = password.length >= 8;
    const isUpper = /[A-Z]/.test(password);
    const isNumber = /[0-9]/.test(password);
    const isSpecial = /[^A-Za-z0-9]/.test(password);

    // Cập nhật hiệu ứng cho từng yêu cầu
    if(isLength) {
        lengthReq.classList.add('valid');
        lengthReq.classList.remove('invalid');
        lengthIcon.className = 'fas fa-check-circle';
    } else {
        lengthReq.classList.remove('valid');
        lengthReq.classList.add('invalid');
        lengthIcon.className = 'far fa-circle';
    }
    if(isUpper) {
        uppercaseReq.classList.add('valid');
        uppercaseReq.classList.remove('invalid');
        uppercaseIcon.className = 'fas fa-check-circle';
    } else {
        uppercaseReq.classList.remove('valid');
        uppercaseReq.classList.add('invalid');
        uppercaseIcon.className = 'far fa-circle';
    }
    if(isNumber) {
        numberReq.classList.add('valid');
        numberReq.classList.remove('invalid');
        numberIcon.className = 'fas fa-check-circle';
    } else {
        numberReq.classList.remove('valid');
        numberReq.classList.add('invalid');
        numberIcon.className = 'far fa-circle';
    }
    if(isSpecial) {
        specialReq.classList.add('valid');
        specialReq.classList.remove('invalid');
        specialIcon.className = 'fas fa-check-circle';
    } else {
        specialReq.classList.remove('valid');
        specialReq.classList.add('invalid');
        specialIcon.className = 'far fa-circle';
    }
}

// Issue #168: Ô xác nhận mật khẩu gọi checkPasswordMatch() mỗi lần gõ
// nhưng hàm này chưa từng được định nghĩa — mỗi phím bấm ném
// ReferenceError và div #passwordMatch vĩnh viễn trống. Nay đã định
// nghĩa: so #password với #password_confirmation, dùng lại đúng cặp
// class .valid/.invalid (xanh #10b981 / đỏ #ef4444) mà
// checkPasswordStrength ở trên đã dùng trong register.css.
// Lưu ý: đây chỉ là chỉ báo UI; cổng thật vẫn là rule 'confirmed' phía
// server trong RegisteredUserController.
function checkPasswordMatch() {
    const password = document.getElementById('password');
    const confirmation = document.getElementById('password_confirmation');
    const match = document.getElementById('passwordMatch');
    if (!password || !confirmation || !match) return;

    // Ô xác nhận còn trống: ẩn chỉ báo (không báo lỗi khi user chưa gõ xong)
    if (confirmation.value === '') {
        match.textContent = '';
        match.classList.remove('valid');
        match.classList.remove('invalid');
        return;
    }

    const isMatch = password.value === confirmation.value;
    match.textContent = isMatch ? 'Mật khẩu khớp' : 'Mật khẩu không khớp';
    if (isMatch) {
        match.classList.add('valid');
        match.classList.remove('invalid');
    } else {
        match.classList.add('invalid');
        match.classList.remove('valid');
    }
}
