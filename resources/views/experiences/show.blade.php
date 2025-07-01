@extends('layouts.app')
@section('content')
@vite(['resources/css/experiences_show.css', 'resources/js/experiences_show.js'])
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <!-- Card bài chia sẻ -->
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
                @if($experience->image)
                    <div class="alert-image-container" style="max-height: 400px; overflow: hidden;">
                        <img src="{{ asset('storage/' . $experience->image) }}" class="img-fluid w-100" alt="Ảnh minh họa" style="object-fit: cover;">
                    </div>
                @endif
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="card-title fw-bold mb-2 text-success">{{ $experience->title }}</h2>
                            <span class="badge {{ $experience->status == 'approved' ? 'bg-success' : ($experience->status == 'pending' ? 'bg-warning text-dark' : 'bg-danger') }} py-2 px-3 rounded-pill">
                                @if($experience->status == 'pending')
                                    <i class="fas fa-hourglass-half me-1"></i> Chờ duyệt
                                @elseif($experience->status == 'approved')
                                    Đã duyệt
                                @else
                                    Từ chối
                                @endif
                            </span>
                        </div>
                        <div class="text-end">
                            <span class="text-muted small d-block">
                                <i class="far fa-calendar-alt me-1"></i> {{ $experience->created_at->format('d/m/Y H:i') }}
                            </span>
                            <span class="text-muted small">
                                <i class="far fa-user me-1"></i> {{ $experience->user->name ?? $experience->name ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                    <div class="alert-details mb-4">
                        <div class="mb-3">
                            <h5 class="fw-semibold mb-2 text-success"><i class="fas fa-info-circle me-2"></i>Nội dung chia sẻ</h5>
                            <p class="card-text ps-4" style="white-space:pre-line;">{{ $experience->content }}</p>
                        </div>
                    </div>
                    <!-- Nút hành động + like ở footer -->
                    <div class="border-top pt-3 mt-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="{{ route('experiences.index') }}" class="btn btn-outline-success rounded-pill">
                                    <i class="fas fa-arrow-left me-1"></i> Quay lại
                                </a>
                                @if(auth()->check() && (auth()->user()->isAdmin || auth()->id() === $experience->user_id))
                                    <a href="{{ route('experiences.edit', $experience) }}" class="btn btn-success rounded-pill">
                                        <i class="fas fa-edit me-1"></i> Sửa
                                    </a>
                                    <form action="{{ route('experiences.destroy', $experience) }}" method="POST" class="d-inline form-delete">
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
                                <button id="like-btn-exp" class="btn-like-custom{{ $experience->likes()->where('user_id', auth()->id())->exists() ? ' liked' : '' }}" data-liked="{{ $experience->likes()->where('user_id', auth()->id())->exists() ? '1' : '0' }}" data-id="{{ $experience->id }}" data-type="experience">
                                    <span id="like-text-exp">{{ $experience->likes()->where('user_id', auth()->id())->exists() ? 'Đã Thích' : 'Thích' }}</span> (<span id="like-count-exp">{{ $experience->likes()->count() }}</span>)
                                </button>
                                
                                @else
                                <a href="{{ route('login') }}" class="btn-like-custom" title="Đăng nhập để thích">
                                    Thích (<span id="like-count-exp">{{ $experience->likes()->count() }}</span>)
                                </a>
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Phần bình luận -->
            <div class="card border-0 shadow-sm rounded-3 mt-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-4 d-flex align-items-center">
                        <i class="fas fa-comments text-success me-2"></i> Bình luận
                        <span class="badge bg-success bg-opacity-10 text-success ms-2 rounded-pill">
                            {{ $experience->comments()->count() }}
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
                    @if($experience->status == 'approved')
                        @auth
                            <form action="{{ route('comments.store') }}" method="POST" class="mb-4">
                                @csrf
                                <input type="hidden" name="experience_id" value="{{ $experience->id }}">
                                <div class="mb-3">
                                    <textarea name="content" class="form-control rounded-3" rows="3" placeholder="Viết bình luận của bạn..." required>{{ old('content') }}</textarea>
                                    @error('content')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn btn-success rounded-pill px-4">
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
                            <i class="fas fa-info-circle me-2"></i> Chỉ bình luận khi bài chia sẻ đã được duyệt.
                        </div>
                    @endif
                    <div class="comments-section">
                        @php
                            $comments = $experience->comments()->whereNull('parent_id')->latest()->get();
                        @endphp
                        @forelse($comments as $comment)
                            @include('comments._item', ['comment' => $comment, 'parentType' => 'experience', 'parentId' => $experience->id])
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

@endsection 