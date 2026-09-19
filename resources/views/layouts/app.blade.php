<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Crime Alert Web') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha384-blOohCVdhjmtROpu8+CfTnUWham9nkX7P7OZQMst+RUnhtoY/9qemFAkIKOYxDI3" crossorigin="anonymous">
        <style>
            body, .font-sans, .navbar, .btn, .form-control, .card, .alert, .dropdown-menu {
                font-family: 'Inter', 'Roboto', 'Nunito', Arial, sans-serif !important;
            }
            .swal2-container { z-index: 20000 !important; }
        </style>

        <!-- Bootstrap 5 -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    </head>
    <body class="font-sans antialiased">
        {{-- Issue #404: a keyboard user had to tab through the whole nav
             (three links, the category dropdown, up to three admin links, the
             notification bell and the profile dropdown) before reaching any
             page content on every single page load. The skip link jumps
             straight to #main-content; tabindex="-1" makes the main element
             focusable programmatically WITHOUT adding a redundant stop to the
             tab order. WCAG 2.4.1 (Level A). --}}
        <a href="#main-content" class="skip-link">Bỏ qua đến nội dung</a>
        <div class="min-h-screen bg-gray-100">
            @if(empty($hideMenu))
                @include('layouts.navigation')
            @endif

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main id="main-content" tabindex="-1">
                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="bg-white border-top mt-5">
                <div class="container py-4">
                    <div class="row align-items-center">
                        <div class="col-md-6 text-center text-md-start">
                            <p class="mb-0 text-muted">
                                <strong>Hệ thống cảnh báo tội phạm</strong>
                            </p>
                        </div>
                        <div class="col-md-6 text-center text-md-end">
                            <p class="mb-0 text-muted">
                                © 2025 <strong>Nhóm 5</strong>. Tất cả các quyền được bảo lưu.
                                <a href="https://chi-trung.github.io/CV/" target="_blank" rel="noopener" class="text-decoration-none ms-2">
                                    Về chúng tôi
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
        <!-- Bootstrap 5 JS bundle (for dropdown, modal, v.v.) -->
        {{-- Issue #327: pinned but integrity-less; hashed like the #221
             sweetalert2 sibling above (no CSP ships in this repo, so SRI is
             the only CDN integrity layer). --}}
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <!-- SweetAlert2 CDN -->
        {{-- Issue #221: was sweetalert2@11 — jsdelivr resolves the bare major
             to the newest v11.x on every render (26.x today), so the whole
             layout silently executed upstream code churn with no SRI defense.
             Pinned to the exact build verified byte-identical to what the @11
             URL served at fix time; #167's geocoder tags are the precedent. --}}
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js" integrity="sha384-nLoOnA/BDh8A/jxqtckg4DumuCGOBYUnNJLZdQz/zfYNp3wcjGSoWTAzgko06G/2" crossorigin="anonymous"></script>
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
                        // Issue #210: this is the app's ONLY form-reject
                        // listener. /admin/alerts used to register two more
                        // byte-identical copies (an inline block in
                        // alerts/admin_index.blade.php plus
                        // public/js/alerts_admin_index.js), but
                        // sweetalert2@11 is a singleton: each fire() destroys
                        // the previous instance and resolves its promise with
                        // isDismissed, so only this layout dialog ever
                        // rendered and the page-specific wording shipped —
                        // twice — was unreachable. Alert pages now pass their
                        // own wording through the optional data attributes
                        // below instead of stacking listeners.
                        Swal.fire({
                            title: form.dataset.rejectTitle || 'Bạn có chắc chắn muốn từ chối bài này?',
                            text: form.dataset.rejectText || 'Sau khi từ chối, bài sẽ không được hiển thị công khai!',
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
                // Issue #353: a manual #mainNavbar / .navbar-toggler fallback
                // for a Bootstrap hamburger used to live here. That markup was
                // replaced by layouts/navigation.blade.php's own .modern-nav,
                // which ships its own button.mobile-toggle and its own handler
                // — neither id nor class existed anywhere in a rendered page, so
                // the guard below never passed. Removed alongside the !important
                // #mainNavbar style hack at the bottom of this file.
            });
        </script>

        @if(auth()->check())
        <!-- Chatbot AI Styles -->
        <style>
            /* Chatbot Toggle Button */
            .chatbot-toggle {
                position: fixed;
                bottom: 24px;
                right: 24px;
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                border-radius: 50%;
                color: white;
                font-size: 24px;
                cursor: pointer;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                transition: all 0.3s ease;
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .chatbot-toggle:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.2);
                background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            }
            
            .chatbot-toggle.active {
                background: #e74c3c;
            }
            
            /* Chatbot Container */
            .chatbot-container {
                position: fixed;
                bottom: 24px;
                right: 24px;
                width: 380px;
                height: 520px;
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.15);
                display: none;
                flex-direction: column;
                overflow: hidden;
                z-index: 999;
                animation: slideInUp 0.3s ease-out;
            }
            
            @media (max-width: 480px) {
                .chatbot-container {
                    width: 95vw;
                    height: 80vh;
                    right: 2.5vw;
                    bottom: 10px;
                }
                .chatbot-toggle {
                    right: 20px;
                    bottom: 20px;
                }
            }
            
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Chatbot Header */
            .chatbot-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 16px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            
            .chatbot-header-info {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .chatbot-avatar {
                width: 40px;
                height: 40px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
            }
            
            .chatbot-title {
                font-weight: 600;
                font-size: 16px;
            }
            
            .chatbot-subtitle {
                font-size: 12px;
                opacity: 0.8;
            }
            
            .chatbot-close {
                background: none;
                border: none;
                color: white;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
                opacity: 0.7;
                transition: opacity 0.2s;
            }
            
            .chatbot-close:hover {
                opacity: 1;
            }
            
            /* Messages Area */
            .chatbot-messages {
                flex: 1;
                padding: 16px;
                overflow-y: auto;
                background: #f8f9fa;
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .chatbot-messages::-webkit-scrollbar {
                width: 6px;
            }
            
            .chatbot-messages::-webkit-scrollbar-track {
                background: #f1f1f1;
            }
            
            .chatbot-messages::-webkit-scrollbar-thumb {
                background: #c1c1c1;
                border-radius: 3px;
            }
            
            /* Message Bubbles */
            .message {
                display: flex;
                align-items: flex-end;
                gap: 8px;
                max-width: 85%;
                animation: fadeInMessage 0.3s ease-out;
            }
            
            .message.user {
                align-self: flex-end;
                flex-direction: row-reverse;
            }
            
            .message.bot {
                align-self: flex-start;
            }
            
            @keyframes fadeInMessage {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .message-bubble {
                padding: 10px 14px;
                border-radius: 18px;
                font-size: 14px;
                line-height: 1.4;
                word-wrap: break-word;
                position: relative;
            }
            
            .message.user .message-bubble {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-bottom-right-radius: 4px;
            }
            
            .message.bot .message-bubble {
                background: white;
                color: #333;
                border: 1px solid #e9ecef;
                border-bottom-left-radius: 4px;
            }
            
            .message-time {
                font-size: 10px;
                opacity: 0.6;
                margin-top: 4px;
            }
            
            /* Input Area */
            .chatbot-input-area {
                padding: 16px;
                background: white;
                border-top: 1px solid #e9ecef;
                display: flex;
                gap: 12px;
                align-items: center;
            }
            
            .chatbot-input {
                flex: 1;
                border: 2px solid #e9ecef;
                border-radius: 20px;
                padding: 10px 16px;
                font-size: 14px;
                outline: none;
                transition: border-color 0.2s;
            }
            
            .chatbot-input:focus {
                border-color: #667eea;
            }
            
            .chatbot-send {
                width: 40px;
                height: 40px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                border-radius: 50%;
                color: white;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }
            
            .chatbot-send:hover {
                transform: scale(1.05);
            }
            
            .chatbot-send:disabled {
                opacity: 0.5;
                cursor: not-allowed;
                transform: none;
            }
            
            /* Loading Animation */
            .typing-indicator {
                display: flex;
                gap: 4px;
                padding: 12px 16px;
            }
            
            .typing-dot {
                width: 8px;
                height: 8px;
                background: #667eea;
                border-radius: 50%;
                animation: typingAnimation 1.4s infinite ease-in-out;
            }
            
            .typing-dot:nth-child(1) { animation-delay: -0.32s; }
            .typing-dot:nth-child(2) { animation-delay: -0.16s; }
            
            @keyframes typingAnimation {
                0%, 80%, 100% {
                    transform: scale(0);
                    opacity: 0.5;
                }
                40% {
                    transform: scale(1);
                    opacity: 1;
                }
            }
            
            /* Welcome Message */
            .welcome-message {
                text-align: center;
                padding: 20px;
                color: #6c757d;
                font-size: 14px;
            }
            
            .welcome-message .icon {
                font-size: 48px;
                margin-bottom: 12px;
                color: #667eea;
            }

            /* Issue #404: off-screen until focused. display:none would remove
               the link from the tab order entirely and defeat the point — it
               must be reachable, just invisible until it is used. */
            .skip-link {
                position: absolute;
                left: -9999px;
                top: 0;
                z-index: 30000;
                padding: 0.75rem 1.25rem;
                background: #4f46e5;
                color: #fff;
                font-weight: 600;
                border-radius: 0 0 0.5rem 0;
                text-decoration: none;
            }
            .skip-link:focus {
                left: 0;
            }
        </style>

        <!-- Chatbot Toggle Button -->
        {{-- Issue #400: title is a tooltip, not an accessible name — the button
             is a bare robot glyph, so it announced as an unnamed button. --}}
        <button class="chatbot-toggle" id="chatbotToggle" aria-label="Mở cửa sổ chat với trợ lý AI" aria-expanded="false" aria-haspopup="dialog">
            <i class="fas fa-robot" aria-hidden="true"></i>
        </button>

        <!-- Chatbot Container -->
        {{-- Issue #400: a shown/hidden container needs dialog semantics to be
             announced as a named container instead of a pile of divs. --}}
        <div class="chatbot-container" id="chatbotContainer" role="dialog" aria-modal="true" aria-label="Trợ lý AI">
            <!-- Header -->
            <div class="chatbot-header">
                <div class="chatbot-header-info">
                    <div class="chatbot-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div>
                        <div class="chatbot-title">Trợ lý AI</div>
                        <div class="chatbot-subtitle">Hỗ trợ 24/7</div>
                    </div>
                </div>
                <button class="chatbot-close" id="chatbotClose" aria-label="Đóng cửa sổ chat">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <!-- Messages Area -->
            {{-- Issue #402: sendMessage() writes the AI's reply into this node,
                 but it was not a live region — the reply appeared in silence
                 even after #400 gave the container dialog semantics. --}}
            <div class="chatbot-messages" id="chatbotMessages" role="log" aria-live="polite" aria-label="Hội thoại với trợ lý AI">
                <div class="welcome-message">
                    <div class="icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <p><strong>Xin chào!</strong><br>
                    Tôi là trợ lý AI của bạn.<br>
                    Hãy hỏi tôi về an ninh, cảnh báo hoặc bất kỳ thắc mắc nào!</p>
                </div>
            </div>

            <!-- Input Area -->
            <div class="chatbot-input-area">
                <input
                    type="text"
                    class="chatbot-input"
                    id="chatbotInput"
                    placeholder="Nhập câu hỏi của bạn..."
                    autocomplete="off"
                    maxlength="2000"
                    aria-label="Câu hỏi cho trợ lý ảo"
                >
                <button class="chatbot-send" id="chatbotSend" type="button" aria-label="Gửi câu hỏi">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <!-- Chatbot JavaScript -->
        <script>
            class ChatbotAI {
                constructor() {
                    this.isOpen = false;
                    this.isLoading = false;
                    this.messageHistory = [];
                    this.init();
                }

                init() {
                    this.bindEvents();
                    this.loadMessageHistory();
                }

                bindEvents() {
                    const toggle = document.getElementById('chatbotToggle');
                    const close = document.getElementById('chatbotClose');
                    const send = document.getElementById('chatbotSend');
                    const input = document.getElementById('chatbotInput');

                    toggle?.addEventListener('click', () => this.toggleChat());
                    close?.addEventListener('click', () => this.closeChat());
                    send?.addEventListener('click', () => this.sendMessage());
                    
                    input?.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            this.sendMessage();
                        }
                    });

                    // Close on outside click
                    document.addEventListener('click', (e) => {
                        const container = document.getElementById('chatbotContainer');
                        const toggle = document.getElementById('chatbotToggle');

                        if (this.isOpen && !container?.contains(e.target) && !toggle?.contains(e.target)) {
                            this.closeChat();
                        }
                    });

                    // Issue #400: Escape closes the dialog. Every other panel
                    // in this app closes on Escape; the chatbot was the one
                    // keyboard-only path that could not close it.
                    document.addEventListener('keydown', (e) => {
                        if (this.isOpen && (e.key === 'Escape' || e.key === 'Esc')) {
                            e.preventDefault();
                            this.closeChat();
                        }
                    });
                }

                toggleChat() {
                    this.isOpen ? this.closeChat() : this.openChat();
                }

                openChat() {
                    const container = document.getElementById('chatbotContainer');
                    const toggle = document.getElementById('chatbotToggle');

                    if (container && toggle) {
                        container.style.display = 'flex';
                        toggle.classList.add('active');
                        toggle.style.display = 'none';
                        toggle.setAttribute('aria-expanded', 'true');
                        this.isOpen = true;

                        // Issue #400: move focus into the dialog on open. The
                        // 300ms delay existed to let the CSS transition finish,
                        // but focus does not need to wait for it — an immediate
                        // focus is what a keyboard user is waiting on.
                        document.getElementById('chatbotInput')?.focus();
                    }
                }

                closeChat() {
                    const container = document.getElementById('chatbotContainer');
                    const toggle = document.getElementById('chatbotToggle');

                    if (container && toggle) {
                        container.style.display = 'none';
                        toggle.classList.remove('active');
                        toggle.style.display = '';
                        toggle.setAttribute('aria-expanded', 'false');
                        this.isOpen = false;

                        // Issue #400: return focus to the trigger so a keyboard
                        // user is not stranded on a hidden container's last
                        // focused element.
                        toggle.focus();
                    }
                }

                async sendMessage() {
                    if (this.isLoading) return;
                    
                    const input = document.getElementById('chatbotInput');
                    const message = input?.value?.trim();
                    
                    if (!message) return;

                    // Add user message
                    this.addMessage(message, 'user');
                    input.value = '';
                    
                    // Show loading
                    this.showLoading();
                    this.isLoading = true;

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                        const response = await fetch('{{ route('chatbot.ask') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                // Issue #295: without an explicit Accept the
                                // browser sends */*, expectsJson() is false,
                                // and the central #207 renderer never fires —
                                // a stale session got the middleware's 302 to
                                // the HTML login page, fetch followed it
                                // transparently, and the JSON below failed to
                                // parse into the generic dead bubble. This
                                // header is what makes #207's {success,
                                // message, redirect} contract reachable from
                                // this widget, same as the like clients
                                // (alerts_show.js / experiences_show.js).
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ question: message })
                        });

                        if (!response.ok) {
                            // Issue #208: the old shape threw on !response.ok
                            // BEFORE reading the body, so every server-side
                            // error message — notably #190's 403 "xác thực
                            // email" JSON, which the controller emits
                            // deliberately — was discarded and the catch
                            // always rendered the generic connection-error
                            // bubble. A freshly registered (auto-logged-in,
                            // unverified) user was told to "retry later" for
                            // a task that is actually email verification,
                            // and each retry burned the 20,1,chatbot throttle
                            // lane. Parse the body first and surface its
                            // message when present; the generic line stays
                            // only for real transport failures.
                            const data = await response.json().catch(() => null);
                            // Issue #295: a 401 (expired session) now carries
                            // redirect per #207 — follow it like the sibling
                            // clients instead of showing a bubble with no way
                            // out. Checked before message because the 401 body
                            // carries both keys and redirect is the action.
                            if (data && data.redirect) {
                                this.hideLoading();
                                window.location.href = data.redirect;
                                return;
                            }
                            if (data && data.message) {
                                this.hideLoading();
                                this.addMessage(data.message, 'bot', true);
                                return;
                            }

                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }

                        const data = await response.json();
                        
                        // Hide loading and show response
                        this.hideLoading();
                        this.addMessage(data.answer || 'Xin lỗi, tôi không thể trả lời lúc này.', 'bot');
                        
                    } catch (error) {
                        console.error('Chatbot error:', error);
                        this.hideLoading();
                        this.addMessage('Xin lỗi, đã xảy ra lỗi kết nối. Vui lòng thử lại sau.', 'bot', true);
                    } finally {
                        this.isLoading = false;
                    }
                }

                addMessage(content, type, isError = false) {
                    const messagesContainer = document.getElementById('chatbotMessages');
                    if (!messagesContainer) return;

                    // Remove welcome message if exists
                    const welcomeMsg = messagesContainer.querySelector('.welcome-message');
                    if (welcomeMsg) {
                        welcomeMsg.remove();
                    }

                    const messageDiv = document.createElement('div');
                    messageDiv.className = `message ${type}`;
                    
                    const bubbleDiv = document.createElement('div');
                    bubbleDiv.className = 'message-bubble';
                    bubbleDiv.innerHTML = this.formatMessage(content);
                    
                    if (isError) {
                        bubbleDiv.style.color = '#e74c3c';
                        bubbleDiv.style.borderColor = '#e74c3c';
                    }
                    
                    messageDiv.appendChild(bubbleDiv);
                    messagesContainer.appendChild(messageDiv);
                    
                    // Scroll to bottom
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;

                    // Keep the in-memory trail. Issue #355: saveMessageHistory()
                    // used to slice the last 50 here and then discard the
                    // result — no store was ever written (no localStorage, by
                    // requirement), so the method saved nothing while its name
                    // promised persistence. Removed; the push stays because a
                    // future store would want the trail already collected.
                    this.messageHistory.push({ content, type, timestamp: Date.now() });
                }

                showLoading() {
                    const messagesContainer = document.getElementById('chatbotMessages');
                    if (!messagesContainer) return;

                    const loadingDiv = document.createElement('div');
                    loadingDiv.className = 'message bot';
                    loadingDiv.id = 'chatbot-loading';
                    
                    const bubbleDiv = document.createElement('div');
                    bubbleDiv.className = 'message-bubble';
                    
                    const typingDiv = document.createElement('div');
                    typingDiv.className = 'typing-indicator';
                    typingDiv.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
                    
                    bubbleDiv.appendChild(typingDiv);
                    loadingDiv.appendChild(bubbleDiv);
                    messagesContainer.appendChild(loadingDiv);
                    
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }

                hideLoading() {
                    const loadingDiv = document.getElementById('chatbot-loading');
                    if (loadingDiv) {
                        loadingDiv.remove();
                    }
                }

                formatMessage(content) {
                    // Basic HTML sanitization and formatting
                    return content
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/\n/g, '<br>')
                        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                        .replace(/\*(.*?)\*/g, '<em>$1</em>');
                }

                loadMessageHistory() {
                    try {
                        // Note: Not using localStorage as per requirements
                        // Chat starts fresh each time, which is acceptable
                        this.messageHistory = [];
                    } catch (error) {
                        console.warn('Could not load chat history:', error);
                        this.messageHistory = [];
                    }
                }
            }

            // Initialize chatbot when DOM is ready
            document.addEventListener('DOMContentLoaded', function() {
                new ChatbotAI();
            });
        </script>
        @endif

        @yield('scripts')
        <style>
        .chatbot-header, .chatbot-close {
            z-index: 2001 !important;
        }
        </style>
    </body>
</html>