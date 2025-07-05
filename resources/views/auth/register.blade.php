<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Tài Khoản</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        secondary: '#818cf8',
                        light: '#f5f7ff',
                        dark: '#1e293b',
                        success: '#10b981'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/register.css') }}">
    <script src="{{ asset('js/register.js') }}"></script>
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/128/2592/2592317.png">
</head>
<body>
    <div class="flex flex-col md:flex-row w-full max-w-6xl bg-white rounded-2xl overflow-hidden shadow-xl">
        <!-- Left Side - Graphics -->
        <div class="w-full md:w-1/2 bg-gradient-to-br from-primary to-secondary p-10 flex flex-col justify-center relative hidden md:block">
            <div class="text-white text-center z-10">
                <div class="animate-bounce-slow mb-8">
                    <!-- Icon đã bị xóa -->
                </div>
                <h1 class="text-4xl font-bold mb-4">Tạo tài khoản mới</h1>
                <p class="text-xl opacity-90">Đăng ký để bắt đầu trải nghiệm hệ thống của chúng tôi</p>
            </div>
            
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute top-10 right-10 w-24 h-24 rounded-full bg-white opacity-10"></div>
                <div class="absolute bottom-20 left-20 w-32 h-32 rounded-full bg-white opacity-10"></div>
                <div class="absolute top-1/3 left-1/4 w-16 h-16 rounded-full bg-white opacity-10"></div>
            </div>
            
            <div class="wave">
                <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                    <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="#FFFFFF"></path>
                    <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" fill="#FFFFFF"></path>
                    <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="#FFFFFF"></path>
                </svg>
            </div>
        </div>
        
        <!-- Right Side - Register Form -->
        <div class="w-full md:w-1/2 p-8 md:p-12">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-bold text-gray-800">Đăng ký tài khoản</h2>
                <p class="text-gray-600 mt-2">Vui lòng điền thông tin bên dưới</p>
            </div>
            
            <form method="POST" action="{{ route('register') }}">
                @csrf
                
                <!-- Name -->
                <div class="input-group">
                    <!-- <div class="input-icon">
                        <i class="far fa-user"></i>
                    </div> -->
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        class="w-full form-input py-3 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Họ và tên"
                        value="{{ old('name') }}"
                        required
                        autofocus
                        autocomplete="name"
                    >
                    @error('name')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Email -->
                <div class="input-group">
                    <!-- <div class="input-icon">
                        <i class="far fa-envelope"></i>
                    </div> -->
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="w-full form-input py-3 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Địa chỉ email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="username"
                    >
                    @error('email')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Password -->
                <div class="input-group">
                    <!-- <div class="input-icon">
                        <i class="fas fa-lock"></i>
                    </div> -->
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="w-full form-input py-3 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Mật khẩu"
                        required
                        autocomplete="new-password"
                        oninput="checkPasswordStrength(this.value)"
                    >
                    <!-- <div class="password-toggle" onclick="togglePassword('password')">
                        <i class="far fa-eye"></i>
                    </div> -->
                    <div class="password-strength" id="passwordStrength"></div>
                    <div class="password-requirements" id="passwordRequirements">
                        <div class="requirement" id="lengthReq">
                            <i class="far fa-circle"></i>
                            <span>Ít nhất 8 ký tự</span>
                        </div>
                        <div class="requirement" id="uppercaseReq">
                            <i class="far fa-circle"></i>
                            <span>Ít nhất 1 chữ hoa</span>
                        </div>
                        <div class="requirement" id="numberReq">
                            <i class="far fa-circle"></i>
                            <span>Ít nhất 1 số</span>
                        </div>
                        <div class="requirement" id="specialReq">
                            <i class="far fa-circle"></i>
                            <span>Ít nhất 1 ký tự đặc biệt</span>
                        </div>
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Confirm Password -->
                <div class="input-group">
                    <!-- <div class="input-icon">
                        <i class="fas fa-lock"></i>
                    </div> -->
                    <input 
                        type="password" 
                        id="password_confirmation" 
                        name="password_confirmation" 
                        class="w-full form-input py-3 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Xác nhận mật khẩu"
                        required
                        autocomplete="new-password"
                        oninput="checkPasswordMatch()"
                    >
                    <!-- <div class="password-toggle" onclick="togglePassword('password_confirmation')">
                        <i class="far fa-eye"></i>
                    </div> -->
                    <div id="passwordMatch" class="text-sm mt-1"></div>
                </div>
                
                <!-- Terms and Conditions -->
                <div class="flex items-center mt-4 mb-6">
                    <input 
                        type="checkbox" 
                        id="terms" 
                        name="terms"
                        class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                        required
                    >
                    <label for="terms" class="ml-2 block text-sm text-gray-700">
                        Tôi đồng ý với <a href="#" class="text-primary font-medium hover:underline">Điều khoản dịch vụ</a> và <a href="#" class="text-primary font-medium hover:underline">Chính sách bảo mật</a>
                    </label>
                </div>
                
                <!-- Register Button -->
                <button 
                    type="submit" 
                    class="w-full btn-register bg-primary hover:bg-primary/90 text-white py-3 px-4 rounded-lg font-medium"
                >
                    Đăng ký
                </button>
                
                <!-- Already Registered Link -->
                <div class="text-center text-gray-600 mt-6">
                    Đã có tài khoản? 
                    <a href="{{ route('login') }}" class="text-primary font-medium hover:underline">Đăng nhập ngay</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>