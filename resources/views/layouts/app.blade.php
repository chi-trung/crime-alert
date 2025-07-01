<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body, .font-sans, .navbar, .btn, .form-control, .card, .alert, .dropdown-menu {
                font-family: 'Inter', 'Roboto', 'Nunito', Arial, sans-serif !important;
            }
            .swal2-container { z-index: 20000 !important; }
        </style>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Bootstrap 5 -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                @yield('content')
            </main>
        </div>
        <!-- Bootstrap 5 JS bundle (for dropdown, modal, v.v.) -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- SweetAlert2 CDN -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                @if(session('success'))
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: @json(session('success')),
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                @endif
                @if(session('error'))
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: @json(session('error')),
                        showConfirmButton: false,
                        timer: 3500,
                        timerProgressBar: true
                    });
                @endif
                @if(session('warning'))
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'warning',
                        title: @json(session('warning')),
                        showConfirmButton: false,
                        timer: 3500,
                        timerProgressBar: true
                    });
                @endif
                @if(session('info'))
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'info',
                        title: @json(session('info')),
                        showConfirmButton: false,
                        timer: 3500,
                        timerProgressBar: true
                    });
                @endif
                // SweetAlert2 confirm cho form xóa
                document.querySelectorAll('form.form-delete').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Bạn có chắc chắn muốn xóa?',
                            text: 'Hành động này không thể hoàn tác!',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#e63946',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Xóa',
                            cancelButtonText: 'Hủy',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                form.submit();
                            }
                        });
                    });
                });
                // Hiệu ứng xác nhận cho form-reject
                document.querySelectorAll('form.form-reject').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Bạn có chắc chắn muốn từ chối bài này?',
                            text: 'Sau khi từ chối, bài sẽ không được hiển thị công khai!',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#ffc107',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Từ chối',
                            cancelButtonText: 'Hủy',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                form.submit();
                            }
                        });
                    });
                });
                // Xử lý toggle menu 3 gạch thủ công nếu Bootstrap JS lỗi
                var toggler = document.querySelector('.navbar-toggler');
                var menu = document.getElementById('mainNavbar');
                if (toggler && menu) {
                    toggler.addEventListener('click', function(e) {
                        menu.classList.toggle('show');
                    });
                    // Đóng menu khi click ra ngoài (trên mobile)
                    document.addEventListener('click', function(e) {
                        if (!menu.contains(e.target) && !toggler.contains(e.target)) {
                            menu.classList.remove('show');
                        }
                    });
                }
            });
        </script>
        @if(auth()->check())
        <style>
            #chatbot-toggle-btn {
                position: fixed;
                bottom: 32px;
                right: 32px;
                z-index: 9999;
                width: 64px;
                height: 64px;
                border-radius: 50%;
                background: #0d6efd;
                color: #fff;
                border: none;
                box-shadow: 0 8px 32px rgba(0,0,0,0.18);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 2.3rem;
                cursor: pointer;
                transition: background 0.2s;
            }
            #chatbot-toggle-btn:hover { background: #0b5ed7; }
            #chatbot-box {
                position: fixed;
                bottom: 32px;
                right: 32px;
                z-index: 9999;
                width: 400px;
                min-width: 300px;
                max-width: 98vw;
                height: 560px;
                min-height: 400px;
                max-height: 85vh;
                display: none;
                animation: fadeInUp 0.3s;
            }
            @media (max-width: 600px) {
                #chatbot-box {
                    width: 98vw;
                    right: 1vw;
                    left: 1vw;
                    min-width: unset;
                    height: 80vh;
                    bottom: 0;
                }
            }
            .chatbot-container {
                background: #fff;
                border-radius: 18px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.18);
                padding: 0;
                position: relative;
                display: flex;
                flex-direction: column;
                height: 100%;
                border: 1px solid #f0f0f0;
            }
            .chatbot-header {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 18px 20px 12px 20px;
                border-bottom: 1px solid #f0f0f0;
                background: #f8fafc;
                border-radius: 18px 18px 0 0;
            }
            .chatbot-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #e3f0ff;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                color: #1976d2;
                font-weight: bold;
            }
            .chatbot-title {
                font-weight: 600;
                font-size: 1.1rem;
                color: #222;
            }
            #chatbot-close-btn {
                position: absolute;
                top: 16px;
                right: 18px;
                background: none;
                border: none;
                font-size: 1.3rem;
                color: #888;
                z-index: 2;
                transition: color 0.2s;
            }
            #chatbot-close-btn:hover { color: #e74c3c; }
            #chatbot-messages {
                flex: 1;
                background: #f6f7fa;
                border-radius: 0 0 0 0;
                padding: 18px 16px 8px 16px;
                overflow-y: auto;
                font-size: 15.5px;
                margin-bottom: 0;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .chatbot-msg {
                display: flex;
                align-items: flex-end;
                gap: 8px;
            }
            .chatbot-msg.bot {
                justify-content: flex-start;
            }
            .chatbot-msg.user {
                justify-content: flex-end;
            }
            .chatbot-bubble {
                max-width: 75%;
                padding: 10px 16px;
                border-radius: 16px;
                font-size: 15.5px;
                line-height: 1.5;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                word-break: break-word;
            }
            .chatbot-msg.bot .chatbot-bubble {
                background: #e3f0ff;
                color: #1976d2;
                border-bottom-left-radius: 4px;
            }
            .chatbot-msg.user .chatbot-bubble {
                background: #1976d2;
                color: #fff;
                border-bottom-right-radius: 4px;
            }
            #chatbot-form {
                display: flex;
                gap: 8px;
                align-items: center;
                padding: 14px 16px 16px 16px;
                background: #fff;
                border-radius: 0 0 18px 18px;
                border-top: 1px solid #f0f0f0;
            }
            #chatbot-input {
                border-radius: 12px;
                font-size: 16px;
                padding: 10px 14px;
                border: 1px solid #e0e0e0;
                flex: 1;
            }
            #chatbot-send-btn {
                background: #1976d2;
                color: #fff;
                border: none;
                border-radius: 50%;
                width: 44px;
                height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.3rem;
                box-shadow: 0 2px 8px rgba(25,118,210,0.08);
                transition: background 0.2s;
            }
            #chatbot-send-btn:hover {
                background: #1251a3;
            }
        </style>
        <button id="chatbot-toggle-btn" title="Chat hỗ trợ">
            <i class="fas fa-comments"></i>
        </button>
        <div id="chatbot-box">
            <div class="chatbot-container">
                <div class="chatbot-header">
                    <div class="chatbot-avatar"><i class="fas fa-robot"></i></div>
                    <div class="chatbot-title">Trợ lý AI</div>
                </div>
                <button type="button" id="chatbot-close-btn"><i class="fas fa-times"></i></button>
                <div id="chatbot-messages"></div>
                <form id="chatbot-form">
                    <input type="text" id="chatbot-input" class="form-control" placeholder="Hỏi về an ninh, cảnh báo..." autocomplete="off">
                    <button type="submit" id="chatbot-send-btn"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
        <script>
        const toggleBtn = document.getElementById('chatbot-toggle-btn');
        const chatBox = document.getElementById('chatbot-box');
        const closeBtn = document.getElementById('chatbot-close-btn');
        const msgBox = document.getElementById('chatbot-messages');
        toggleBtn.onclick = () => { chatBox.style.display = 'block'; toggleBtn.style.display = 'none'; }
        closeBtn.onclick = () => { chatBox.style.display = 'none'; toggleBtn.style.display = 'flex'; }
        document.getElementById('chatbot-form').onsubmit = async function(e) {
            e.preventDefault();
            let input = document.getElementById('chatbot-input');
            let question = input.value.trim();
            if (!question) return;
            // Hiển thị tin nhắn user
            msgBox.innerHTML += `<div class='chatbot-msg user'><div class='chatbot-bubble'>${question}</div></div>`;
            input.value = '';
            msgBox.scrollTop = msgBox.scrollHeight;
            // Loading
            let loadingId = 'loading-' + Date.now();
            msgBox.innerHTML += `<div class='chatbot-msg bot' id='${loadingId}'><div class='chatbot-avatar'><i class='fas fa-robot'></i></div><div class='chatbot-bubble'>Đang trả lời...</div></div>`;
            msgBox.scrollTop = msgBox.scrollHeight;
            try {
                let res = await fetch('{{ route('chatbot.openrouter') }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({question})
                });
                let data = await res.json();
                // Xóa loading
                let loadingDiv = document.getElementById(loadingId);
                if (loadingDiv) loadingDiv.remove();
                // Hiển thị tin nhắn bot
                msgBox.innerHTML += `<div class='chatbot-msg bot'><div class='chatbot-avatar'><i class='fas fa-robot'></i></div><div class='chatbot-bubble'>${data.answer}</div></div>`;
                msgBox.scrollTop = msgBox.scrollHeight;
            } catch (err) {
                let loadingDiv = document.getElementById(loadingId);
                if (loadingDiv) loadingDiv.remove();
                msgBox.innerHTML += `<div class='chatbot-msg bot'><div class='chatbot-avatar'><i class='fas fa-robot'></i></div><div class='chatbot-bubble' style='color:red'>Lỗi kết nối hoặc server!</div></div>`;
                msgBox.scrollTop = msgBox.scrollHeight;
            }
        };
        </script>
        @endif
        @yield('scripts')
        <style>
        #mainNavbar {
          display: flex !important;
          opacity: 1 !important;
          visibility: visible !important;
          height: auto !important;
        }
        </style>
    </body>
</html>
