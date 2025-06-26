@php
$user = auth()->user();
@endphp

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<nav class="navbar navbar-expand-lg navbar-dark nav-dark-bg py-0 shadow-sm nav-elevated sticky-navbar">
  <div class="container-fluid px-4">
    <!-- Logo -->
    <a class="navbar-brand d-flex align-items-center py-2" href="{{ route('dashboard') }}">
      <img src="https://cdn-icons-png.flaticon.com/128/17895/17895681.png" alt="Logo" width="36" height="36" class="me-2 logo-animate">
      <span class="fw-bold">Trang chủ</span>
    </a>
    <!-- Main menu -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center">
        <li class="nav-item">
          <a class="nav-link px-3 {{ request()->routeIs('alerts.map') ? 'active' : '' }}" href="{{ route('alerts.map') }}">
            <i class="fas fa-map-marked-alt me-1"></i> Bản đồ cảnh báo
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link px-3 {{ request()->routeIs('alerts.index') ? 'active' : '' }}" href="{{ route('alerts.index') }}">
            <i class="fas fa-list me-1"></i> Danh sách cảnh báo
          </a>
        </li>
        @auth
        <li class="nav-item">
          <a class="nav-link px-3 {{ request()->routeIs('alerts.create') ? 'active' : '' }}" href="{{ route('alerts.create') }}">
            <i class="fas fa-plus-circle me-1"></i> Gửi cảnh báo
          </a>
        </li>
        @if(auth()->user()->isAdmin)
        <li class="nav-item dropdown nav-animate">
          <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('admin.alerts*') || request()->routeIs('admin.experiences*') ? 'active' : '' }}" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-cog me-1"></i> Quản lý
          </a>
          <ul class="dropdown-menu dropdown-menu-custom" aria-labelledby="adminDropdown">
            <li><a class="dropdown-item {{ request()->routeIs('admin.alerts*') ? 'active' : '' }}" href="{{ route('admin.alerts') }}"><i class="fas fa-exclamation-triangle me-2 text-danger"></i> Quản lý cảnh báo</a></li>
            <li><a class="dropdown-item {{ request()->routeIs('admin.experiences*') ? 'active' : '' }}" href="{{ route('admin.experiences') }}"><i class="fas fa-comments me-2 text-success"></i> Quản lý chia sẻ kinh nghiệm</a></li>
          </ul>
        </li>
        @endif
        @endauth
        <li class="nav-item dropdown nav-animate">
          <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('news.index') || request()->routeIs('experiences.index') || request()->routeIs('community_alerts.index') || request()->routeIs('wanted_list.index') ? 'active' : '' }}" href="#" id="resourcesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-book me-1"></i> Chuyên mục
          </a>
          <ul class="dropdown-menu dropdown-menu-custom" aria-labelledby="resourcesDropdown">
            <li><a class="dropdown-item {{ request()->routeIs('news.index') ? 'active' : '' }}" href="{{ route('news.index') }}"><i class="fas fa-newspaper me-2 text-primary"></i> Tin tức an ninh</a></li>
            <li><a class="dropdown-item {{ request()->routeIs('experiences.index') ? 'active' : '' }}" href="{{ route('experiences.index') }}"><i class="fas fa-comments me-2 text-success"></i> Chia sẻ kinh nghiệm</a></li>
            <li><a class="dropdown-item {{ request()->routeIs('community_alerts.index') ? 'active' : '' }}" href="{{ route('community_alerts.index') }}"><i class="fas fa-users me-2 text-info"></i> Cảnh báo cộng đồng</a></li>
            <li><a class="dropdown-item {{ request()->routeIs('wanted_list.index') ? 'active' : '' }}" href="{{ route('wanted_list.index') }}"><i class="fas fa-user-ninja me-2 text-danger"></i> Truy nã</a></li>
          </ul>
        </li>
      </ul>
      <!-- Right section -->
      <ul class="navbar-nav ms-auto align-items-lg-center flex-row gap-2">
        @if($user)
        <li class="nav-item dropdown ms-2 user-dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center user-avatar-hover" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="avatar bg-white text-primary rounded-circle d-flex align-items-center justify-content-center me-2 avatar-animate" style="width: 36px; height: 36px; font-weight: bold;">
              {{ mb_strtoupper(mb_substr($user->name, 0, 1, 'UTF-8'), 'UTF-8') }}
            </div>
            <span class="fw-bold">{{ $user->name }}</span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow user-dropdown-animate">
            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fas fa-user-cog me-2"></i> Hồ sơ cá nhân</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="dropdown-item">
                  <i class="fas fa-sign-out-alt me-2"></i> Đăng xuất
                </button>
              </form>
            </li>
          </ul>
        </li>
        @else
        <li class="nav-item ms-2">
          <a href="{{ route('login') }}" class="btn btn-outline-light rounded-pill px-3 me-2"><i class="fas fa-sign-in-alt me-1"></i> Đăng nhập</a>
        </li>
        <li class="nav-item">
          <a href="{{ route('register') }}" class="btn btn-danger rounded-pill px-3">Đăng ký</a>
        </li>
        @endif
      </ul>
    </div>
  </div>
</nav>

