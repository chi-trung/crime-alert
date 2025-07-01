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
