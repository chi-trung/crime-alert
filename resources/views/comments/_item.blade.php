<div class="comment-item mb-3 pb-3 border-bottom ms-{{ isset($level) ? $level * 4 : 0 }}">
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
                <form action="{{ route('comments.destroy', $comment) }}" method="POST" class="d-inline form-delete">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill"><i class="fas fa-trash-alt"></i></button>
                </form>
            @endif
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
});
</script> 