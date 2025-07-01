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
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="d-flex justify-content-between align-items-center like-inline">
                                    <h2 class="card-title fw-bold mb-0 text-danger">{{ $alert->title }}</h2>
                                </div>
                                <span class="badge bg-danger bg-opacity-10 text-danger py-2 px-3 rounded-pill">
                                    {{ $alert->type ?? 'Không rõ' }}
                                </span>
                            </div>
                            <span class="text-muted small d-block">
                                <i class="far fa-calendar-alt me-1"></i> {{ $alert->created_at->format('d/m/Y H:i') }}
                            </span>
                            <span class="text-muted small">
                                <i class="far fa-user me-1"></i> {{ $alert->user->name ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Nút chia sẻ -->
                    <div class="mb-3 position-relative d-inline-block">
                        <button class="btn btn-outline-primary btn-sm rounded-pill" id="share-btn-alert" onclick="toggleSharePopupAlert(event)">
                            <i class="fas fa-share-alt"></i> Chia sẻ
                        </button>
                        <div id="share-popup-alert" style="display:none;position:absolute;left:0;top:100%;min-width:180px;background:#fff;border:1px solid #eee;padding:10px 16px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.13);z-index:9999;">
                            <div class="d-flex flex-column align-items-start gap-2">
                                <a href="#" id="share-fb-alert" class="btn btn-light w-100 text-start" target="_blank" style="font-weight:500;"><i class="fab fa-facebook text-primary me-2"></i> Facebook</a>
                                <a href="#" id="share-x-alert" class="btn btn-light w-100 text-start" target="_blank" style="font-weight:500;">
                                    <span style="display:inline-block;width:1.2em;vertical-align:middle;margin-right:8px;">
                                        <svg viewBox="0 0 1200 1227" width="18" height="18" fill="currentColor" style="vertical-align:middle;"><path d="M1199.99 0H949.19L600.01 494.09L250.81 0H0L489.09 701.81L0 1227H250.81L600.01 732.91L949.19 1227H1200L710.91 525.19L1199.99 0ZM300.01 111.09L600.01 545.45L900.01 111.09H1050.91L600.01 801.09L149.09 111.09H300.01ZM149.09 1115.91L600.01 425.91L1050.91 1115.91H900.01L600.01 681.55L300.01 1115.91H149.09Z"></path></svg>
                                    </span>
                                    X
                                </a>
                            </div>
                        </div>
                    </div>
                    <script>
                    function toggleSharePopupAlert(e) {
                        e.stopPropagation();
                        var popup = document.getElementById('share-popup-alert');
                        var url = window.location.href;
                        document.getElementById('share-fb-alert').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
                        document.getElementById('share-x-alert').href = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url);
                        popup.style.display = (popup.style.display === 'block' ? 'none' : 'block');
                        document.addEventListener('click', closeSharePopupAlert);
                    }
                    function closeSharePopupAlert(e) {
                        var popup = document.getElementById('share-popup-alert');
                        if (popup && !popup.contains(e.target) && e.target.id !== 'share-btn-alert') {
                            popup.style.display = 'none';
                            document.removeEventListener('click', closeSharePopupAlert);
                        }
                    }
                    </script>
                    
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
                    
                    <!-- Footer card: nút hành động + like -->
                    <div class="border-top pt-3 mt-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="{{ route('alerts.index') }}" class="btn btn-outline-secondary rounded-pill">
                                    <i class="fas fa-arrow-left me-1"></i> Quay lại
                                </a>
                                @if(auth()->check() && (auth()->user()->isAdmin || auth()->id() === $alert->user_id))
                                    <a href="{{ route('admin.alerts.edit', $alert) }}" class="btn btn-primary rounded-pill">
                                        <i class="fas fa-edit me-1"></i> Sửa
                                    </a>
                                    <form action="{{ route('admin.alerts.destroy', $alert) }}" method="POST" class="d-inline form-delete">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger rounded-pill">
                                            <i class="fas fa-trash-alt me-1"></i> Xóa
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <div>
                                @auth
                                <button id="like-btn-alert" class="btn-like-custom{{ $alert->likes()->where('user_id', auth()->id())->exists() ? ' liked' : '' }}" data-liked="{{ $alert->likes()->where('user_id', auth()->id())->exists() ? '1' : '0' }}" data-id="{{ $alert->id }}" data-type="alert">
                                    <span id="like-text-alert">{{ $alert->likes()->where('user_id', auth()->id())->exists() ? 'Đã Thích' : 'Thích' }}</span> (<span id="like-count-alert">{{ $alert->likes()->count() }}</span>)
                                </button>
                                <script>
                                document.getElementById('like-btn-alert').addEventListener('click', async function(e) {
                                    e.preventDefault();
                                    const btn = this;
                                    const liked = btn.getAttribute('data-liked') === '1';
                                    const id = btn.getAttribute('data-id');
                                    const type = btn.getAttribute('data-type');
                                    btn.disabled = true;
                                    try {
                                        const res = await fetch(liked ? '{{ route('like.destroy') }}' : '{{ route('like.store') }}', {
                                            method: liked ? 'DELETE' : 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                'Accept': 'application/json',
                                            },
                                            body: JSON.stringify({ type, id })
                                        });
                                        const data = await res.json();
                                        if (data.success) {
                                            btn.setAttribute('data-liked', liked ? '0' : '1');
                                            document.getElementById('like-count-alert').textContent = data.count;
                                            document.getElementById('like-text-alert').textContent = liked ? 'Thích' : 'Đã Thích';
                                            btn.classList.toggle('liked', !liked);
                                        } else if(data.redirect) {
                                            window.location.href = data.redirect;
                                        }
                                    } catch (err) {
                                        alert('Có lỗi xảy ra!');
                                    }
                                    btn.disabled = false;
                                });
                                </script>
                                @else
                                <a href="{{ route('login') }}" class="btn-like-custom" title="Đăng nhập để thích">
                                    Thích (<span id="like-count-alert">{{ $alert->likes()->count() }}</span>)
                                </a>
                                @endauth
                            </div>
                        </div>
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
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show rounded-3">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    @if($alert->status == 'approved')
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
                    @else
                        <div class="alert alert-warning rounded-3">
                            <i class="fas fa-info-circle me-2"></i> Chỉ bình luận khi cảnh báo đã được duyệt.
                        </div>
                    @endif
                    
                    <div class="comments-section">
                        @php
                            $comments = $alert->comments()->whereNull('parent_id')->latest()->get();
                        @endphp
                        @forelse($comments as $comment)
                            @include('comments._item', ['comment' => $comment, 'parentType' => 'alert', 'parentId' => $alert->id])
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
    
    .like-inline {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 0.5rem;
    }
    .btn-like-custom {
        border: 2px solid #e63946;
        background: #fff;
        color: #e63946;
        border-radius: 22px;
        padding: 6px 22px;
        font-weight: 600;
        font-size: 1.08rem;
        transition: all 0.18s;
        cursor: pointer;
        outline: none;
        box-shadow: none;
        min-width: 110px;
        display: inline-block;
    }
    .btn-like-custom.liked {
        background: #e63946;
        color: #fff;
        border: 2px solid #e63946;
    }
    .btn-like-custom:not(.liked):hover {
        background: #ffe6ea;
        color: #e63946;
        border: 2px solid #e63946;
    }
    .btn-like-custom.liked:hover {
        background: #d62839;
        color: #fff;
        border: 2px solid #e63946;
    }
</style>

@endsection