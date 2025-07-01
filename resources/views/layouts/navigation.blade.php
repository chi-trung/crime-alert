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
    
  </div>
</nav>

@vite(['resources/css/navigation.css', 'resources/js/navigation.js'])
