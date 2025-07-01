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
    <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse d-none d-lg-block" id="mainNavbar">
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
        <li class="nav-item">
          <a class="nav-link px-3 {{ request()->routeIs('my-history') ? 'active' : '' }}" href="{{ route('my-history') }}">
            <i class="fas fa-history me-1"></i> Lịch sử của tôi
          </a>
        </li>
        <li class="nav-item dropdown nav-animate">
          <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('alerts.index') || request()->routeIs('experiences.index') || request()->routeIs('wanted_list.index') ? 'active' : '' }}" href="#" id="resourcesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-th-large me-1"></i> Danh mục
          </a>
          <ul class="dropdown-menu dropdown-menu-custom" aria-labelledby="resourcesDropdown">
            <li><a class="dropdown-item {{ request()->routeIs('experiences.index') ? 'active' : '' }}" href="{{ route('experiences.index') }}"><i class="fas fa-comments me-2 text-success"></i> Chia sẻ kinh nghiệm</a></li>
            <li><a class="dropdown-item {{ request()->routeIs('wanted_list.index') ? 'active' : '' }}" href="{{ route('wanted_list.index') }}"><i class="fas fa-user-secret me-2 text-warning"></i> Danh sách truy nã</a></li>
          </ul>
        </li>
      </ul>
      <!-- Right section -->
      <ul class="navbar-nav ms-auto align-items-lg-center flex-row gap-2">
        @if($user)
        <!-- Notification Bell -->
        <li class="nav-item dropdown ms-2">
          @php
            $unreadNotifications = $user->unreadNotifications()->take(10)->get();
            $unreadCount = $unreadNotifications->count();
          @endphp
          <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding-right: 18px;">
            <i class="fas fa-bell fa-lg"></i>
            @if($unreadCount > 0)
              <span class="notification-badge badge bg-danger">{{ $unreadCount }}</span>
            @endif
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow fade-in-menu notification-dropdown-menu" aria-labelledby="notificationDropdown" style="min-width: 420px; max-width: 520px;">
            <li class="dropdown-header fw-bold text-primary">Thông báo mới</li>
            @forelse($unreadNotifications as $notification)
              <li>
                @if(isset($notification->data['type']) && $notification->data['type'] === 'support')
                  <a class="dropdown-item py-3 px-3 notification-item d-flex align-items-start gap-2 notification-unread" href="{{ route('notifications.read', $notification->id) }}" style="white-space: normal; word-break: break-word;">
                    <div class="me-2 mt-1 flex-shrink-0">
                      <i class="fas fa-comments text-primary fa-lg"></i>
                    </div>
                    <div class="flex-grow-1" style="min-width:0;">
                      <div class="mb-1 notification-title" style="white-space: normal; word-break: break-word;">
                        <b>{{ $notification->data['sender_name'] }}</b> đã gửi tin nhắn hỗ trợ:
                        <span class="text-dark">{{ Str::limit($notification->data['message'], 60) }}</span>
                      </div>
                      <div class="small text-muted mb-1 notification-content" style="white-space: normal; word-break: break-word;">Yêu cầu: {{ $notification->data['support_subject'] }}</div>
                      <div class="d-flex align-items-center gap-1">
                        <i class="far fa-clock text-secondary small"></i>
                        <span class="small text-secondary">{{ $notification->created_at->diffForHumans() }}</span>
                        <span class="badge bg-primary text-white ms-2">Hỗ trợ</span>
                      </div>
                    </div>
                  </a>
                @elseif(isset($notification->data['reply_id']))
                  <a class="dropdown-item py-3 px-3 notification-item d-flex align-items-start gap-2 notification-unread" href="{{ route('notifications.read', $notification->id) }}" style="white-space: normal; word-break: break-word;">
                    <div class="me-2 mt-1 flex-shrink-0">
                      <i class="fas fa-reply text-info fa-lg"></i>
                    </div>
                    <div class="flex-grow-1" style="min-width:0;">
                      <div class="mb-1 notification-title" style="white-space: normal; word-break: break-word;">
                        <b>{{ $notification->data['reply_user'] }}</b> đã trả lời bình luận của bạn trong <span class="text-primary">{{ $notification->data['post_type'] == 'alert' ? 'cảnh báo' : 'kinh nghiệm' }}</span>:
                        <b>{{ $notification->data['post_title'] }}</b>
                      </div>
                      <div class="small text-muted mb-1 notification-content" style="white-space: normal; word-break: break-word;">"{{ $notification->data['reply_content'] }}"</div>
                      <div class="d-flex align-items-center gap-1">
                        <i class="far fa-clock text-secondary small"></i>
                        <span class="small text-secondary">{{ $notification->created_at->diffForHumans() }}</span>
                        <span class="badge bg-info text-dark ms-2">Reply</span>
                      </div>
                    </div>
                  </a>
                @else
                  <a class="dropdown-item py-3 px-3 notification-item d-flex align-items-start gap-2 @if(is_null($notification->read_at)) notification-unread @endif" href="{{ route('notifications.read', $notification->id) }}" style="white-space: normal; word-break: break-word;">
                    <div class="me-2 mt-1 flex-shrink-0">
                      <i class="fas fa-comment-dots text-success fa-lg"></i>
                    </div>
                    <div class="flex-grow-1" style="min-width:0;">
                      <div class="mb-1 notification-title" style="white-space: normal; word-break: break-word;">
                        @if(isset($notification->data['comment_user'], $notification->data['post_type'], $notification->data['post_title']))
                          <b>{{ $notification->data['comment_user'] }}</b> đã bình luận vào <span class="text-primary">{{ $notification->data['post_type'] == 'alert' ? 'cảnh báo' : 'kinh nghiệm' }}</span>:
                          <b>{{ $notification->data['post_title'] }}</b>
                        @else
                          {{ $notification->data['message'] ?? 'Bạn có thông báo mới' }}
                        @endif
                      </div>
                      @if(isset($notification->data['comment_content']))
                        <div class="small text-muted mb-1 notification-content" style="white-space: normal; word-break: break-word;">"{{ $notification->data['comment_content'] }}"</div>
                      @endif
                      <div class="d-flex align-items-center gap-1">
                        <i class="far fa-clock text-secondary small"></i>
                        <span class="small text-secondary">{{ $notification->created_at->diffForHumans() }}</span>
                        @if(is_null($notification->read_at))
                          <span class="badge bg-warning text-dark ms-2">Chưa đọc</span>
                        @endif
                      </div>
                    </div>
                  </a>
                @endif
              </li>
            @empty
              <li><span class="dropdown-item text-muted">Không có thông báo mới.</span></li>
            @endforelse
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item text-center text-primary" href="{{ route('notifications.index') }}">Xem tất cả thông báo</a>
            </li>
          </ul>
        </li>
        <!-- End Notification Bell -->
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
    <!-- Offcanvas menu cho mobile -->
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="mobileMenuLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('alerts.map') ? 'active' : '' }}" href="{{ route('alerts.map') }}">
              <i class="fas fa-map-marked-alt me-1"></i> Bản đồ cảnh báo
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('alerts.index') ? 'active' : '' }}" href="{{ route('alerts.index') }}">
              <i class="fas fa-list me-1"></i> Danh sách cảnh báo
            </a>
          </li>
          @auth
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('alerts.create') ? 'active' : '' }}" href="{{ route('alerts.create') }}">
              <i class="fas fa-plus-circle me-1"></i> Gửi cảnh báo
            </a>
          </li>
          @if(auth()->user()->isAdmin)
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="adminDropdownMobile" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-cog me-1"></i> Quản lý
            </a>
            <ul class="dropdown-menu" aria-labelledby="adminDropdownMobile">
              <li><a class="dropdown-item {{ request()->routeIs('admin.alerts*') ? 'active' : '' }}" href="{{ route('admin.alerts') }}"><i class="fas fa-exclamation-triangle me-2 text-danger"></i> Quản lý cảnh báo</a></li>
              <li><a class="dropdown-item {{ request()->routeIs('admin.experiences*') ? 'active' : '' }}" href="{{ route('admin.experiences') }}"><i class="fas fa-comments me-2 text-success"></i> Quản lý chia sẻ kinh nghiệm</a></li>
            </ul>
          </li>
          @endif
          @endauth
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('my-history') ? 'active' : '' }}" href="{{ route('my-history') }}">
              <i class="fas fa-history me-1"></i> Lịch sử của tôi
            </a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="resourcesDropdownMobile" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-th-large me-1"></i> Danh mục
            </a>
            <ul class="dropdown-menu" aria-labelledby="resourcesDropdownMobile">
              <li><a class="dropdown-item {{ request()->routeIs('experiences.index') ? 'active' : '' }}" href="{{ route('experiences.index') }}"><i class="fas fa-comments me-2 text-success"></i> Chia sẻ kinh nghiệm</a></li>
              <li><a class="dropdown-item {{ request()->routeIs('wanted_list.index') ? 'active' : '' }}" href="{{ route('wanted_list.index') }}"><i class="fas fa-user-secret me-2 text-warning"></i> Danh sách truy nã</a></li>
            </ul>
          </li>
          @if($user)
          <li><hr></li>
          <li class="nav-item">
            <a class="nav-link" href="{{ route('profile.edit') }}"><i class="fas fa-user-cog me-2"></i> Hồ sơ cá nhân</a>
          </li>
          <li class="nav-item">
            <form action="{{ route('logout') }}" method="POST">
              @csrf
              <button type="submit" class="nav-link btn btn-link p-0"><i class="fas fa-sign-out-alt me-2"></i> Đăng xuất</button>
            </form>
          </li>
          @else
          <li class="nav-item">
            <a href="{{ route('login') }}" class="btn btn-outline-primary w-100 mb-2"><i class="fas fa-sign-in-alt me-1"></i> Đăng nhập</a>
          </li>
          <li class="nav-item">
            <a href="{{ route('register') }}" class="btn btn-danger w-100">Đăng ký</a>
          </li>
          @endif
        </ul>
      </div>
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
/* Thêm style cho dropdown notification */
.notification-badge {
  position: absolute;
  top: 2px;
  right: 2px;
  font-size: 0.75rem;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  z-index: 2;
  box-shadow: 0 1px 4px rgba(0,0,0,0.12);
}
.notification-dropdown-menu {
  border-radius: 14px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18);
  border: 1.5px solid #e63946;
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
  max-width: 520px;
  min-width: 420px;
  word-break: break-word;
}
.notification-item {
  border-left: 4px solid transparent;
  transition: background 0.18s, border-color 0.18s;
  border-radius: 8px;
  white-space: normal;
  word-break: break-word;
}
.notification-item:hover {
  background: #f3f6fa;
  border-left: 4px solid #e63946;
}
.notification-unread {
  background: #fffbe6;
  border-left: 4px solid #e63946;
}
.notification-title, .notification-content {
  white-space: normal;
  word-break: break-word;
  overflow-wrap: break-word;
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.notification-item').forEach(function(item) {
    item.addEventListener('click', function(e) {
      e.preventDefault();
      var url = this.getAttribute('href');
      var li = this.closest('li');
      var badge = document.querySelector('.notification-badge');
      // Gọi AJAX đánh dấu đã đọc
      fetch(url, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => {
          if (res.redirected) {
            window.location.href = res.url;
            return;
          }
          // Hiệu ứng mờ dần
          li.style.transition = 'opacity 0.5s';
          li.style.opacity = 0;
          setTimeout(() => {
            li.remove();
            // Giảm badge
            let count = parseInt(badge?.textContent || '1');
            if (count > 1) badge.textContent = count - 1;
            else if (badge) badge.remove();
          }, 500);
        });
    });
  });
});
</script>
@endpush
