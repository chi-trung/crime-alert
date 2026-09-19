  <nav class="modern-nav">
    <div class="nav-container">
      <!-- Logo/Brand Section -->
      <div class="nav-brand">
        <a href="{{ route('dashboard') }}" class="brand-link">
          <div class="brand-icon">
            <img src="{{ asset('favicon.svg') }}" alt="Logo" width="28" height="28" />
          </div>
          <span class="brand-text">Trang chủ</span>
        </a>
      </div>

      <!-- Main Navigation -->
      <div class="nav-main" id="nav-main">
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
            {{-- Issue #374: a real navigation link that goes nowhere is the
                 wrong element for a control that only toggles content — a
                 button is keyboard-focusable and Enter-able with no extra
                 wiring. Styled to sit in the flex row like its sibling
                 anchors; the handler below still calls preventDefault(). --}}
            <button type="button" class="nav-link dropdown-toggle" aria-expanded="false" aria-haspopup="true" aria-controls="category-dropdown" id="categoryToggle">
              <span class="link-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="3" width="7" height="7"></rect>
                  <rect x="14" y="3" width="7" height="7"></rect>
                  <rect x="14" y="14" width="7" height="7"></rect>
                  <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
              </span>
              <span class="link-text">Chuyên mục</span>

            </button>
            {{-- Issue #406: the toggle above declares aria-haspopup="true" and
                 aria-controls="category-dropdown", so this container is
                 announced as a menu — but it was a plain div, and the four
                 links inside lost their menu context. role="menu" + the
                 role="menuitem" on each item make the declared relationship
                 real (WCAG 1.3.1). The .menu-open/:focus-within open
                 behaviour of #374 is untouched. --}}
            <div class="dropdown-menu" id="category-dropdown" role="menu">
              <a href="{{ route('news.index') }}" class="dropdown-item {{ request()->routeIs('news.index') ? 'active' : '' }}" role="menuitem">
                <span>Tin tức an ninh</span>
              </a>
              <a href="{{ route('experiences.index') }}" class="dropdown-item {{ request()->routeIs('experiences.index') ? 'active' : '' }}" role="menuitem">
                <span>Chia sẻ kinh nghiệm</span>
              </a>
              <a href="{{ route('wanted_list.index') }}" class="dropdown-item {{ request()->routeIs('wanted_list.index') ? 'active' : '' }}" role="menuitem">
                <span>Truy nã</span>
              </a>
              <a href="{{ route('my-history') }}" class="dropdown-item {{ request()->routeIs('my-history') ? 'active' : '' }}" role="menuitem">
                <span>Lịch sử</span>
              </a>
            </div>
          </li>
        </ul>
        @auth
          @if(auth()->user()->isAdmin)
            {{-- Issue #345: this block was unreachable HTML. A stray </li>
                 with no opening <li> below made the browser reject the whole
                 <ul> as malformed, so "Bai viet" and "Bao cao" never rendered
                 for ANY admin - verified by DOM-probing the rendered nav: the
                 second ul came back with zero child <li>. The dashboard card
                 buttons were the only path left to those admin indexes. --}}
            <ul class="nav-menu">
              <li class="nav-item">
                <a href="{{ route('admin.alerts') }}" class="nav-link">
                  <span class="link-icon">
                    <i class="fas fa-chart-bar"></i>
                  </span>
                  <span class="link-text">Báo cáo</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{ route('admin.experiences') }}" class="nav-link">
                  <span class="link-icon">
                    <i class="fas fa-book"></i>
                  </span>
                  <span class="link-text">Bài viết</span>
                </a>
              </li>
              {{-- Issue #345: the admin support queue had no nav entry either
                   - only a "Xem tất cả" button on one dashboard card. --}}
              <li class="nav-item">
                <a href="{{ route('admin.support.index') }}" class="nav-link">
                  <span class="link-icon">
                    <i class="fas fa-headset"></i>
                  </span>
                  <span class="link-text">Hỗ trợ</span>
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
              // Issue #69: the dropdown previews 10, but the badge shows the
              // true total — it used to count this truncated list and froze
              // at "10". Mirrors NotificationController::unreadAjax().
              // Issue #91: id DESC tiebreak, the #89/#90 fix applied to this
              // second copy of the query — the framework relation ends in
              // plain ->latest(), so a same-second burst truncated to an
              // arbitrary ten, re-rolled on every authenticated page view.
              $unreadNotifications = auth()->user()->unreadNotifications()->orderByDesc('id')->take(10)->get();
              $unreadCount = auth()->user()->unreadNotifications()->count();
            @endphp
            {{-- Issue #400: the bell link was a bare SVG plus a number — its
                 accessible name was empty. It also opens a dropdown without
                 announcing the expanded state. --}}
            <a href="#" class="notification-icon" aria-label="Thông báo" aria-expanded="false" aria-haspopup="true" id="notification-toggle">
              <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
              {{-- Issue #400: the badge is rewritten every 10s by the poll
                   below, so it needs to be a live region — otherwise a new
                   notification arrives and a screen reader user is never told
                   until they open the dropdown themselves. --}}
              <span class="notification-badge" role="status" aria-live="polite" style="{{ $unreadCount > 0 ? '' : 'display:none;' }}">{{ $unreadCount }}</span>
            </a>
            <div class="notification-dropdown">
              <div class="dropdown-header">
                <h4>Thông báo mới</h4>
                <a href="{{ route('notifications.index') }}">Xem tất cả</a>
              </div>
              <div class="notification-list" role="list">
                @forelse($unreadNotifications as $notification)
                  <a href="{{ route('notifications.read', $notification->id) }}" class="notification-item" role="listitem">
                    <div class="notification-content">
                      {{ $notification->data['message'] ?? 'Bạn có thông báo mới' }}
                    </div>
                    <div class="notification-time">{{ $notification->created_at->diffForHumans() }}</div>
                  </a>
                @empty
                  <div class="notification-empty" role="listitem">Không có thông báo mới</div>
                @endforelse
              </div>
            </div>
          </div>
          
          <!-- User Profile -->
          {{-- Issue #357: this anchor carried only .profile-link, so the nav's
               generic .dropdown-toggle click handler (below) never bound it
               and the menu opened through CSS :hover alone. On a phone or
               tablet there is no hover, so "Hồ sơ cá nhân" and "Đăng xuất"
               were unreachable — a hard lockout of sign-out on mobile.
               dropdown-toggle puts the existing handler in charge;
               :focus-within below covers keyboard users the same way. --}}
          <div class="user-profile dropdown">
            <a href="#" class="profile-link dropdown-toggle" aria-expanded="false" aria-haspopup="true" aria-controls="profile-dropdown" id="profileToggle">
              <div class="profile-avatar">
                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1, 'UTF-8'), 'UTF-8') }}
              </div>
              <span class="profile-name">{{ auth()->user()->name }}</span>
            </a>
            {{-- Issue #406: same gap as the category menu — the profile toggle
                 declares the menu relationship, the container did not honour
                 it. The logout item is a POST form (#149), so its role goes on
                 the form itself; the submit button keeps its real behaviour. --}}
            <div class="profile-dropdown" id="profile-dropdown" role="menu">
              <a href="{{ route('profile.edit') }}" class="dropdown-item" role="menuitem">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Hồ sơ cá nhân</span>
              </a>
              <form action="{{ route('logout') }}" method="POST" class="dropdown-item" role="menuitem">
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
      {{-- Issue #374: the button toggles .nav-container.active but never told
           assistive technology whether the menu it controls was open, so the
           label was the same collapsed and expanded. aria-expanded reports
           exactly the state the CSS already acts on; aria-controls points at
           the region it opens. --}}
      <button class="mobile-toggle" aria-label="Mở menu" aria-expanded="false" aria-controls="nav-main" id="mobileToggle">
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
  /* Issue #374: the category toggle is a real <button>, not an anchor, so it
   * needs the browser defaults reset to sit in the row like its siblings. */
  button.nav-link {
    border: none;
    background: none;
    cursor: pointer;
    font: inherit;
    width: 100%;
    text-align: left;
  }

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

  .nav-item:hover .dropdown-menu,
  .nav-item.menu-open .dropdown-menu {
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

  .user-notification:hover .notification-dropdown,
  .user-notification.open .notification-dropdown {
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

  /* Issue #357: :hover alone never fires on a touch device, and the dropdown
     has no click handler of its own, so the menu was unreachable on phones
     and tablets. :focus-within covers keyboard and screen-reader users too;
     the JS handler toggles .dropdown-toggle for everyone else. */
  .user-profile:hover .profile-dropdown,
  .user-profile:focus-within .profile-dropdown,
  .user-profile.menu-open .profile-dropdown {
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
    
    .nav-item:hover .dropdown-menu,
    .nav-item.menu-open .dropdown-menu {
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
        const isOpen = navContainer.classList.toggle('active');
        // Issue #374: report the state the CSS already acts on.
        mobileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        mobileToggle.setAttribute('aria-label', isOpen ? 'Đóng menu' : 'Mở menu');
      });
    }

    // Keeps every dropdown toggle's aria-expanded in step with the class the
    // CSS opens the menu with (#374).
    function syncDropdownToggles() {
      document.querySelectorAll('.nav-item.menu-open, .user-profile.menu-open').forEach(item => {
        const toggle = item.querySelector('.dropdown-toggle');
        if (toggle) {
          toggle.setAttribute('aria-expanded', 'true');
        }
      });
      document.querySelectorAll('.nav-item:not(.menu-open), .user-profile:not(.menu-open)').forEach(item => {
        const toggle = item.querySelector('.dropdown-toggle');
        if (toggle) {
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
    }
    
    // Close dropdowns when clicking outside.
    // Issue #365: this used to write an inline display rule on the menu
    // element. Desktop CSS opens these menus through opacity/visibility and
    // never touches display, so that inline rule outlived the click and
    // hover could not open the menu again until the page was reloaded.
    // Toggling the .menu-open class instead keeps hover authoritative.
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.dropdown-toggle') && !e.target.closest('.dropdown-menu') && !e.target.closest('.profile-dropdown')) {
        document.querySelectorAll('.nav-item.menu-open, .user-profile.menu-open').forEach(item => {
          item.classList.remove('menu-open');
        });
        syncDropdownToggles();
      }
    });

    // Toggle dropdowns on click (for mobile & desktop). Same class, not
    // inline display: see the #365 note above.
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const container = this.closest('.nav-item, .user-profile');
        if (!container) return;
        const isOpen = container.classList.contains('menu-open');
        document.querySelectorAll('.nav-item.menu-open, .user-profile.menu-open').forEach(item => {
          item.classList.remove('menu-open');
        });
        if (!isOpen) {
          container.classList.add('menu-open');
        }
        syncDropdownToggles();
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
              // Issue #365: a 401 (expired session) answers with
              // {success:false, message, redirect} and no count/notifications
              // keys. Without this guard, data.notifications.length throws a
              // TypeError, the promise rejects unhandled, and the 10s
              // interval keeps re-throwing for as long as the tab is open.
              if (!data || !Array.isArray(data.notifications)) {
                  return;
              }
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
              // Issue #400: keep the trigger's expanded state in sync with the
              // dropdown it owns, so a screen reader announces open/closed.
              const toggle = document.getElementById('notification-toggle');
              if (toggle) {
                  toggle.setAttribute('aria-expanded', data.count > 0 ? 'true' : 'false');
              }
              // Cập nhật danh sách thông báo
              const list = document.querySelector('.notification-list');
              if (list) {
                  list.innerHTML = '';
                  if (data.notifications.length > 0) {
                      data.notifications.forEach(function(noti) {
                          const a = document.createElement('a');
                          // Issue #113: rows used to navigate straight to the
                          // raw data['url'], bypassing read()'s #110 target
                          // validation (and a javascript: payload would run
                          // as a href). Rows go through the read route, which
                          // marks read and validates server-side.
                          a.href = noti.read_url ? noti.read_url : '#';
                          a.className = 'notification-item';
                          // Issue #400: the server-side rows ship role="listitem"
                          // inside role="list"; dynamically created rows must
                          // too, or they land outside the a11y tree's list.
                          a.setAttribute('role', 'listitem');
                          // textContent, not innerHTML: noti.message interpolates the
                          // notifier's name and post title, which are user input (issue #18).
                          const content = document.createElement('div');
                          content.className = 'notification-content';
                          content.textContent = noti.message;
                          const time = document.createElement('div');
                          time.className = 'notification-time';
                          time.textContent = noti.created_at;
                          a.appendChild(content);
                          a.appendChild(time);
                          list.appendChild(a);
                      });
                  } else {
                      const div = document.createElement('div');
                      div.className = 'notification-empty';
                      div.setAttribute('role', 'listitem');
                      div.textContent = 'Không có thông báo mới';
                      list.appendChild(div);
                  }
              }
          })
          // Issue #365: a dropped request or an expired session must not
          // surface as an unhandled rejection in the visitor's console every
          // 10s. Same idiom as app.blade.php and support/show.blade.php.
          .catch(() => {});
      }
      setInterval(fetchNotifications, 10000); // 10 giây
      document.addEventListener('DOMContentLoaded', fetchNotifications);

      // Issue #400: the dropdown opened on :hover only, so a touch device or a
      // keyboard user could never reach it — the same lockout #357 fixed for
      // the profile menu. The bell is a link, not a button, so it takes both
      // a click toggle and a keyboard-openable path.
      document.addEventListener('DOMContentLoaded', function() {
          const wrapper = document.querySelector('.user-notification');
          const bell = document.getElementById('notification-toggle');
          if (!wrapper || !bell) {
              return;
          }
          function setOpen(open) {
              wrapper.classList.toggle('open', open);
              bell.setAttribute('aria-expanded', open ? 'true' : 'false');
          }
          bell.addEventListener('click', function(e) {
              e.preventDefault();
              setOpen(!wrapper.classList.contains('open'));
          });
          // A keyboard user tabbing past the bell must not leave the dropdown
          // hanging open behind them.
          bell.addEventListener('blur', function() {
              if (!wrapper.contains(document.activeElement)) {
                  setOpen(false);
              }
          });
          document.addEventListener('keydown', function(e) {
              if (e.key === 'Escape' && wrapper.classList.contains('open')) {
                  setOpen(false);
                  bell.focus();
              }
          });
      });
  })();
  </script>
  @endauth