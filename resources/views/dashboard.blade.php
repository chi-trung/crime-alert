@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
<script src="{{ asset('js/dashboard.js') }}"></script>
<div class="container px-4">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 mt-4">
        <div class="mb-3 mb-md-0">
            <h1 class="fw-bold mb-1">Bảng điều khiển</h1>
        </div>
        <div class="d-flex align-items-center">
            <div class="me-3 d-none d-sm-block text-end">
                <div class="text-muted small">Cập nhật lần cuối</div>
                <div class="fw-semibold text-primary">{{ now()->format('d/m/Y H:i') }}</div>
            </div>
            <button class="btn btn-light rounded-circle p-2" id="refresh-btn">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>
    <!-- Welcome Card -->
    <div class="card border-0 bg-gradient-primary mb-4 overflow-hidden">
        <div class="card-body p-4 position-relative">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="mb-3 mb-md-0">
                    <h5 class="fw-bold text-white mb-2">Chào mừng trở lại, {{ auth()->user()->name }}!</h5>
                    <p class="text-white-50 mb-0">Hệ thống đã sẵn sàng để bạn quản lý các cảnh báo tội phạm</p>
                    <a href="{{ auth()->user()->isAdmin ? route('admin.alerts') : route('alerts.create') }}" 
                       class="btn btn-light btn-sm mt-3">
                       {{ auth()->user()->isAdmin ? 'Xem báo cáo' : 'Tạo cảnh báo mới' }}
                    </a>
                    @if(auth()->user()->isAdmin)
                        
                    @else
                        <a href="{{ route('support.create') }}" class="btn btn-warning btn-sm mt-3 ms-2">
                            <i class="fas fa-life-ring me-1"></i> Yêu cầu hỗ trợ
                        </a>
                    @endif
                    <a href="{{ route('alerts.map') }}" class="btn btn-outline-light btn-lg mt-3">
                        <i class="fas fa-map-marked-alt me-1"></i> Xem bản đồ tội phạm
                    </a>
                </div>
                <div class="d-none d-md-block">
                    <img src="https://cdn-icons-png.flaticon.com/128/2642/2642651.png" alt="Welcome" style="height: 120px;" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    {{-- Block: Yêu cầu hỗ trợ gần đây của bạn (user thường) --}}
    @if(!auth()->user()->isAdmin && isset($latestSupportRequest))
        <div class="card border-0 shadow-sm mb-4 mt-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0">
                <h5 class="card-title mb-0">
                    <i class="fas fa-life-ring me-2 text-warning"></i>
                    Yêu cầu hỗ trợ gần đây của bạn
                </h5>
                <a href="{{ route('support.index') }}" class="btn btn-outline-warning btn-sm rounded-pill px-3">Xem tất cả</a>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1 flex-wrap gap-2">
                            <h6 class="fw-bold mb-0 me-2">{{ $latestSupportRequest->subject }}</h6>
                            <span class="badge {{ $latestSupportRequest->status == 'open' ? 'bg-warning text-dark' : 'bg-secondary' }} px-3 py-2 rounded-pill d-flex align-items-center" style="font-size: 0.95em;">
                                @if($latestSupportRequest->status == 'open')
                                    <i class="fas fa-hourglass-half me-1"></i> Đang mở
                                @else
                                    Đã đóng
                                @endif
                            </span>
                        </div>
                        <div class="text-muted small mb-2">
                            <i class="far fa-calendar-alt me-1"></i> {{ $latestSupportRequest->created_at->format('d/m/Y H:i') }}
                        </div>
                        <a href="{{ route('support.show', $latestSupportRequest) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="fas fa-eye me-1"></i> Xem chi tiết
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(auth()->user()->isAdmin && isset($latestSupportRequest))
    <div class="card border-0 shadow-sm mb-4 mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0">
            <h5 class="card-title mb-0"><i class="fas fa-life-ring me-2 text-warning"></i>Yêu cầu hỗ trợ gần đây</h5>
            <a href="{{ route('admin.support.index') }}" class="btn btn-outline-warning btn-sm rounded-pill px-3">Xem tất cả</a>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center gap-3">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-1 flex-wrap gap-2">
                        <h6 class="fw-bold mb-0 me-2">{{ $latestSupportRequest->subject }}</h6>
                        <span class="badge {{ $latestSupportRequest->status == 'open' ? 'bg-warning text-dark' : 'bg-secondary' }} px-3 py-2 rounded-pill d-flex align-items-center" style="font-size: 0.95em;">
                            @if($latestSupportRequest->status == 'open')
                                <i class="fas fa-hourglass-half me-1"></i> Đang mở
                            @else
                                Đã đóng
                            @endif
                        </span>
                        <span class="ms-2 text-muted small"><i class="fas fa-user me-1"></i> {{ $latestSupportRequest->user->name ?? 'N/A' }}</span>
                    </div>
                    <div class="text-muted small mb-2"><i class="far fa-calendar-alt me-1"></i> {{ $latestSupportRequest->created_at->format('d/m/Y H:i') }}</div>
                    <a href="{{ route('support.show', $latestSupportRequest) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fas fa-eye me-1"></i> Xem chi tiết</a>
                </div>
            </div>
        </div>
    </div>
    @elseif(auth()->user()->isAdmin)
    <div class="card border-0 shadow-sm mb-4 mt-4">
        <div class="card-header bg-white border-bottom-0">
            <h5 class="card-title mb-0"><i class="fas fa-life-ring me-2 text-warning"></i>Yêu cầu hỗ trợ gần đây</h5>
        </div>
        <div class="card-body text-center text-muted">
            Chưa có yêu cầu hỗ trợ nào cả.
        </div>
    </div>
    @endif

    @if(!auth()->user()->isAdmin)
        <div class="mb-2 text-center">
            <span class="fw-semibold text-primary">Thống kê theo tháng {{ $monthLabel }}</span>
        </div>
        <!-- Thống kê tỷ lệ loại tội phạm của user -->
        <div class="row g-4 mb-4 justify-content-center">
            <div class="col-md-2 col-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3 text-center">
                        <span class="avatar-title bg-danger bg-opacity-10 text-danger rounded fs-3 mb-2 d-inline-block">
                            <i class="fas fa-bolt"></i>
                        </span>
                        <div class="fw-bold fs-4">{{ $typePercents['Cướp giật'] ?? 0 }}%</div>
                        <div class="text-muted small">Cướp giật</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3 text-center">
                        <span class="avatar-title bg-primary bg-opacity-10 text-primary rounded fs-3 mb-2 d-inline-block">
                            <i class="fas fa-mask"></i>
                        </span>
                        <div class="fw-bold fs-4">{{ $typePercents['Trộm cắp'] ?? 0 }}%</div>
                        <div class="text-muted small">Trộm cắp</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3 text-center">
                        <span class="avatar-title bg-warning bg-opacity-10 text-warning rounded fs-3 mb-2 d-inline-block">
                            <i class="fas fa-user-secret"></i>
                        </span>
                        <div class="fw-bold fs-4">{{ $typePercents['Lừa đảo'] ?? 0 }}%</div>
                        <div class="text-muted small">Lừa đảo</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3 text-center">
                        <span class="avatar-title bg-success bg-opacity-10 text-success rounded fs-3 mb-2 d-inline-block">
                            <i class="fas fa-fist-raised"></i>
                        </span>
                        <div class="fw-bold fs-4">{{ $typePercents['Bạo lực'] ?? 0 }}%</div>
                        <div class="text-muted small">Bạo lực</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3 text-center">
                        <span class="avatar-title bg-secondary bg-opacity-10 text-secondary rounded fs-3 mb-2 d-inline-block">
                            <i class="fas fa-question"></i>
                        </span>
                        <div class="fw-bold fs-4">{{ $typePercents['Khác'] ?? 0 }}%</div>
                        <div class="text-muted small">Khác</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3 text-center">
                        <span class="avatar-title bg-info bg-opacity-10 text-info rounded fs-3 mb-2 d-inline-block">
                            <i class="fas fa-pen-alt"></i>
                        </span>
                        <div class="fw-bold fs-4">{{ isset($totalPosts) ? $totalPosts : 0 }}</div>
                        <div class="text-muted small">Tổng bài viết</div>
                        @if(isset($totalPosts) && $totalPosts > 0)
                            <div class="text-muted small mb-1">
                                {{ $totalApprovedPosts }}/{{ $totalPosts }} được duyệt
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @if(isset($myExperiencesThisMonth) && $myExperiencesThisMonth->count() > 0)
        <!-- Modal danh sách bài chia sẻ tháng này -->
        <div class="modal fade" id="modalExperiencesThisMonth" tabindex="-1" aria-labelledby="modalExperiencesThisMonthLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="modalExperiencesThisMonthLabel">Bài chia sẻ kinh nghiệm đã đăng trong tháng {{ $monthLabel }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="table-responsive">
                  <table class="table table-borderless align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Tiêu đề</th>
                        <th>Ngày gửi</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Hành động</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($myExperiencesThisMonth as $exp)
                      <tr>
                        <td><a href="{{ route('experiences.show', $exp) }}" class="fw-semibold text-dark text-decoration-none">{{ $exp->title }}</a></td>
                        <td>{{ $exp->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                          @if($exp->status == 'approved')
                            <span class="badge bg-success">Đã duyệt</span>
                          @elseif($exp->status == 'pending')
                            <span class="badge bg-warning text-dark">Chờ duyệt</span>
                          @else
                            <span class="badge bg-danger">Từ chối</span>
                          @endif
                        </td>
                        <td class="text-end">
                          <a href="{{ route('experiences.show', $exp) }}" class="btn btn-outline-primary btn-sm">Xem</a>
                          @if((auth()->user()->isAdmin || auth()->id() === $exp->user_id) && $exp->status != 'approved')
                            <a href="{{ route('experiences.edit', $exp) }}" class="btn btn-outline-success btn-sm">Sửa</a>
                            <form action="{{ route('experiences.destroy', $exp) }}" method="POST" class="d-inline form-delete">
                              @csrf
                              @method('DELETE')
                              <button type="submit" class="btn btn-outline-danger btn-sm">Xóa</button>
                            </form>
                          @endif
                        </td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
        @endif
        <!-- 3 khối Tin tức, Truy nã, Chia sẻ kinh nghiệm -->
        <div class="row g-4 mb-4 mt-2 justify-content-center">
            <!-- Tin tức an ninh mới nhất -->
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card h-100 shadow-sm border-0 rounded-4 bg-white p-3 d-flex flex-column">
                    <div class="card-body pb-2 d-flex flex-column" style="height: 100%">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-newspaper text-primary fs-3 me-2"></i>
                            <span class="fw-bold fs-5">Tin tức an ninh mới nhất</span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-grid gap-3">
                            @foreach($latestNews as $news)
                                <div class="border-bottom pb-2">
                                    <a href="{{ $news->link }}" target="_blank" class="fw-semibold text-dark text-decoration-none">{{ $news->title }}</a>
                                    <div class="text-muted small">{{ Str::limit($news->description, 60) }}</div>
                                </div>
                            @endforeach
                            </div>
                        </div>
                        <div class="mt-auto pt-2 text-center">
                            <a href="{{ route('news.index') }}" class="btn btn-primary btn-sm rounded-pill px-4">Xem tất cả</a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Truy nã nổi bật -->
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card h-100 shadow-sm border-0 rounded-4 bg-white p-3 d-flex flex-column">
                    <div class="card-body pb-2 d-flex flex-column" style="height: 100%">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-user-ninja text-danger fs-3 me-2"></i>
                            <span class="fw-bold fs-5">Truy nã nổi bật</span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="row g-2">
                            @foreach($hotWanted as $person)
                                <div class="col-12">
                                    <div class="d-flex align-items-center border rounded-3 p-2 shadow-sm bg-light mb-2">
                                        <img src="https://truyna.bocongan.gov.vn/DesktopModules/PoliceTruyNaToiPham/ShowImage.aspx?TruyNaId={{ $person->id }}&Width=40&Height=40" alt="Ảnh truy nã" class="rounded-circle me-3 border" width="40" height="40" onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png';">
                                        <div>
                                            <div class="fw-semibold">{{ $person->name }}</div>
                                            <div class="text-muted small">{{ $person->crime }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            </div>
                        </div>
                        <div class="mt-auto pt-2 text-center">
                            <a href="{{ route('wanted_list.index') }}" class="btn btn-danger btn-sm rounded-pill px-4">Xem tất cả</a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Chia sẻ kinh nghiệm nổi bật -->
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card h-100 shadow-sm border-0 rounded-4 bg-white p-3 d-flex flex-column">
                    <div class="card-body pb-2 d-flex flex-column" style="height: 100%">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-comments text-success fs-3 me-2"></i>
                            <span class="fw-bold fs-5">Chia sẻ kinh nghiệm nổi bật</span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-grid gap-3">
                            @forelse($topExperiences as $exp)
                                <div class="border-bottom pb-2">
                                    <a href="{{ route('experiences.show', $exp) }}" class="fw-semibold text-dark text-decoration-none">{{ $exp->title }}</a>
                                    <div class="text-muted small">{{ Str::limit($exp->content, 60) }}</div>
                                </div>
                            @empty
                                <div class="text-muted text-center mb-2">Chưa có bài chia sẻ nào</div>
                            @endforelse
                            </div>
                        </div>
                        <div class="mt-auto pt-2 text-center">
                            <a href="{{ route('experiences.index') }}" class="btn btn-success btn-sm rounded-pill px-4">Xem tất cả</a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Cảnh báo tội phạm nổi bật -->
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card h-100 shadow-sm border-0 rounded-4 bg-white p-3 d-flex flex-column">
                    <div class="card-body pb-2 d-flex flex-column" style="height: 100%">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-bullhorn text-danger fs-3 me-2"></i>
                            <span class="fw-bold fs-5">Cảnh báo tội phạm nổi bật</span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-grid gap-3">
                            @forelse($topAlerts as $alert)
                                <div class="border-bottom pb-2">
                                    <a href="{{ route('alerts.show', $alert) }}" class="fw-semibold text-dark text-decoration-none">{{ $alert->title }}</a>
                                    <div class="text-muted small">{{ Str::limit($alert->description, 60) }}</div>
                                </div>
                            @empty
                                <div class="text-muted text-center mb-2">Chưa có cảnh báo nào</div>
                            @endforelse
                            </div>
                        </div>
                        <div class="mt-auto pt-2 text-center">
                            <a href="{{ route('alerts.index') }}" class="btn btn-danger btn-sm rounded-pill px-4">Xem tất cả</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Block bài chia sẻ của bạn -->
        <div class="card border-0 shadow-sm mb-4 mt-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0">
                <h5 class="card-title mb-0"><i class="fas fa-comments me-2 text-success"></i>Bài chia sẻ của bạn</h5>
                <a href="{{ route('experiences.create') }}" class="btn btn-success btn-sm rounded-pill px-3"><i class="fas fa-plus-circle me-1"></i> Gửi bài mới</a>
            </div>
            <div class="card-body">
                @if(isset($myExperience) && $myExperience)
                    <div class="row align-items-center">
                        <div class="col-md-8 d-flex align-items-start gap-3">
                            <div class="flex-shrink-0">
                                <img src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name='.urlencode(auth()->user()->name).'&background=0D8ABC&color=fff' }}" alt="Avatar" class="rounded-circle border" width="64" height="64">
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-1 flex-wrap gap-2">
                                    <h5 class="fw-bold mb-0 me-2">{{ $myExperience->title }}</h5>
                                    <span class="badge {{ $myExperience->status == 'approved' ? 'bg-success' : ($myExperience->status == 'pending' ? 'bg-warning text-dark' : 'bg-danger') }} px-3 py-2 rounded-pill d-flex align-items-center" style="font-size: 0.95em;">
                                        @if($myExperience->status == 'pending')
                                            <i class="fas fa-hourglass-half me-1"></i> Chờ duyệt
                                        @elseif($myExperience->status == 'approved')
                                            Đã duyệt
                                        @else
                                            Từ chối
                                        @endif
                                    </span>
                                </div>
                                <div class="text-muted small mb-2"><i class="far fa-calendar-alt me-1"></i> {{ $myExperience->created_at->format('d/m/Y H:i') }}</div>
                                <div class="mb-3 text-gray-800" style="font-size: 1.08em; line-height: 1.7;">
                                    {{ Str::limit($myExperience->content, 120) }}
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <a href="{{ route('experiences.show', $myExperience) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fas fa-eye me-1"></i> Xem</a>
                                    @if((auth()->user()->isAdmin || auth()->id() === $myExperience->user_id) && $myExperience->status != 'approved')
                                        <a href="{{ route('experiences.edit', $myExperience) }}" class="btn btn-outline-success btn-sm rounded-pill px-3"><i class="fas fa-edit me-1"></i> Sửa</a>
                                        <form action="{{ route('experiences.destroy', $myExperience) }}" method="POST" class="d-inline form-delete">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3"><i class="fas fa-trash me-1"></i> Xóa</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-5">
                        <img src="https://cdn-icons-png.flaticon.com/512/4076/4076478.png" alt="No experiences" width="80" class="mb-3 opacity-50">
                        <h6 class="text-muted">Bạn chưa có bài chia sẻ nào</h6>
                        <a href="{{ route('experiences.create') }}" class="btn btn-success mt-2"><i class="fas fa-plus-circle me-1"></i> Gửi bài chia sẻ đầu tiên</a>
                    </div>
                @endif
            </div>
        </div>
        <!-- User Latest Alert Section (đồng bộ giao diện với bài chia sẻ) -->
        <div class="card border-0 shadow-sm mb-4 mt-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0">
                <h5 class="card-title mb-0"><i class="fas fa-bell me-2 text-primary"></i>Cảnh báo mới nhất của bạn</h5>
            </div>
            <div class="card-body">
                @if($myLatest)
                    <div class="row align-items-center">
                        <div class="col-md-8 d-flex align-items-start gap-3">
                            <div class="flex-shrink-0">
                                <img src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name='.urlencode(auth()->user()->name).'&background=0D8ABC&color=fff' }}" alt="Avatar" class="rounded-circle border" width="64" height="64">
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-1 flex-wrap gap-2">
                                    <h5 class="fw-bold mb-0 me-2">{{ $myLatest->title }}</h5>
                                    <span class="badge {{ $myLatest->status == 'approved' ? 'bg-success' : ($myLatest->status == 'pending' ? 'bg-warning text-dark' : 'bg-danger') }} px-3 py-2 rounded-pill d-flex align-items-center" style="font-size: 0.95em;">
                                        @if($myLatest->status == 'pending')
                                            <i class="fas fa-hourglass-half me-1"></i> Chờ duyệt
                                        @elseif($myLatest->status == 'approved')
                                            Đã duyệt
                                        @else
                                            Từ chối
                                        @endif
                                    </span>
                                </div>
                                <div class="text-muted small mb-2"><i class="far fa-calendar-alt me-1"></i> {{ $myLatest->created_at->format('d/m/Y H:i') }}</div>
                                <div class="mb-3 text-gray-800" style="font-size: 1.08em; line-height: 1.7;">
                                    {{ Str::limit($myLatest->description, 120) }}
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <a href="{{ route('alerts.show', $myLatest) }}" class="btn btn-outline-info btn-sm rounded-pill px-3"><i class="fas fa-eye me-1"></i> Xem</a>
                                    <a href="{{ route('alerts.edit', $myLatest) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fas fa-edit me-1"></i> Chỉnh sửa</a>
                                    <form action="{{ route('alerts.destroy', $myLatest) }}" method="POST" class="d-inline form-delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('Bạn có chắc chắn muốn xóa cảnh báo này?')">
                                            <i class="fas fa-trash me-1"></i> Xóa
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            @if($myLatest->image)
                                <img src="{{ asset('storage/' . $myLatest->image) }}" alt="Ảnh minh họa" class="img-fluid shadow border" style="max-width: 100%; max-height: 220px; object-fit: cover; border-radius: 12px;">
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <img src="https://cdn-icons-png.flaticon.com/512/4076/4076478.png" alt="No alerts" width="120" class="opacity-50">
                        </div>
                        <h5 class="text-muted mb-3">Bạn chưa đăng cảnh báo nào</h5>
                        <p class="text-muted mb-4">Hãy bắt đầu bằng cách tạo cảnh báo đầu tiên của bạn</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
    @if(auth()->user()->isAdmin)
        <!-- Admin Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Tổng cảnh báo</span>
                                <h3 class="mb-0 mt-1">{{ $totalAlerts }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary bg-opacity-10 text-primary rounded fs-4">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> {{ $totalAlertsPercent }}%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Chờ duyệt</span>
                                <h3 class="mb-0 mt-1">{{ $pendingAlerts }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning bg-opacity-10 text-warning rounded fs-4">
                                    <i class="fas fa-clock"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-danger bg-opacity-10 text-danger">
                                <i class="fas fa-arrow-down me-1"></i> {{ $pendingPercent }}%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Đã duyệt</span>
                                <h3 class="mb-0 mt-1">{{ $approvedAlerts }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success bg-opacity-10 text-success rounded fs-4">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> {{ $approvedPercent }}%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Từ chối</span>
                                <h3 class="mb-0 mt-1">{{ $rejectedAlerts }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger bg-opacity-10 text-danger rounded fs-4">
                                    <i class="fas fa-times-circle"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> {{ $rejectedPercent }}%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Tổng user</span>
                                <h3 class="mb-0 mt-1">{{ $totalUsers }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info bg-opacity-10 text-info rounded fs-4">
                                    <i class="fas fa-users"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> {{ $totalUsersPercent }}%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Tỷ lệ duyệt</span>
                                <h3 class="mb-0 mt-1">{{ $totalAlerts > 0 ? round(($approvedAlerts / $totalAlerts) * 100) : 0 }}%</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-secondary bg-opacity-10 text-secondary rounded fs-4">
                                    <i class="fas fa-percentage"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> 3%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Thống kê cảnh báo theo tháng</h5>
                            <div class="dropdown">
                                <button class="btn btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#">Xuất báo cáo</a></li>
                                    <li><a class="dropdown-item" href="#">Xem chi tiết</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <canvas id="alertsChart" height="180"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0">
                        <h5 class="card-title mb-0">Phân loại cảnh báo</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="flex-grow-1">
                            <canvas id="alertsPieChart" height="180"></canvas>
                        </div>
                        <div class="mt-3">
                            <div class="row text-center">
                                <div class="col-2">
                                    <span class="d-block fw-bold">{{ $typePercentsAdmin['Cướp giật'] ?? 0 }}%</span>
                                    <span class="text-muted small">Cướp giật</span>
                                </div>
                                <div class="col-2">
                                    <span class="d-block fw-bold">{{ $typePercentsAdmin['Trộm cắp'] ?? 0 }}%</span>
                                    <span class="text-muted small">Trộm cắp</span>
                                </div>
                                <div class="col-2">
                                    <span class="d-block fw-bold">{{ $typePercentsAdmin['Lừa đảo'] ?? 0 }}%</span>
                                    <span class="text-muted small">Lừa đảo</span>
                                </div>
                                <div class="col-2">
                                    <span class="d-block fw-bold">{{ $typePercentsAdmin['Bạo lực'] ?? 0 }}%</span>
                                    <span class="text-muted small">Bạo lực</span>
                                </div>
                                <div class="col-2">
                                    <span class="d-block fw-bold">{{ $typePercentsAdmin['Khác'] ?? 0 }}%</span>
                                    <span class="text-muted small">Khác</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables Row -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow rounded-4 border-0 mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top-4" style="padding: 1.25rem 1.5rem;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-bell text-primary fs-5"></i>
                            <h5 class="card-title mb-0 fw-bold">Cảnh báo mới nhất</h5>
                        </div>
                        <a href="{{ route('admin.alerts') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold">
                            Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body d-flex align-items-center gap-3" style="padding: 1.5rem;">
                        @if($latestAlert)
                            <img src="{{ $latestAlert->image ? asset('storage/' . $latestAlert->image) : 'https://ui-avatars.com/api/?name='.urlencode($latestAlert->title).'&background=random' }}" alt="Ảnh cảnh báo" class="rounded-circle border shadow-sm" width="56" height="56">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <span class="fw-bold fs-6">{{ $latestAlert->title }}</span>
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-3 py-2 rounded-pill">{{ $latestAlert->type ?? 'Không rõ' }}</span>
                                    <span class="badge {{ $latestAlert->status == 'approved' ? 'bg-success' : ($latestAlert->status == 'pending' ? 'bg-warning text-dark' : 'bg-danger') }} fw-semibold px-3 py-2 rounded-pill">
                                        @if($latestAlert->status == 'pending')
                                            <i class="fas fa-hourglass-half me-1"></i> Chờ duyệt
                                        @elseif($latestAlert->status == 'approved')
                                            Đã duyệt
                                        @else
                                            Từ chối
                                        @endif
                                    </span>
                                </div>
                                <div class="text-muted small mb-1">
                                    <i class="fas fa-user me-1"></i> {{ $latestAlert->user->name ?? 'N/A' }}
                                    <span class="mx-2">|</span>
                                    <i class="far fa-calendar-alt me-1"></i> {{ $latestAlert->created_at->format('d/m/Y') }}
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <a href="{{ route('alerts.show', $latestAlert) }}" class="btn btn-outline-info btn-sm rounded-pill px-3"><i class="fas fa-eye me-1"></i> Xem</a>
                                    @if($latestAlert->status == 'pending')
                                        <form action="{{ route('admin.alerts.approve', $latestAlert) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-success btn-sm rounded-pill px-3" title="Duyệt"><i class="fas fa-check me-1"></i> Duyệt</button>
                                        </form>
                                        <form action="{{ route('admin.alerts.reject', $latestAlert) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-danger btn-sm rounded-pill px-3" title="Từ chối"><i class="fas fa-times me-1"></i> Từ chối</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="text-center w-100 py-4">
                                <img src="https://cdn-icons-png.flaticon.com/512/4076/4076478.png" alt="No data" width="60" class="mb-2 opacity-50">
                                <p class="text-muted mb-0">Chưa có cảnh báo nào</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow rounded-4 border-0 mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top-4" style="padding: 1.25rem 1.5rem;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-comments text-success fs-5"></i>
                            <h5 class="card-title mb-0 fw-bold">Quản lý bài chia sẻ kinh nghiệm</h5>
                        </div>
                        <a href="{{ route('admin.experiences') }}" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-semibold">
                            Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body d-flex align-items-center gap-3" style="padding: 1.5rem;">
                        @if($latestExperience)
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <a href="{{ route('experiences.show', $latestExperience) }}" class="fw-bold fs-6 text-dark text-decoration-none">{{ $latestExperience->title }}</a>
                                    <span class="badge {{ $latestExperience->status == 'approved' ? 'bg-success' : ($latestExperience->status == 'pending' ? 'bg-warning text-dark' : 'bg-danger') }} fw-semibold px-3 py-2 rounded-pill">
                                        @if($latestExperience->status == 'pending')
                                            <i class="fas fa-hourglass-half me-1"></i> Chờ duyệt
                                        @elseif($latestExperience->status == 'approved')
                                            Đã duyệt
                                        @else
                                            Từ chối
                                        @endif
                                    </span>
                                </div>
                                <div class="text-muted small mb-1">
                                    <i class="fas fa-user me-1"></i> {{ $latestExperience->user->name ?? 'N/A' }}
                                    <span class="mx-2">|</span>
                                    <i class="far fa-calendar-alt me-1"></i> {{ $latestExperience->created_at->format('d/m/Y H:i') }}
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <a href="{{ route('experiences.show', $latestExperience) }}" class="btn btn-outline-info btn-sm rounded-pill px-3"><i class="fas fa-eye me-1"></i> Xem</a>
                                    @if($latestExperience->status == 'pending')
                                        <form action="{{ route('admin.experiences.approve', $latestExperience) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-success btn-sm rounded-pill px-3" title="Duyệt"><i class="fas fa-check me-1"></i> Duyệt</button>
                                        </form>
                                        <form action="{{ route('admin.experiences.reject', $latestExperience) }}" method="POST" class="d-inline form-reject">
                                            @csrf
                                            <button class="btn btn-warning btn-sm rounded-pill px-3" title="Từ chối"><i class="fas fa-times me-1"></i> Từ chối</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('admin.experiences.destroy', $latestExperience) }}" method="POST" class="d-inline form-delete">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm rounded-pill px-3" title="Xóa"><i class="fas fa-trash me-1"></i> Xóa</button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="text-center w-100 py-4">
                                <img src="https://cdn-icons-png.flaticon.com/512/4076/4076478.png" alt="No data" width="60" class="mb-2 opacity-50">
                                <p class="text-muted mb-0">Không có bài chia sẻ chờ duyệt</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@if(auth()->user()->isAdmin)
    <script>
        window.createdData = @json($createdData);
        window.approvedData = @json($approvedData);
        window.typePercentsAdmin = @json($typePercentsAdmin);
    </script>
@endif
@endsection
