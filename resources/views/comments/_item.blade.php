<div class="comment-item mb-3 pb-3 @if(empty($level) || $level == 0) border-bottom @endif ms-{{ isset($level) ? $level * 4 : 0 }}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="d-flex align-items-center">
            <div class="avatar bg-{{ $parentType === 'alert' ? 'primary' : 'success' }} bg-opacity-10 text-{{ $parentType === 'alert' ? 'primary' : 'success' }} rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="fas fa-user"></i>
            </div>
            <div class="ms-2">
                <strong class="d-block">{{ $comment->user->name ?? 'Ẩn danh' }}</strong>
                <span class="text-muted small">
                    <i class="far fa-clock me-1"></i> {{ $comment->created_at->diffForHumans() }}
                </span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @auth
                <button class="btn btn-link btn-sm text-decoration-none text-primary px-2 py-0 reply-btn" data-comment-id="{{ $comment->id }}"><i class="fas fa-reply me-1"></i>Trả lời</button>
            @endauth
            @if(auth()->check() && (auth()->user()->isAdmin || auth()->id() === $comment->user_id))
                <a href="{{ route('comments.edit', $comment) }}" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fas fa-edit"></i></a>
                <form action="{{ route('comments.destroy', $comment) }}" method="POST" class="d-inline form-delete">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill"><i class="fas fa-trash-alt"></i></button>
                </form>
            @endif
            @auth
                <button type="button" class="btn btn-like-comment px-2 py-0{{ $comment->likes()->where('user_id', auth()->id())->exists() ? ' liked' : '' }}" data-id="{{ $comment->id }}" data-liked="{{ $comment->likes()->where('user_id', auth()->id())->exists() ? '1' : '0' }}">
                    <i class="fa-heart {{ $comment->likes()->where('user_id', auth()->id())->exists() ? 'fa-solid text-danger' : 'fa-regular text-secondary' }}"></i>
                    <span class="like-count">{{ $comment->likes()->count() }}</span>
                </button>
            @else
                <a href="{{ route('login') }}" class="btn btn-like-comment px-2 py-0" title="Đăng nhập để thích">
                    <i class="fa-regular fa-heart text-secondary"></i>
                    <span class="like-count">{{ $comment->likes()->count() }}</span>
                </a>
            @endauth
        </div>
    </div>
    <div class="comment-content ps-4">{{ $comment->content }}</div>
    <!-- Form reply (ẩn/hiện bằng JS) -->
    <div class="reply-form-container mt-2" id="reply-form-{{ $comment->id }}" style="display:none;">
        <form action="{{ route('comments.store') }}" method="POST">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
            <input type="hidden" name="{{ $parentType }}_id" value="{{ $parentId }}">
            <div class="mb-2">
                <textarea name="content" class="form-control rounded-3" rows="2" placeholder="Viết trả lời..." required></textarea>
            </div>
            <button type="submit" class="btn btn-success btn-sm rounded-pill px-3"><i class="fas fa-reply me-1"></i> Gửi trả lời</button>
            <button type="button" class="btn btn-link btn-sm text-secondary cancel-reply-btn" data-comment-id="{{ $comment->id }}">Hủy</button>
        </form>
    </div>
    <!-- Hiển thị replies lồng nhau -->
    @foreach($comment->replies()->orderBy('created_at')->get() as $reply)
        @include('comments._item', ['comment' => $reply, 'parentType' => $parentType, 'parentId' => $parentId, 'level' => (isset($level) ? $level + 1 : 1)])
    @endforeach
</div>
<style>
.btn-like-comment {
    background: none;
    border: none;
    outline: none;
    box-shadow: none;
    font-size: 1.1rem;
    color: #888;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    border-radius: 50px;
    transition: color 0.18s, background 0.18s, transform 0.18s;
}
.btn-like-comment.liked .fa-heart {
    color: #e63946 !important;
}
.btn-like-comment:not(.liked):hover .fa-heart {
    color: #e63946 !important;
    transform: scale(1.13);
}
.comment-item {
    background: none !important;
    border: none !important;
    box-shadow: none !important;
    padding-left: 0;
}
.comment-item.border-bottom {
    border-bottom: 1.5px solid #e9ecef !important;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.reply-btn').forEach(function(btn) {
        btn.onclick = function() {
            var id = btn.getAttribute('data-comment-id');
            document.getElementById('reply-form-' + id).style.display = 'block';
        };
    });
    document.querySelectorAll('.cancel-reply-btn').forEach(function(btn) {
        btn.onclick = function() {
            var id = btn.getAttribute('data-comment-id');
            document.getElementById('reply-form-' + id).style.display = 'none';
        };
    });
    document.querySelectorAll('.btn-like-comment').forEach(function(btn) {
        btn.onclick = async function(e) {
            if (btn.tagName === 'A') return; // link login
            e.preventDefault();
            const liked = btn.getAttribute('data-liked') === '1';
            const id = btn.getAttribute('data-id');
            btn.disabled = true;
            try {
                const res = await fetch(liked ? '/like/unlike' : '/like', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ type: 'comment', id })
                });
                const data = await res.json();
                if (data.success) {
                    btn.setAttribute('data-liked', liked ? '0' : '1');
                    btn.classList.toggle('liked', !liked);
                    const icon = btn.querySelector('i');
                    icon.className = liked ? 'fa-regular fa-heart text-secondary' : 'fa-solid fa-heart text-danger';
                    btn.querySelector('.like-count').textContent = data.count;
                } else if(data.redirect) {
                    window.location.href = data.redirect;
                }
            } catch (err) {
                alert('Có lỗi xảy ra!');
            }
            btn.disabled = false;
        };
    });
});
</script> 