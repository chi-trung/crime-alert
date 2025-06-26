<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cảnh Báo Tội Phạm - An Toàn Cộng Đồng</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
            background: #0a0a0a;
        }

        /* Animated Background */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, 
                #1a1a2e 0%, 
                #16213e 25%, 
                #0f0f23 50%, 
                #1a1a2e 75%, 
                #16213e 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -3;
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            opacity: 0.6;
        }

        .particle {
            position: absolute;
            background: rgba(255, 69, 0, 0.3);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        .particle:nth-child(odd) {
            background: rgba(255, 20, 147, 0.3);
            animation-duration: 25s;
        }

        .particle:nth-child(3n) {
            background: rgba(0, 191, 255, 0.3);
            animation-duration: 30s;
        }

        /* Grid overlay */
        .grid-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -1;
            animation: gridMove 20s linear infinite;
        }

        /* Header - Fixed z-index issue */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 1.2rem 2rem;
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(15px);
            border-bottom: 2px solid rgba(255, 69, 0, 0.3);
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 900;
            color: #ff4500;
            text-shadow: 0 0 15px rgba(255, 69, 0, 0.6);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 1001;
        }

        .logo:hover {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            z-index: 1001;
        }

        .nav-link {
            color: #fff;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border: 2px solid transparent;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 69, 0, 0.3), transparent);
            transition: left 0.5s;
        }

        .nav-link:hover::before {
            left: 100%;
        }

        .nav-link:hover {
            border-color: #ff4500;
            box-shadow: 0 0 20px rgba(255, 69, 0, 0.4);
            transform: translateY(-2px);
            background: rgba(255, 69, 0, 0.1);
        }

        /* Main Content - Fixed padding to account for header */
        .main-content {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6rem 2rem 2rem; /* Added top padding for fixed header */
            position: relative;
        }

        .content-container {
            max-width: 1200px;
            text-align: center;
            z-index: 10;
        }

        .hero-title {
            font-size: clamp(3rem, 8vw, 7rem);
            font-weight: 900;
            color: #fff;
            margin-bottom: 1.5rem;
            text-shadow: 
                0 0 20px rgba(255, 69, 0, 0.4),
                0 0 40px rgba(255, 69, 0, 0.2),
                0 0 60px rgba(255, 69, 0, 0.1);
            animation: titleGlow 3s ease-in-out infinite alternate;
            line-height: 1.1;
            letter-spacing: -2px;
        }

        .hero-subtitle {
            font-size: clamp(1.2rem, 3vw, 1.8rem);
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
            font-weight: 400;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .hero-description {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 3rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.8;
            font-weight: 300;
        }

        .cta-buttons {
            display: flex;
            gap: 2rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 4rem;
        }

        .cta-button {
            padding: 1.2rem 2.5rem;
            font-size: 1.2rem;
            font-weight: 700;
            text-decoration: none;
            border-radius: 50px;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            min-width: 220px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .cta-primary {
            background: linear-gradient(135deg, #ff4500 0%, #ff6347 50%, #ff1493 100%);
            color: white;
            box-shadow: 0 8px 25px rgba(255, 69, 0, 0.4);
            border: none;
        }

        .cta-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }

        .cta-primary:hover::before {
            left: 100%;
        }

        .cta-primary:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(255, 69, 0, 0.6);
        }

        .cta-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .cta-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: #ff4500;
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(255, 69, 0, 0.3);
        }

        /* Features Section */
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2.5rem;
            margin-top: 5rem;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.08);
            padding: 2.5rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ff4500, #ff6347, #ff1493);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .feature-card::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: radial-gradient(circle, rgba(255, 69, 0, 0.1) 0%, transparent 70%);
            transform: translate(-50%, -50%);
            transition: all 0.6s ease;
            border-radius: 50%;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover::after {
            width: 200%;
            height: 200%;
        }

        .feature-card:hover {
            transform: translateY(-15px) rotateX(5deg);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 69, 0, 0.4);
        }

        .feature-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #ff4500;
            text-shadow: 0 0 20px rgba(255, 69, 0, 0.5);
            position: relative;
            z-index: 2;
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 2;
        }

        .feature-text {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.7;
            font-size: 1.1rem;
            position: relative;
            z-index: 2;
        }

        /* Stats Section */
        .stats {
            display: flex;
            justify-content: center;
            gap: 5rem;
            margin: 5rem 0;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
            position: relative;
        }

        .stat-item::before {
            content: '';
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #ff4500, #ff1493);
            border-radius: 2px;
        }

        .stat-number {
            font-size: 4rem;
            font-weight: 900;
            color: #ff4500;
            display: block;
            text-shadow: 0 0 20px rgba(255, 69, 0, 0.6);
            margin-top: 1rem;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.2rem;
            margin-top: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }

            .nav {
                flex-direction: row;
                justify-content: space-between;
            }

            .logo {
                font-size: 1.4rem;
            }

            .nav-links {
                gap: 1rem;
            }

            .nav-link {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
                min-width: auto;
            }

            .main-content {
                padding: 5rem 1rem 2rem;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
                gap: 1.5rem;
            }

            .cta-button {
                width: 100%;
                max-width: 300px;
            }

            .stats {
                gap: 3rem;
            }

            .stat-number {
                font-size: 2.5rem;
            }

            .features {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        /* Animations */
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        @keyframes titleGlow {
            0% { 
                text-shadow: 
                    0 0 20px rgba(255, 69, 0, 0.4),
                    0 0 40px rgba(255, 69, 0, 0.2);
            }
            100% { 
                text-shadow: 
                    0 0 30px rgba(255, 69, 0, 0.6), 
                    0 0 50px rgba(255, 69, 0, 0.4),
                    0 0 70px rgba(255, 69, 0, 0.2);
            }
        }

        /* Pulse effect for emergency button */
        @keyframes pulse {
            0% { 
                box-shadow: 0 8px 25px rgba(255, 69, 0, 0.4);
                transform: scale(1);
            }
            50% { 
                box-shadow: 0 8px 35px rgba(255, 69, 0, 0.8);
                transform: scale(1.02);
            }
            100% { 
                box-shadow: 0 8px 25px rgba(255, 69, 0, 0.4);
                transform: scale(1);
            }
        }

        .cta-primary {
            animation: pulse 3s infinite;
        }

        /* Smooth entrance animations */
        .hero-title {
            animation: titleGlow 3s ease-in-out infinite alternate, fadeInUp 1s ease-out;
        }

        .hero-subtitle {
            animation: fadeInUp 1s ease-out 0.3s both;
        }

        .hero-description {
            animation: fadeInUp 1s ease-out 0.6s both;
        }

        .cta-buttons {
            animation: fadeInUp 1s ease-out 0.9s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="background"></div>
    <div class="grid-overlay"></div>
    <div class="particles" id="particles"></div>

    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <a href="#" class="logo">🚨 CrimeAlert</a>
            <div class="nav-links">
                <a href="http://127.0.0.1:8000/login" class="nav-link">Đăng nhập</a>
                <a href="http://127.0.0.1:8000/register" class="nav-link">Đăng ký</a>
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
                <a href="#" class="cta-button cta-primary">🚨 BÁO CÁO KHẨN CẤP</a>
                <a href="#" class="cta-button cta-secondary">🗺️ XEM BẢN ĐỒ AN NINH</a>
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
                    <div class="feature-icon">📱</div>
                    <h3 class="feature-title">Báo cáo nhanh</h3>
                    <p class="feature-text">
                        Báo cáo sự cố chỉ với vài thao tác đơn giản. 
                        Thông tin được gửi ngay lập tức đến cơ quan chức năng và cộng đồng.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🗺️</div>
                    <h3 class="feature-title">Bản đồ an ninh</h3>
                    <p class="feature-text">
                        Xem bản đồ thời gian thực các vụ việc trong khu vực. 
                        Cập nhật liên tục để bạn luôn nắm bắt tình hình an ninh.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3 class="feature-title">Cộng đồng kết nối</h3>
                    <p class="feature-text">
                        Kết nối với hàng xóm và cộng đồng địa phương. 
                        Chia sẻ thông tin, hỗ trợ lẫn nhau để tạo môi trường an toàn.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Create floating particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 60;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 5 + 2;
                const startX = Math.random() * window.innerWidth;
                const delay = Math.random() * 25;
                
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = startX + 'px';
                particle.style.animationDelay = delay + 's';
                
                particlesContainer.appendChild(particle);
            }
        }

        // Animate stats numbers
        function animateStats() {
            const statNumbers = document.querySelectorAll('.stat-number');
            
            statNumbers.forEach(stat => {
                const text = stat.textContent;
                if (text.includes('+')) {
                    const finalNumber = parseInt(text.replace('+', ''));
                    let currentNumber = 0;
                    const increment = finalNumber / 80;
                    
                    const timer = setInterval(() => {
                        currentNumber += increment;
                        if (currentNumber >= finalNumber) {
                            stat.textContent = finalNumber + '+';
                            clearInterval(timer);
                        } else {
                            stat.textContent = Math.floor(currentNumber) + '+';
                        }
                    }, 30);
                }
            });
        }

        // Enhanced parallax effect
        function handleParallax() {
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const rate = scrolled * -0.3;
                const background = document.querySelector('.background');
                const particles = document.querySelector('.particles');
                
                background.style.transform = `translateY(${rate}px)`;
                particles.style.transform = `translateY(${rate * 0.5}px)`;
            });
        }

        // Intersection Observer for animations
        function setupIntersectionObserver() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        if (entry.target.classList.contains('stats')) {
                            animateStats();
                        }
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });

            // Observe elements
            document.querySelectorAll('.feature-card, .stats').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(50px)';
                el.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                observer.observe(el);
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            createParticles();
            setupIntersectionObserver();
            handleParallax();

            // Add click effects to buttons
            document.querySelectorAll('.cta-button, .nav-link').forEach(button => {
                button.addEventListener('click', function(e) {
                    // Create ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.4);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        left: ${x}px;
                        top: ${y}px;
                        width: ${size}px;
                        height: ${size}px;
                        pointer-events: none;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });

        // Add ripple animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Smooth scrolling for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>