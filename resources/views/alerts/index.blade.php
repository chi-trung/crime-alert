@extends('layouts.app')

@section('content')
@vite(['resources/css/alerts_index.css'])
<div class="container mt-4">
    <!-- Header với background cảnh sát -->
    <div class="alert-header bg-primary text-white rounded-4 p-4 mb-4 position-relative overflow-hidden">
        <div class="position-absolute top-0 end-0 opacity-10">
            <i class="fas fa-shield-alt fa-10x"></i>
        </div>
        <h1 class="display-5 fw-bold mb-3">Cảnh báo tội phạm cộng đồng</h1>
        <p class="lead mb-0">Cùng chung tay phòng chống tội phạm bằng cách chia sẻ thông tin</p>
    </div>

    <!-- Thanh công cụ tìm kiếm và bộ lọc -->
    <div class="card shadow-sm mb-4 border-0 rounded-4">
        <div class="card-body p-4">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="type" class="form-label fw-semibold">Loại tội phạm</label>
                    <select name="type" id="type" class="form-select border-2">
                        <option value="">Tất cả loại</option>
                        <option value="Cướp giật" {{ request('type') == 'Cướp giật' ? 'selected' : '' }}>Cướp giật</option>
                        <option value="Trộm cắp" {{ request('type') == 'Trộm cắp' ? 'selected' : '' }}>Trộm cắp</option>
                        <option value="Lừa đảo" {{ request('type') == 'Lừa đảo' ? 'selected' : '' }}>Lừa đảo</option>
                        <option value="Bạo lực" {{ request('type') == 'Bạo lực' ? 'selected' : '' }}>Bạo lực</option>
                        <option value="Khác" {{ request('type') == 'Khác' ? 'selected' : '' }}>Khác</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label fw-semibold">Trạng thái</label>
                    <select name="status" id="status" class="form-select border-2">
                        <option value="">Tất cả</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Chờ duyệt</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Đã duyệt</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Từ chối</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="location" class="form-label fw-semibold">Khu vực</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                        <input type="text" name="location" id="location" class="form-control border-2" 
                               value="{{ request('location') }}" placeholder="Nhập khu vực...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="q" class="form-label fw-semibold">Tìm kiếm</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="q" id="q" class="form-control border-2" 
                               value="{{ request('q') }}" placeholder="Tìm theo tiêu đề...">
                    </div>
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-filter me-1"></i> Lọc
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Thông báo khi không có cảnh báo -->
    @if($alerts->count() === 0)
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body text-center p-5">
                <h4 class="text-muted mb-3">Chưa có cảnh báo nào phù hợp</h4>
                <p class="text-muted mb-4">Hãy thử thay đổi bộ lọc hoặc tạo cảnh báo mới</p>
            </div>
        </div>
    @else
        <!-- Danh sách cảnh báo -->
        <div class="row g-4">
            @foreach($alerts as $alert)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden hover-shadow-lg transition-all">
                        <!-- Badge trạng thái -->
                        <div class="position-absolute top-0 end-0 m-3">
                            @if($alert->status == 'approved')
                                <span class="badge bg-success bg-opacity-90 text-white">
                                    <i class="fas fa-check-circle me-1"></i> Đã duyệt
                                </span>
                            @elseif($alert->status == 'pending')
                                <span class="badge bg-warning bg-opacity-90 text-dark">
                                    <i class="fas fa-clock me-1"></i> Chờ duyệt
                                </span>
                            @else
                                <span class="badge bg-danger bg-opacity-90 text-white">
                                    <i class="fas fa-times-circle me-1"></i> Từ chối
                                </span>
                            @endif
                        </div>
                        
                        <!-- Ảnh cảnh báo -->
                        @if($alert->image)
                            <a href="{{ route('alerts.show', $alert) }}">
                                <div class="card-img-top overflow-hidden" style="height: 200px;">
                                    <img src="{{ asset('storage/' . $alert->image) }}" 
                                         class="img-fluid w-100 h-100 object-fit-cover transition-all"
                                         alt="Ảnh cảnh báo">
                                </div>
                            </a>
                        @else
                            <a href="{{ route('alerts.show', $alert) }}">
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="fas fa-exclamation-triangle fa-4x text-muted opacity-50"></i>
                                </div>
                            </a>
                        @endif
                        
                        <div class="card-body">
                            <!-- Loại tội phạm -->
                            <div class="mb-2">
                                <span class="badge bg-danger bg-opacity-10 text-danger">
                                    {{ $alert->type ?? 'Không rõ' }}
                                </span>
                            </div>
                            
                            <!-- Tiêu đề -->
                            <h5 class="card-title mb-2">
                                <a href="{{ route('alerts.show', $alert) }}" class="text-decoration-none text-dark hover-text-primary">
                                    {{ $alert->title }}
                                </a>
                            </h5>
                            
                            <!-- Mô tả ngắn -->
                            <p class="card-text text-muted mb-3">
                                {{ Str::limit($alert->description, 100) }}
                            </p>
                            
                            <!-- Thông tin vị trí và thời gian -->
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                    <small class="text-muted">{{ $alert->location ?? 'Không rõ' }}</small>
                                </div>
                                <div>
                                    <i class="fas fa-clock text-muted me-1"></i>
                                    <small class="text-muted">{{ $alert->created_at->diffForHumans() }}</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Footer với số lượt xem và người đăng -->
                        <div class="card-footer bg-transparent border-0 pt-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary me-2">
                                        <i class="fas fa-user"></i> {{ $alert->user->name ?? 'Ẩn danh' }}
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-comments me-1"></i> {{ $alert->comments_count ?? 0 }} bình luận
                                </small>
                                <a href="{{ route('alerts.show', $alert) }}" class="btn btn-sm btn-outline-primary">
                                    Xem chi tiết <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        <!-- Phân trang -->
        <div class="d-flex justify-content-center mt-5">
            {{ $alerts->links() }}
        </div>
    @endif
    
    <!-- Call to action -->
    <div class="card shadow-sm mt-5 border-0 rounded-4 bg-light">
        <div class="card-body text-center p-4">
            <h3 class="mb-3">Bạn có thông tin về tội phạm?</h3>
            <p class="text-muted mb-4">Hãy chia sẻ ngay để cảnh báo cộng đồng và giúp đỡ mọi người phòng tránh</p>
            <a href="{{ route('alerts.create') }}" class="btn btn-danger px-4 py-2">
                <i class="fas fa-bell me-2"></i> Tạo cảnh báo ngay
            </a>
        </div>
    </div>
</div>

@endsection

@section('scripts')
@parent
@endsection