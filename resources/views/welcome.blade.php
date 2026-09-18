<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cảnh Báo Tội Phạm - An Toàn Cộng Đồng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
    {{-- Issue #359: defer keeps the script's own DOMContentLoaded ordering
        while letting the page render instead of blocking on the file. --}}
    <script src="{{ asset('js/welcome.js') }}" defer></script>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
</head>
<body>
    <div class="background"></div>
    <div class="grid-overlay"></div>
    <div class="particles" id="particles"></div>

    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <a href="{{ url('/') }}" class="logo">CRIME ALERT</a>
            <div class="nav-links">
                <a href="{{ route('news.index') }}" class="nav-link">Tin tức</a>
                <a href="{{ route('wanted_list.index') }}" class="nav-link">Truy nã</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="nav-link">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="nav-link">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="nav-link">Đăng ký</a>
                @endauth
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-container">
            <h1 class="hero-title">CẢNH BÁO TỘI PHẠM</h1>
            <p class="hero-subtitle">BẢO VỆ CỘNG ĐỒNG - AN TOÀN MỌI NHÀ</p>
            <p class="hero-description">
                Hệ thống cảnh báo tội phạm thông minh giúp cộng đồng kết nối, chia sẻ thông tin an ninh
                và bảo vệ lẫn nhau. Cùng nhau xây dựng một môi trường sống an toàn và hòa bình.
            </p>

            <div class="cta-buttons">
                <a href="{{ route('alerts.create') }}" class="cta-button cta-primary">Gửi báo cáo ngay</a>
                <a href="{{ route('alerts.map') }}" class="cta-button cta-secondary">Xem bản đồ an ninh</a>
            </div>

            <div class="stats">
                <div class="stat-item">
                    <span class="stat-number">24/7</span>
                    <div class="stat-label">Giám sát</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">1000+</span>
                    <div class="stat-label">Thành viên</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">50+</span>
                    <div class="stat-label">Khu vực</div>
                </div>
            </div>

            <div class="features">
                @php
                    // Issue #341: the cards hardcoded '/alerts/create' etc. as
                    // plain strings. route() fails loudly if a route is later
                    // renamed, and the two cards below pointed at the wrong
                    // page: 'Hỗ trợ trực tuyến' went to /notifications instead
                    // of the support form, and 'Chatbot AI' went to /dashboard
                    // even though the chatbot is a floating widget on every
                    // authenticated page, not a page of its own.
                    //
                    // Issue #341 (auth gate): news and the wanted list are the
                    // only public ones here. Every other target sits behind
                    // auth middleware, so a guest clicking a card lands on the
                    // login form. That form remembers the intended URL
                    // (AuthenticatedSessionController::redirect()->intended),
                    // so the guest still reaches the page after signing in;
                    // the badge below just sets the expectation first.
                    $features = [
                        [
                            'icon' => '📱',
                            'title' => 'Báo cáo nhanh',
                            'text' => 'Báo cáo sự cố chỉ với vài thao tác đơn giản. Thông tin được gửi ngay lập tức đến cơ quan chức năng và cộng đồng.',
                            'route' => 'alerts.create',
                            'public' => false,
                        ],
                        [
                            'icon' => '🗺️',
                            'title' => 'Bản đồ an ninh',
                            'text' => 'Xem bản đồ thời gian thực các vụ việc trong khu vực. Cập nhật liên tục để bạn luôn nắm bắt tình hình an ninh.',
                            'route' => 'alerts.map',
                            'public' => false,
                        ],
                        [
                            'icon' => '👥',
                            'title' => 'Cộng đồng kết nối',
                            'text' => 'Kết nối với hàng xóm và cộng đồng địa phương. Chia sẻ thông tin, hỗ trợ lẫn nhau để tạo môi trường an toàn.',
                            'route' => 'experiences.create',
                            'public' => false,
                        ],
                        [
                            'icon' => '💬',
                            'title' => 'Hỗ trợ trực tuyến',
                            'text' => 'Đội ngũ hỗ trợ luôn sẵn sàng giải đáp thắc mắc, tiếp nhận thông tin và hỗ trợ bạn 24/7 qua nhiều kênh liên lạc.',
                            'route' => 'support.create',
                            'public' => false,
                        ],
                        [
                            'icon' => '🤖',
                            'title' => 'Chatbot AI',
                            'text' => 'Trợ lý ảo thông minh giúp bạn tra cứu thông tin, hướng dẫn sử dụng hệ thống và hỗ trợ xử lý tình huống khẩn cấp. Đăng nhập rồi bấm nút trợ lý ở góc phải màn hình.',
                            'route' => 'dashboard',
                            'public' => false,
                        ],
                        [
                            'icon' => '🔔',
                            'title' => 'Thông báo',
                            'text' => 'Nhận thông báo tức thì về các sự kiện an ninh, cảnh báo mới và cập nhật quan trọng trong khu vực của bạn.',
                            'route' => 'notifications.index',
                            'public' => false,
                        ],
                    ];
                @endphp

                @foreach($features as $feature)
                    <div class="feature-card">
                        <a href="{{ route($feature['route']) }}" style="text-decoration:none;color:inherit;display:block">
                            <div class="feature-icon">{{ $feature['icon'] }}</div>
                            <h3 class="feature-title">{{ $feature['title'] }}</h3>
                            <p class="feature-text">
                                {{ $feature['text'] }}
                                @if(! $feature['public'] && ! auth()->check())
                                    <span class="feature-guest-hint">Cần đăng nhập</span>
                                @endif
                            </p>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </main>
</body>
</html>