<style>
.nav-dark-bg {
  background: #202944;
}
.btn-danger {
  background: #e63946;
  color: #fff;
  border: none;
  box-shadow: 0 2px 8px rgba(230,57,70,0.08);
  transition: background 0.2s, box-shadow 0.2s;
}
.btn-danger:hover, .btn-danger:focus {
  background: #b71c1c;
  color: #fff;
  box-shadow: 0 4px 16px rgba(230,57,70,0.18);
}
.mega-menu {
  min-width: 260px;
  max-width: 350px;
  left: 0;
  right: auto;
  margin: 0;
  border-radius: 14px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18);
  border: none;
  background: #fff;
  color: #23203a;
  top: 100%;
  animation: fadeInMenu 0.35s cubic-bezier(.4,0,.2,1);
}
.fade-in-menu {
  animation: fadeInMenu 0.35s cubic-bezier(.4,0,.2,1);
}
@keyframes fadeInMenu {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.navbar .dropdown-menu.mega-menu {
  display: none;
}
.navbar .nav-item.dropdown:hover > .dropdown-menu.mega-menu,
.navbar .nav-item.dropdown:focus-within > .dropdown-menu.mega-menu {
  display: block;
  position: absolute;
  z-index: 1050;
}
.navbar .dropdown-menu.mega-menu {
  border: none;
  padding-top: 0.7rem;
  padding-bottom: 0.7rem;
}
.navbar .dropdown-menu.mega-menu h6 {
  color: #e63946;
}
.navbar .dropdown-menu.mega-menu a.text-primary {
  color: #457b9d !important;
  font-weight: 500;
}
.navbar .dropdown-menu.mega-menu a.text-primary:hover {
  text-decoration: underline;
}
.navbar .nav-link {
  font-size: 0.98rem;
  padding-top: 0.7rem;
  padding-bottom: 0.7rem;
  border-radius: 8px 8px 0 0;
  transition: color 0.18s, background 0.18s, border-bottom 0.18s;
}
.navbar .nav-link.active, .navbar .nav-link:focus, .navbar .nav-link:hover {
  color: #e63946 !important;
  border-bottom: 2px solid #e63946;
  background: rgba(230,57,70,0.06);
}
.navbar .navbar-brand {
  font-size: 1.08rem;
  font-weight: 700;
  letter-spacing: 0.3px;
}
.logo-animate {
  width: 30px !important;
  height: 30px !important;
}
.avatar {
  font-size: 1rem;
  width: 32px;
  height: 32px;
}
.avatar-animate:hover {
  box-shadow: 0 0 0 3px #e6394633;
  background: #f3f6fa;
  border: 1.5px solid #e63946;
}
.user-avatar-hover:hover span {
  color: #e63946;
}
.navbar-nav > .nav-item.user-dropdown {
  position: relative;
}
.user-dropdown-animate {
  border-radius: 12px;
  box-shadow: 0 6px 24px rgba(0,0,0,0.13);
  position: absolute !important;
  top: 100% !important;
  right: 0 !important;
  left: auto !important;
  min-width: 180px;
  margin-top: 0.4rem !important;
  z-index: 1200;
}
.navbar .btn-outline-light {
  border-radius: 16px;
  border-width: 2px;
  font-weight: 500;
  font-size: 0.97rem;
  transition: background 0.2s, color 0.2s;
}
.navbar .btn-outline-light:hover, .navbar .btn-outline-light:focus {
  background: #fff;
  color: #e63946 !important;
  border-color: #e63946;
}
.navbar .btn-danger {
  font-weight: 600;
  letter-spacing: 0.3px;
  font-size: 0.97rem;
}
.navbar .nav-item .nav-link {
  transition: background 0.18s, color 0.18s;
}
.navbar .nav-item .nav-link:active {
  background: #e63946;
  color: #fff !important;
}
@media (max-width: 991.98px) {
  .mega-menu {
    min-width: 90vw;
    left: 0;
    right: 0;
    margin: auto;
    position: static !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    border-radius: 12px;
  }
  .navbar .dropdown-menu.mega-menu {
    position: static !important;
    display: none !important;
  }
  .navbar .nav-item.dropdown.show > .dropdown-menu.mega-menu {
    display: block !important;
  }
  .navbar .navbar-brand {
    font-size: 1rem;
  }
  .logo-animate {
    width: 26px !important;
    height: 26px !important;
  }
  .navbar .nav-link {
    font-size: 0.95rem;
    padding-top: 0.6rem;
    padding-bottom: 0.6rem;
  }
  .dropdown-menu-custom {
    min-width: 90vw;
    border-radius: 10px;
  }
}
.nav-elevated {
  box-shadow: 0 4px 24px rgba(40, 20, 80, 0.13), 0 1.5px 0 #e63946 inset;
}
.sticky-navbar {
  position: sticky;
  top: 0;
  z-index: 1100;
}
.dropdown-menu-custom {
  min-width: 180px;
  border-radius: 12px;
  box-shadow: 0 6px 24px rgba(0,0,0,0.13);
  padding: 0.3rem 0;
  background: #fff;
  border: none;
  margin-top: 0.4rem !important;
  left: 0 !important;
  right: auto !important;
}
.dropdown-menu-custom .dropdown-item {
  padding: 0.45rem 1rem;
  font-size: 0.97rem;
  border-radius: 7px;
  margin: 1px 0;
  transition: background 0.18s, color 0.18s;
}
.dropdown-menu-custom .dropdown-item.active, .dropdown-menu-custom .dropdown-item:hover {
  background: #f3f6fa;
  color: #e63946;
}
/* Không gạch chân khi hover user info */
.navbar-nav > .nav-item.user-dropdown > .nav-link {
  border-bottom: none !important;
}
.navbar-nav > .nav-item.user-dropdown > .nav-link:hover,
.navbar-nav > .nav-item.user-dropdown > .nav-link:focus {
  border-bottom: none !important;
  background: rgba(230,57,70,0.06);
  color: #e63946 !important;
}
</style>
