  <nav class="modern-nav">
    <div class="nav-container">
      <!-- Logo/Brand Section -->
      <div class="nav-brand">
        <a href="{{ route('dashboard') }}" class="brand-link">
          <div class="brand-icon">
            <img src="https://cdn-icons-png.flaticon.com/128/2592/2592317.png" alt="Logo" width="32" height="32" />
          </div>
          <span class="brand-text">Trang chủ</span>
        </a>
      </div>

      <!-- Main Navigation -->
      <div class="nav-main">
        <ul class="nav-menu">
          <li class="nav-item {{ request()->routeIs('alerts.map') ? 'active' : '' }}">
            <a href="{{ route('alerts.map') }}" class="nav-link">
              <span class="link-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                  <circle cx="12" cy="10" r="3"></circle>
                </svg>
              </span>
              <span class="link-text">Bản đồ</span>
            </a>
          </li>
          
          <li class="nav-item {{ request()->routeIs('alerts.index') ? 'active' : '' }}">
            <a href="{{ route('alerts.index') }}" class="nav-link">
              <span class="link-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="8" y1="6" x2="21" y2="6"></line>
                  <line x1="8" y1="12" x2="21" y2="12"></line>
                  <line x1="8" y1="18" x2="21" y2="18"></line>
                  <line x1="3" y1="6" x2="3.01" y2="6"></line>
                  <line x1="3" y1="12" x2="3.01" y2="12"></line>
                  <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
              </span>
              <span class="link-text">Danh sách</span>
            </a>
          </li>
          
          @auth
          <li class="nav-item {{ request()->routeIs('alerts.create') ? 'active' : '' }}">
            <a href="{{ route('alerts.create') }}" class="nav-link">
              <span class="link-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="12" y1="5" x2="12" y2="19"></line>
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
              </span>
              <span class="link-text">Gửi cảnh báo</span>
            </a>
          </li>
          @endauth
          
          <!-- More menu items -->
          <li class="nav-item dropdown">
            <a href="javascript:void(0)" class="nav-link dropdown-toggle">
              <span class="link-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="3" width="7" height="7"></rect>
                  <rect x="14" y="3" width="7" height="7"></rect>
                  <rect x="14" y="14" width="7" height="7"></rect>
                  <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
              </span>
              <span class="link-text">Chuyên mục</span>
              
            </a>
            <div class="dropdown-menu">
              <a href="{{ route('news.index') }}" class="dropdown-item {{ request()->routeIs('news.index') ? 'active' : '' }}">
                <span>Tin tức an ninh</span>
              </a>
              <a href="{{ route('experiences.index') }}" class="dropdown-item {{ request()->routeIs('experiences.index') ? 'active' : '' }}">
                <span>Chia sẻ kinh nghiệm</span>
              </a>
              <!--<a href="{{ route('community_alerts.index') }}" class="dropdown-item {{ request()->routeIs('community_alerts.index') ? 'active' : '' }}">
                <span>Cảnh báo cộng đồng</span>
              </a>-->
              <a href="{{ route('wanted_list.index') }}" class="dropdown-item {{ request()->routeIs('wanted_list.index') ? 'active' : '' }}">
                <span>Truy nã</span>
              </a>
              <a href="{{ route('my-history') }}" class="dropdown-item {{ request()->routeIs('my-history') ? 'active' : '' }}">
                <span>Lịch sử</span>
              </a>
            </div>
          </li>
        </ul>
        @auth
          @if(auth()->user()->isAdmin)
            <ul class="nav-menu">
              
              </li>
              <li class="nav-item">
                <a href="{{ route('admin.experiences') }}" class="nav-link">
                  <span class="link-icon">
                    <i class="fas fa-book"></i>
                  </span>
                    <span class="link-text">Bài viết</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{ route('admin.alerts') }}" class="nav-link">
                  <span class="link-icon">
                    <i class="fas fa-chart-bar"></i>
                  </span>
                  <span class="link-text">Báo cáo</span>
                </a>
              </li>
            </ul>
          @endif
        @endauth
      </div>

      <!-- User Section -->
      <div class="nav-user">
        @auth
          <!-- Notifications -->
          <div class="user-notification dropdown">
            @php
              $unreadNotifications = auth()->user()->unreadNotifications()->take(10)->get();
              $unreadCount = $unreadNotifications->count();
            @endphp
            <a href="#" class="notification-icon">
              <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
              <span class="notification-badge" style="{{ $unreadCount > 0 ? '' : 'display:none;' }}">{{ $unreadCount }}</span>
            </a>
            <div class="notification-dropdown">
              <div class="dropdown-header">
                <h4>Thông báo mới</h4>
                <a href="{{ route('notifications.index') }}">Xem tất cả</a>
              </div>
              <div class="notification-list">
                @forelse($unreadNotifications as $notification)
                  <a href="{{ route('notifications.read', $notification->id) }}" class="notification-item">
                    <div class="notification-content">
                      {{ $notification->data['message'] ?? 'Bạn có thông báo mới' }}
                    </div>
                    <div class="notification-time">{{ $notification->created_at->diffForHumans() }}</div>
                  </a>
                @empty
                  <div class="notification-empty">Không có thông báo mới</div>
                @endforelse
              </div>
            </div>
          </div>
          
          <!-- User Profile -->
          <div class="user-profile dropdown">
            <a href="#" class="profile-link">
              <div class="profile-avatar">
                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1, 'UTF-8'), 'UTF-8') }}
              </div>
              <span class="profile-name">{{ auth()->user()->name }}</span>
            </a>
            <div class="profile-dropdown">
              <a href="{{ route('profile.edit') }}" class="dropdown-item">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Hồ sơ cá nhân</span>
              </a>
              <form action="{{ route('logout') }}" method="POST" class="dropdown-item">
                @csrf
                <button type="submit">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                  </svg>
                  <span>Đăng xuất</span>
                </button>
              </form>
            </div>
          </div>
        @else
          <div class="auth-buttons">
            <a href="{{ route('login') }}" class="auth-button login-button">Đăng nhập</a>
            <a href="{{ route('register') }}" class="auth-button register-button">Đăng ký</a>
          </div>
        @endauth
      </div>
      
      <!-- Mobile Toggle -->
      <button class="mobile-toggle" aria-label="Toggle navigation">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>
  </nav>

  <style>
  /* Modern Navigation Styles */
  .modern-nav {
    --primary-color: #4f46e5;
    --primary-hover: #4338ca;
    --text-color: #1f2937;
    --text-light: #6b7280;
    --bg-color: #ffffff;
    --border-color: #e5e7eb;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius-sm: 0.375rem;
    --radius-md: 0.5rem;
    --radius-lg: 0.75rem;
    --transition: all 0.2s ease;
    
    position: sticky;
    top: 0;
    z-index: 50;
    background-color: var(--bg-color);
    box-shadow: var(--shadow-md);
    border-bottom: 1px solid var(--border-color);
  }

  .nav-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 1.5rem;
    display: flex;
    align-items: center;
    height: 4rem;
    position: relative;
  }

  /* Brand Styles */
  .nav-brand {
    margin-right: 2rem;
  }

  .brand-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    color: var(--text-color);
    font-weight: 600;
    font-size: 1.25rem;
  }

  .brand-icon {
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
  }

  .brand-text {
    letter-spacing: -0.025em;
  }

  /* Main Navigation */
  .nav-main {
    flex: 1;
    display: flex;
  }

  .nav-menu {
    display: flex;
    gap: 0.5rem;
    list-style: none;
    margin: 0;
    padding: 0;
  }

  .nav-item {
    position: relative;
  }

  .nav-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--text-color);
    font-size: 0.95rem;
    font-weight: 500;
    transition: var(--transition);
  }

  .nav-link:hover {
    background-color: rgba(79, 70, 229, 0.05);
    color: var(--primary-color);
  }

  .nav-item.active .nav-link {
    color: var(--primary-color);
    background-color: rgba(79, 70, 229, 0.1);
  }

  .link-icon {
    display: flex;
    align-items: center;
  }

  /* Dropdown Styles */
  .dropdown-toggle {
    position: relative;
    padding-right: 1.75rem !important;
  }

  .dropdown-arrow {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
  }

  .dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    min-width: 12rem;
    background-color: var(--bg-color);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    padding: 0.5rem;
    z-index: 10;
    opacity: 0;
    visibility: hidden;
    transform: translateY(0.5rem);
    transition: var(--transition);
  }

  .nav-item:hover .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
  }

  .dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-color);
    font-size: 0.9rem;
    transition: var(--transition);
  }

  .dropdown-item:hover, .dropdown-item.active {
    background-color: rgba(79, 70, 229, 0.1);
    color: var(--primary-color);
  }

  /* User Section */
  .nav-user {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-left: auto;
  }

  .user-notification {
    position: relative;
  }

  .notification-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    color: var(--text-color);
    transition: var(--transition);
    position: relative;
  }

  .notification-icon:hover {
    background-color: rgba(79, 70, 229, 0.1);
    color: var(--primary-color);
  }

  .notification-badge {
    position: absolute;
    top: 0.25rem;
    right: 0.25rem;
    background-color: #ef4444;
    color: white;
    border-radius: 50%;
    width: 1.25rem;
    height: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
  }

  .notification-dropdown {
    position: absolute;
    right: 0;
    top: 100%;
    width: 20rem;
    background-color: var(--bg-color);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    padding: 1rem;
    z-index: 10;
    opacity: 0;
    visibility: hidden;
    transform: translateY(0.5rem);
    transition: var(--transition);
  }

  .user-notification:hover .notification-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
  }

  .dropdown-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
  }

  .dropdown-header h4 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
  }

  .dropdown-header a {
    font-size: 0.85rem;
    color: var(--primary-color);
    text-decoration: none;
  }

  .notification-list {
    max-height: 20rem;
    overflow-y: auto;
  }

  .notification-item {
    display: block;
    padding: 0.75rem;
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-color);
    transition: var(--transition);
  }

  .notification-item:hover {
    background-color: rgba(79, 70, 229, 0.05);
  }

  .notification-content {
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
  }

  .notification-time {
    font-size: 0.75rem;
    color: var(--text-light);
  }

  .notification-empty {
    padding: 1rem;
    text-align: center;
    color: var(--text-light);
    font-size: 0.9rem;
  }

  /* User Profile */
  .user-profile {
    position: relative;
  }

  .profile-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    color: var(--text-color);
    transition: var(--transition);
  }

  .profile-avatar {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50%;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    transition: var(--transition);
  }

  .profile-name {
    font-size: 0.95rem;
    font-weight: 500;
  }

  .profile-dropdown {
    position: absolute;
    right: 0;
    top: 100%;
    min-width: 12rem;
    background-color: var(--bg-color);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    padding: 0.5rem;
    z-index: 10;
    opacity: 0;
    visibility: hidden;
    transform: translateY(0.5rem);
    transition: var(--transition);
  }

  .user-profile:hover .profile-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
  }

  .profile-dropdown .dropdown-item {
    padding: 0.75rem 1rem;
  }

  .profile-dropdown .dropdown-item svg {
    flex-shrink: 0;
  }

  .profile-dropdown button {
    background: none;
    border: none;
    padding: 0;
    margin: 0;
    width: 100%;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    color: var(--text-color);
  }

  /* Auth Buttons */
  .auth-buttons {
    display: flex;
    gap: 0.75rem;
  }

  .auth-button {
    padding: 0.5rem 1rem;
    border-radius: var(--radius-md);
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 500;
    transition: var(--transition);
  }

  .login-button {
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
  }

  .login-button:hover {
    background-color: rgba(79, 70, 229, 0.05);
  }

  .register-button {
    background-color: var(--primary-color);
    color: white;
  }

  .register-button:hover {
    background-color: var(--primary-hover);
  }

  /* Mobile Toggle */
  .mobile-toggle {
    display: none;
    background: none;
    border: none;
    width: 2rem;
    height: 2rem;
    flex-direction: column;
    justify-content: space-around;
    padding: 0;
    cursor: pointer;
  }

  .mobile-toggle span {
    display: block;
    width: 100%;
    height: 2px;
    background-color: var(--text-color);
    transition: var(--transition);
  }

  /* Responsive Styles */
  @media (max-width: 1024px) {
    .nav-container {
      padding: 0 1rem;
    }
    
    .nav-menu {
      gap: 0.25rem;
    }
    
    .nav-link {
      padding: 0.5rem 0.75rem;
    }
  }

  @media (max-width: 768px) {
    .modern-nav {
      height: auto;
      padding: 0.75rem 0;
    }
    
    .nav-container {
      flex-wrap: wrap;
      height: auto;
      padding: 0 1rem;
    }
    
    .nav-brand {
      margin-right: auto;
    }
    
    .mobile-toggle {
      display: flex;
      order: 1;
    }
    
    .nav-main {
      order: 3;
      width: 100%;
      display: none;
      margin-top: 1rem;
    }
    
    .nav-menu {
      flex-direction: column;
      gap: 0.25rem;
    }
    
    .nav-item {
      width: 100%;
    }
    
    .nav-link {
      padding: 0.75rem 1rem;
    }
    
    .dropdown-menu {
      position: static;
      box-shadow: none;
      opacity: 1;
      visibility: visible;
      transform: none;
      display: none;
      padding-left: 1.5rem;
      border-left: 2px solid var(--border-color);
      margin: 0.5rem 0;
    }
    
    .nav-item:hover .dropdown-menu {
      display: block;
    }
    
    .nav-user {
      order: 2;
      margin-left: 1rem;
    }
    
    .profile-name {
      display: none;
    }
    
    .notification-dropdown {
      right: -1rem;
      width: calc(100vw - 2rem);
    }
    
    .profile-dropdown {
      right: -1rem;
    }
    
    /* Active state */
    .nav-container.active .nav-main {
      display: block;
    }
    
    .nav-container.active .mobile-toggle span:nth-child(1) {
      transform: translateY(6px) rotate(45deg);
    }
    
    .nav-container.active .mobile-toggle span:nth-child(2) {
      opacity: 0;
    }
    
    .nav-container.active .mobile-toggle span:nth-child(3) {
      transform: translateY(-6px) rotate(-45deg);
    }
  }

  /* Notification dropdown mobile fix */
  @media (max-width: 600px) {
    .notification-dropdown {
      left: 50% !important;
      right: auto !important;
      transform: translateX(-50%) translateY(0.5rem) !important;
      width: 95vw !important;
      min-width: 0 !important;
      max-width: 98vw !important;
      border-radius: 16px !important;
      padding: 1rem 0.5rem !important;
      box-shadow: 0 8px 32px rgba(0,0,0,0.18) !important;
    }
    .notification-dropdown .dropdown-header,
    .notification-dropdown .notification-list {
      padding-left: 0.5rem !important;
      padding-right: 0.5rem !important;
    }
  }
  </style>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Mobile toggle functionality
    const mobileToggle = document.querySelector('.mobile-toggle');
    const navContainer = document.querySelector('.nav-container');
    
    if (mobileToggle && navContainer) {
      mobileToggle.addEventListener('click', function() {
        navContainer.classList.toggle('active');
      });
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.dropdown-toggle') && !e.target.closest('.dropdown-menu')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
          menu.style.display = 'none';
        });
      }
    });
    
    // Toggle dropdowns on click (for mobile & desktop)
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const menu = this.nextElementSibling;
        if (menu.style.display === 'block') {
          menu.style.display = 'none';
        } else {
          document.querySelectorAll('.dropdown-menu').forEach(m => {
            m.style.display = 'none';
          });
          menu.style.display = 'block';
        }
      });
    });
    
    // Notification badge animation
    const notificationBadge = document.querySelector('.notification-badge');
    if (notificationBadge) {
      notificationBadge.classList.add('animate-pulse');
      setTimeout(() => {
        notificationBadge.classList.remove('animate-pulse');
      }, 2000);
    }

    // Đóng dropdown khi chạm ra ngoài (mobile & desktop)
    document.addEventListener('click', function(e) {
      const notiDropdown = document.querySelector('.notification-dropdown');
      const notiIcon = document.querySelector('.notification-icon');
      if (notiDropdown && notiDropdown.style.opacity === '1') {
        if (!notiDropdown.contains(e.target) && !notiIcon.contains(e.target)) {
          notiDropdown.style.opacity = '0';
          notiDropdown.style.visibility = 'hidden';
          notiDropdown.style.transform = 'translateY(0.5rem)';
        }
      }
    });
    // Toggle dropdown khi bấm chuông
    const notiIcon = document.querySelector('.notification-icon');
    const notiDropdown = document.querySelector('.notification-dropdown');
    if (notiIcon && notiDropdown) {
      notiIcon.addEventListener('click', function(e) {
        e.preventDefault();
        if (notiDropdown.style.opacity === '1') {
          notiDropdown.style.opacity = '0';
          notiDropdown.style.visibility = 'hidden';
          notiDropdown.style.transform = 'translateY(0.5rem)';
        } else {
          notiDropdown.style.opacity = '1';
          notiDropdown.style.visibility = 'visible';
          notiDropdown.style.transform = 'translateY(0)';
        }
      });
    }
  });

  // Add animation for notification badge
  const style = document.createElement('style');
  style.textContent = `
  @keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
  }
  .animate-pulse {
    animation: pulse 1s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  }
  `;
  document.head.appendChild(style);
  </script>

  @auth
  <script>
  (function() {
      function fetchNotifications() {
          fetch("{{ route('notifications.unread') }}", {
              headers: {
                  'X-Requested-With': 'XMLHttpRequest',
                  'Accept': 'application/json'
              },
              credentials: 'same-origin'
          })
          .then(response => response.json())
          .then(data => {
              // Cập nhật badge
              const badge = document.querySelector('.notification-badge');
              if (badge) {
                  if (data.count > 0) {
                      badge.textContent = data.count;
                      badge.style.display = '';
                  } else {
                      badge.style.display = 'none';
                  }
              }
              // Cập nhật danh sách thông báo
              const list = document.querySelector('.notification-list');
              if (list) {
                  list.innerHTML = '';
                  if (data.notifications.length > 0) {
                      data.notifications.forEach(function(noti) {
                          const a = document.createElement('a');
                          a.href = noti.url ? noti.url : '#';
                          a.className = 'notification-item';
                          a.innerHTML = `<div class="notification-content">${noti.message}</div><div class="notification-time">${noti.created_at}</div>`;
                          list.appendChild(a);
                      });
                  } else {
                      const div = document.createElement('div');
                      div.className = 'notification-empty';
                      div.textContent = 'Không có thông báo mới';
                      list.appendChild(div);
                  }
              }
          });
      }
      setInterval(fetchNotifications, 10000); // 10 giây
      document.addEventListener('DOMContentLoaded', fetchNotifications);
  })();
  </script>
  @endauth