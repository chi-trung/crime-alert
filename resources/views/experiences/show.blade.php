@extends('layouts.app')
@section('title', "{{ $experience->title }} - Crime Alert Web")
@section('content')
<link rel="stylesheet" href="{{ asset('css/experiences_show.css') }}">
{{-- Issue #398: the share popup's keyboard/focus/ARIA behaviour. Loaded before
     experiences_show.js, which only wires the experience-side like button. --}}
<script src="{{ asset('js/share_popup.js') }}"></script>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <!-- Card bài chia sẻ -->
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
                {{-- Issue #133: this block guarded $experience->image, but the
                     post's uploaded picture lives in the `avatar` column
                     (ExperienceController::store line 64; the #53 deleting
                     cascade reads it too). The image never rendered. --}}
                @if($experience->avatar)
                    <div class="alert-image-container" style="max-height: 400px; overflow: hidden;">
                        <img src="{{ asset('storage/' . $experience->avatar) }}" class="img-fluid w-100" alt="Ảnh minh họa" style="object-fit: cover;">
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
                                {{ $experience->created_at->format('d/m/Y H:i') }}
                            </span>
                            <span class="text-muted small">
                                {{ $experience->user->name ?? $experience->name ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                    <!-- Nút chia sẻ -->
                    <div class="mb-3 position-relative d-inline-block">
                        <button class="btn btn-outline-success btn-sm rounded-pill" id="share-btn-exp" aria-haspopup="true" aria-expanded="false" aria-label="Chia sẻ bài viết này">
                            <i class="fas fa-share-alt"></i> Chia sẻ
                        </button>
                        <div id="share-popup-exp" role="dialog" aria-label="Chia sẻ" style="display:none;position:absolute;left:0;top:100%;min-width:180px;background:#fff;border:1px solid #eee;padding:10px 16px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.13);z-index:9999;">
                            <div class="d-flex flex-column align-items-start gap-2">
                                <a href="#" id="share-fb-exp" class="btn btn-light w-100 text-start" target="_blank" rel="noopener" style="font-weight:500;"><i class="fab fa-facebook text-primary me-2"></i> Facebook</a>
                                <a href="#" id="share-x-exp" class="btn btn-light w-100 text-start" target="_blank" rel="noopener" style="font-weight:500;">
                                    <span style="display:inline-block;width:1.2em;vertical-align:middle;margin-right:8px;">
                                        <svg viewBox="0 0 1200 1227" width="18" height="18" fill="currentColor" style="vertical-align:middle;" aria-hidden="true"><path d="M1199.99 0H949.19L600.01 494.09L250.81 0H0L489.09 701.81L0 1227H250.81L600.01 732.91L949.19 1227H1200L710.91 525.19L1199.99 0ZM300.01 111.09L600.01 545.45L900.01 111.09H1050.91L600.01 801.09L149.09 111.09H300.01ZM149.09 1115.91L600.01 425.91L1050.91 1115.91H900.01L600.01 681.55L300.01 1115.91H149.09Z"></path></svg>
                                    </span>
                                    X
                                </a>
                            </div>
                        </div>
                    </div>
                    {{-- Issue #398: this inline block duplicated the share
                         popup logic (and missed keyboard/focus/ARIA entirely).
                         Both show pages now load public/js/share_popup.js;
                         this copy is deleted the same way #351 removed the
                         duplicated login toggle. --}}
                    <div class="alert-details mb-4">
                        <div class="mb-3">
                            <h5 class="fw-semibold mb-2 text-success">Nội dung chia sẻ</h5>
                            <p class="card-text ps-4" style="white-space:pre-line;">{{ $experience->content }}</p>
                        </div>
                    </div>
                    <!-- Nút hành động + like ở footer -->
                    <div class="border-top pt-3 mt-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="{{ route('experiences.index') }}" class="btn btn-outline-success rounded-pill">
                                    Quay lại
                                </a>
                                @if(auth()->check() && (auth()->user()->isAdmin || auth()->id() === $experience->user_id))
                                    <a href="{{ route('experiences.edit', $experience) }}" class="btn btn-success rounded-pill">
                                        Sửa
                                    </a>
                                    <form action="{{ route('experiences.destroy', $experience) }}" method="POST" class="d-inline form-delete">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger rounded-pill">
                                            Xóa
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <div>
                                {{-- Issue #104: like writes are approved-only server-side (LikeController
                                    gate, mirroring #96) — hide the button on unapproved posts like the
                                    comment form below, so owners/admins previewing a pending post don't
                                    get a dead button that 403s. --}}
                                @if($experience->status == 'approved')
                                @auth
                                <button id="like-btn-exp" class="btn-like-custom{{ $experience->likes()->where('user_id', auth()->id())->exists() ? ' liked' : '' }}" data-liked="{{ $experience->likes()->where('user_id', auth()->id())->exists() ? '1' : '0' }}" data-id="{{ $experience->id }}" data-type="experience" aria-label="Thích bài chia sẻ này">
                                    <span id="like-text-exp">{{ $experience->likes()->where('user_id', auth()->id())->exists() ? 'Đã Thích' : 'Thích' }}</span> (<span id="like-count-exp">{{ $experience->likes()->count() }}</span>)
                                </button>
                                @else
                                {{-- Issue #386: see alerts/show.blade.php — the guest branch repeated
                                     id="like-count-exp", which public/js/experiences_show.js:36
                                     selects by bare id. This branch is a plain login link with no
                                     JS, so it ships the class instead. --}}
                                <a href="{{ route('login') }}" class="btn-like-custom" title="Đăng nhập để thích" aria-label="Đăng nhập để thích bài chia sẻ này">
                                    Thích (<span class="like-count">{{ $experience->likes()->count() }}</span>)
                                </a>
                                @endauth
                                @endif
                                {{-- Issue #414: a failed like used to surface through a raw alert() —
                                    a blocking, styleless dialog with no semantics. Replaced by a
                                    server-side live region the JS only writes textContent into, so a
                                    static-DOM reader sees it too. Bootstrap's .sr-only is not loaded
                                    by this app, so the clip ships inline; display:none would remove
                                    the region from the accessibility tree and undo the fix. --}}
                                <span class="like-status sr-only" role="status" aria-live="polite" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Phần bình luận -->
            <div class="card border-0 shadow-sm rounded-3 mt-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-4 d-flex align-items-center">
                        Bình luận
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
                            @if(!auth()->user()->hasVerifiedEmail())
                                <div class="alert alert-warning rounded-3">
                                    Bạn cần xác thực email để bình luận. <a href="{{ route('verification.notice') }}" class="alert-link">Xác thực ngay</a>
                                </div>
                            @else
                                <form action="{{ route('comments.store') }}" method="POST" class="mb-4">
                                    @csrf
                                    <input type="hidden" name="experience_id" value="{{ $experience->id }}">
                                    <div class="mb-3">
                                        <textarea name="content" class="form-control rounded-3" rows="3" placeholder="Viết bình luận của bạn..." required aria-label="Nội dung bình luận">{{ old('content') }}</textarea>
                                        @error('content')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <button type="submit" class="btn btn-success rounded-pill px-4">
                                        Gửi bình luận
                                    </button>
                                </form>
                            @endif
                        @else
                            <div class="alert alert-info rounded-3">
                                Vui lòng <a href="{{ route('login') }}" class="alert-link">đăng nhập</a> để bình luận.
                            </div>
                        @endauth
                    @else
                        <div class="alert alert-warning rounded-3">
                            Chỉ bình luận khi bài chia sẻ đã được duyệt.
                        </div>
                    @endif
                    <div class="comments-section">
                        @php
                            // Issue #258: flat whole-thread fetch + PHP parent_id
                            // map, replacing #25's fixed depth-2 eager chain (the
                            // N+1 had returned one level down). Ordering idiom
                            // as in alerts/show.blade.php: ASC fetch, reversed
                            // roots for the #89 latest/id-DESC top level.
                            $children = $experience->comments()->with(['user', 'likes'])
                                ->orderBy('created_at')->orderBy('id')->get()
                                ->groupBy(fn ($comment) => $comment->parent_id ?? 0);
                            $comments = ($children[0] ?? collect())->reverse();
                        @endphp
                        @forelse($comments as $comment)
                            @include('comments._item', ['comment' => $comment, 'children' => $children, 'parentType' => 'experience', 'parentId' => $experience->id])
                        @empty
                            <div class="text-center py-4">
                                
                                <p class="text-muted">Chưa có bình luận nào.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.LIKE_STORE_URL = "{{ route('like.store') }}";
window.LIKE_DESTROY_URL = "{{ route('like.destroy') }}";
window.CSRF_TOKEN = document.querySelector('meta[name=\'csrf-token\']').getAttribute('content');
</script>
<script src="{{ asset('js/experiences_show.js') }}"></script>
{{-- Issue #398: the experience share button's popup wiring. The ids are
     experience-specific, the behaviour is shared with the alert page via
     public/js/share_popup.js. --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    initSharePopup({
        trigger: 'share-btn-exp',
        popup: 'share-popup-exp',
        facebook: 'share-fb-exp',
        x: 'share-x-exp',
    });
});
</script>
@endsection 