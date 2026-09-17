<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cảnh Báo Tội Phạm - An Toàn Cộng Đồng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
    <script src="{{ asset('js/welcome.js') }}"></script>
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/128/2592/2592317.png">
</head>
<body>
    <div class="background"></div>
    <div class="grid-overlay"></div>
    <div class="particles" id="particles"></div>

    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <a href="#" class="logo">🚨NHÓM 5</a>
            <div class="nav-links">
                <a href="{{ route('news.index') }}" class="nav-link">Tin tức</a>
                <a href="{{ route('wanted_list.index') }}" class="nav-link">Truy nã</a>
                <a href="{{ route('login') }}" class="nav-link">Đăng nhập</a>
                <a href="{{ route('register') }}" class="nav-link">Đăng ký</a>
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
                <a href="{{ route('alerts.create') }}" class="cta-button cta-primary"><i class="fas fa-paper-plane me-2"></i>🚨gửi báo cáo ngay</a>
                <a href="{{ route('alerts.map') }}" class="cta-button cta-secondary">🗺️ XEM BẢN ĐỒ AN NINH</a>
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
                <div class="feature-card">
                    <a href="/alerts/create" style="text-decoration:none;color:inherit;display:block">
                        <div class="feature-icon">📱</div>
                        <h3 class="feature-title">Báo cáo nhanh</h3>
                        <p class="feature-text">
                            Báo cáo sự cố chỉ với vài thao tác đơn giản. 
                            Thông tin được gửi ngay lập tức đến cơ quan chức năng và cộng đồng.
                        </p>
                    </a>
                </div>
                
                <div class="feature-card">
                    <a href="/alerts/map" style="text-decoration:none;color:inherit;display:block">
                        <div class="feature-icon">🗺️</div>
                        <h3 class="feature-title">Bản đồ an ninh</h3>
                        <p class="feature-text">
                            Xem bản đồ thời gian thực các vụ việc trong khu vực. 
                            Cập nhật liên tục để bạn luôn nắm bắt tình hình an ninh.
                        </p>
                    </a>
                </div>
                
                <div class="feature-card">
                    <a href="/experiences/create" style="text-decoration:none;color:inherit;display:block">
                        <div class="feature-icon">👥</div>
                        <h3 class="feature-title">Cộng đồng kết nối</h3>
                        <p class="feature-text">
                            Kết nối với hàng xóm và cộng đồng địa phương. 
                            Chia sẻ thông tin, hỗ trợ lẫn nhau để tạo môi trường an toàn.
                        </p>
                    </a>
                </div>
                <div class="feature-card">
                    <a href="/notifications" style="text-decoration:none;color:inherit;display:block">
                        <div class="feature-icon">💬</div>
                        <h3 class="feature-title">Hỗ trợ trực tuyến</h3>
                        <p class="feature-text">
                            Đội ngũ hỗ trợ luôn sẵn sàng giải đáp thắc mắc, tiếp nhận thông tin và hỗ trợ bạn 24/7 qua nhiều kênh liên lạc.
                        </p>
                    </a>
                </div>
                <div class="feature-card">
                    <a href="/dashboard" style="text-decoration:none;color:inherit;display:block">
                        <div class="feature-icon">🤖</div>
                        <h3 class="feature-title">Chatbot AI</h3>
                        <p class="feature-text">
                            Trợ lý ảo thông minh giúp bạn tra cứu thông tin, hướng dẫn sử dụng hệ thống và hỗ trợ xử lý tình huống khẩn cấp.
                        </p>
                    </a>
                </div>
                <div class="feature-card">
                    <a href="/notifications" style="text-decoration:none;color:inherit;display:block">
                        <div class="feature-icon">🔔</div>
                        <h3 class="feature-title">Thông báo</h3>
                        <p class="feature-text">
                            Nhận thông báo tức thì về các sự kiện an ninh, cảnh báo mới và cập nhật quan trọng trong khu vực của bạn.
                        </p>
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>