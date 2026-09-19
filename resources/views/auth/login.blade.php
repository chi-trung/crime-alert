<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập Hệ Thống</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
    {{-- Issue #327: was the bare "https://cdn.tailwindcss.com", which serves
         upstream LATEST (probed: 3.4.17 today) — every login render silently
         executed unreviewed upstream churn with no integrity check, the exact
         risk #221 closed for sweetalert2. Pinned to the exact build verified
         byte-identical to what the bare URL served at fix time, SRI per the
         #167 doctrine (repo ships no CSP, so SRI is the only CDN integrity
         layer). The two-card layout below relies on the utility classes this
         script generates; css/login.css only styles the custom parts
         (.input-group, .wave…; .login-card was removed in #363). --}}
    <script src="https://cdn.tailwindcss.com/3.4.17" integrity="sha384-igm5BeiBt36UU4gqwWS7imYmelpTsZlQ45FZf+XBn9MuJbn4nQr7yx1yFydocC/K" crossorigin="anonymous"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        secondary: '#818cf8',
                        light: '#f5f7ff',
                        dark: '#1e293b'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    {{-- Issue #351: js/login.js was a byte-sibling of the inline toggle at the
         bottom of this file. Two listeners on one click meant the field flipped
         password -> text -> password and the eye never opened. The inline copy
         survives; the extra file is deleted. --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
</head>
<body>
    <div class="flex flex-col md:flex-row w-full max-w-6xl bg-white rounded-2xl overflow-hidden shadow-xl">
        <!-- Left Side - Graphics -->
        <div class="w-full md:w-1/2 bg-gradient-to-br from-primary to-secondary p-10 flex flex-col justify-center relative hidden md:block">
            <div class="text-white text-center z-10">
                <div class="animate-bounce-slow mb-8">
                    <i class="fas fa-lock text-6xl opacity-90"></i>
                </div>
                <h1 class="text-4xl font-bold mb-4">Chào mừng trở lại</h1>
                <p class="text-xl opacity-90">Đăng nhập để tiếp tục trải nghiệm hệ thống của chúng tôi</p>
            </div>
            
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute top-10 right-10 w-24 h-24 rounded-full bg-white opacity-10"></div>
                <div class="absolute bottom-20 left-20 w-32 h-32 rounded-full bg-white opacity-10"></div>
                <div class="absolute top-1/3 left-1/4 w-16 h-16 rounded-full bg-white opacity-10"></div>
            </div>
            
            <div class="wave">
                <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="#FFFFFF"></path>
                    <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" fill="#FFFFFF"></path>
                    <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="#FFFFFF"></path>
                </svg>
            </div>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="w-full md:w-1/2 p-8 md:p-12">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-bold text-gray-800">Đăng nhập tài khoản</h2>
                <p class="text-gray-600 mt-2">Vui lòng nhập thông tin của bạn</p>
            </div>
            
            @if (session('error'))
                <div class="mb-4 text-red-600 font-semibold text-center">
                    {{ session('error') }}
                </div>
            @endif

            @if (
                isset(
                    $errors
                ) && $errors->any())
                <div class="mb-4 text-red-600 font-semibold text-center">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <!-- Email -->
                <div class="input-group">
                    {{-- Issue #396: placeholder was the only label. It vanishes
                         the moment a user starts typing and is not announced
                         as the field's name. The sibling Breeze auth pages
                         already ship <x-input-label for=, so this was the
                         inconsistent one. --}}
                    <label for="email" class="form-label">{{ __('messages.email') }}</label>
                    <div class="input-icon">
                        <i class="far fa-envelope"></i>
                    </div>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="w-full form-input py-3 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Địa chỉ email"
                        required
                        autocomplete="username"
                    >
                </div>

                <!-- Password -->
                <div class="input-group">
                    <label for="password" class="form-label">{{ __('messages.password') }}</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <input 
                        type="password" 
                        id="password" 
                        name="password"
                        class="w-full form-input py-3 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Mật khẩu"
                        required
                        autocomplete="current-password"
                    >
                    <div class="password-toggle" id="passwordToggle">
                        <i class="far fa-eye"></i>
                    </div>
                </div>
                
                <!-- Remember & Forgot Password -->
                <div class="flex items-center justify-between remember-forgot mb-6">
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            id="remember" 
                            name="remember"
                            class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                        >
                        <label for="remember" class="ml-2 block text-sm text-gray-700">Ghi nhớ đăng nhập</label>
                    </div>
                    <a href="{{ route('password.request') }}" class="text-sm text-primary font-medium">Quên mật khẩu?</a>
                </div>
                
                <!-- Login Button -->
                <button 
                    type="submit" 
                    class="w-full btn-login bg-primary hover:bg-primary/90 text-white py-3 px-4 rounded-lg font-medium"
                >
                    Đăng nhập
                </button>
                
                {{-- Issue #343: this "HOẶC ĐĂNG NHẬP VỚI" divider was
                     followed by an empty social-login block — no provider
                     is wired in this app and none is planned. A divider
                     to nowhere promised a button that never appears; the
                     orphan .social-btn styles in login.css die with it.
                     Removed rather than left as a UI that lies. --}}
                
<!-- Register Link -->
                <div class="text-center text-gray-600">
                    Chưa có tài khoản? 
                    <a href="{{ route('register') }}" class="text-primary font-medium hover:underline">Đăng ký ngay</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Issue #351: this toggle used to exist TWICE — once in public/js/login.js
    // (loaded at the top of <body>) and once here. Two listeners fire on one
    // click, so the field flipped password -> text -> password again and the
    // eye never opened. login.js is gone; this inline copy is the surviving
    // one, matching the idiom of register.blade.php's inline handlers.
    (function() {
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordInput = document.getElementById('password');
        if(passwordToggle && passwordInput) {
            passwordToggle.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                // Toggle eye icon
                const eyeIcon = this.querySelector('i');
                if (type === 'password') {
                    eyeIcon.classList.remove('fa-eye-slash');
                    eyeIcon.classList.add('fa-eye');
                } else {
                    eyeIcon.classList.remove('fa-eye');
                    eyeIcon.classList.add('fa-eye-slash');
                }
            });
        }
    })();
    </script>
</body>
</html>