@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <!-- Card cảnh báo chính -->
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
                @if($alert->image)
                    <div class="alert-image-container" style="max-height: 400px; overflow: hidden;">
                        <img src="{{ asset('storage/' . $alert->image) }}" class="img-fluid w-100" alt="Ảnh cảnh báo" style="object-fit: cover;">
                    </div>
                @endif
                
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="card-title fw-bold mb-2 text-danger">{{ $alert->title }}</h2>
                            <span class="badge bg-danger bg-opacity-10 text-danger py-2 px-3 rounded-pill">
                                {{ $alert->type ?? 'Không rõ' }}
                            </span>
                        </div>
                        <div class="text-end">
                            <span class="text-muted small d-block">
                                <i class="far fa-calendar-alt me-1"></i> {{ $alert->created_at->format('d/m/Y H:i') }}
                            </span>
                            <span class="text-muted small">
                                <i class="far fa-user me-1"></i> {{ $alert->user->name ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="alert-details mb-4">
                        <div class="mb-3">
                            <h5 class="fw-semibold mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Mô tả</h5>
                            <p class="card-text ps-4">{{ $alert->description }}</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h5 class="fw-semibold mb-2"><i class="fas fa-map-marker-alt text-primary me-2"></i>Vị trí</h5>
                                @if($alert->location)
                                    <p class="ps-4">{{ $alert->location }}</p>
                                @elseif($alert->latitude && $alert->longitude)
                                    <p class="ps-4">
                                        <a href="https://www.google.com/maps?q={{ $alert->latitude }},{{ $alert->longitude }}" target="_blank" class="text-primary fw-bold">
                                            Xem trên bản đồ
                                        </a>
                                    </p>
                                @else
                                    <p class="ps-4 text-muted">Không rõ</p>
                                @endif
                            </div>
                            <div class="col-md-6 mb-3">
                                <h5 class="fw-semibold mb-2"><i class="fas fa-tag text-primary me-2"></i>Trạng thái</h5>
                                <p class="ps-4">
                                    @if($alert->status == 'pending')
                                        <span class="badge bg-warning bg-opacity-25 text-warning-emphasis py-2 px-3 rounded-pill">
                                            <i class="fas fa-clock me-1"></i> Chờ duyệt
                                        </span>
                                    @elseif($alert->status == 'approved')
                                        <span class="badge bg-success bg-opacity-25 text-success-emphasis py-2 px-3 rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i> Đã duyệt
                                        </span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-25 text-danger-emphasis py-2 px-3 rounded-pill">
                                            <i class="fas fa-times-circle me-1"></i> Từ chối
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nút hành động -->
                    <div class="d-flex flex-wrap gap-2 border-top pt-3">
                        <a href="{{ route('alerts.index') }}" class="btn btn-outline-secondary rounded-pill">
                            <i class="fas fa-arrow-left me-1"></i> Quay lại
                        </a>
                        
                        @if(auth()->check() && (auth()->user()->isAdmin || auth()->id() === $alert->user_id))
                            <a href="{{ route('admin.alerts.edit', $alert) }}" class="btn btn-primary rounded-pill">
                                <i class="fas fa-edit me-1"></i> Sửa
                            </a>
                            <form action="{{ route('admin.alerts.destroy', $alert) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger rounded-pill" onclick="return confirm('Bạn có chắc chắn muốn xóa cảnh báo này?')">
                                    <i class="fas fa-trash-alt me-1"></i> Xóa
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Phần bình luận -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-4 d-flex align-items-center">
                        <i class="fas fa-comments text-primary me-2"></i> Bình luận
                        <span class="badge bg-primary bg-opacity-10 text-primary ms-2 rounded-pill">
                            {{ $alert->comments()->count() }}
                        </span>
                    </h4>
                    
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show rounded-3">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    @auth
                        <form action="{{ route('comments.store') }}" method="POST" class="mb-4">
                            @csrf
                            <input type="hidden" name="alert_id" value="{{ $alert->id }}">
                            <div class="mb-3">
                                <textarea name="content" class="form-control rounded-3" rows="3" placeholder="Viết bình luận của bạn..." required>{{ old('content') }}</textarea>
                                @error('content')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="fas fa-paper-plane me-1"></i> Gửi bình luận
                            </button>
                        </form>
                    @else
                        <div class="alert alert-info rounded-3">
                            <i class="fas fa-info-circle me-2"></i> Vui lòng <a href="{{ route('login') }}" class="alert-link">đăng nhập</a> để bình luận.
                        </div>
                    @endauth
                    
                    <div class="comments-section">
                        @forelse($alert->comments()->latest()->get() as $comment)
                            <div class="comment-item mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="ms-2">
                                            <strong class="d-block">{{ $comment->user->name ?? 'Ẩn danh' }}</strong>
                                            <span class="text-muted small">
                                                <i class="far fa-clock me-1"></i> {{ $comment->created_at->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>
                                    @if(auth()->check() && (auth()->user()->isAdmin || auth()->id() === $comment->user_id))
                                        <form action="{{ route('comments.destroy', $comment) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" onclick="return confirm('Xóa bình luận này?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                <div class="comment-content ps-4">
                                    {{ $comment->content }}
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4">
                                <i class="far fa-comment-dots text-muted fa-2x mb-2"></i>
                                <p class="text-muted">Chưa có bình luận nào.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .alert-image-container {
        position: relative;
        overflow: hidden;
    }
    
    .alert-image-container::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 50px;
        background: linear-gradient(to top, rgba(0,0,0,0.3), transparent);
        z-index: 1;
    }
    
    .comment-item:hover {
        background-color: rgba(0,0,0,0.02);
    }
    
    .card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
    }
</style>

@endsection