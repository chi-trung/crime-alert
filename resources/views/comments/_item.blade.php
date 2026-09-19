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
                {{-- Issue #410: the reply button toggles the form below, so it
                     owns an expanded region — aria-expanded tells a screen
                     reader the state without opening it. Never an id: this
                     partial is recursive, so an id would collide on nested
                     replies (same doctrine as the #384 textarea). --}}
                <button class="btn btn-link btn-sm text-decoration-none text-primary px-2 py-0 reply-btn" data-comment-id="{{ $comment->id }}" aria-expanded="false" aria-controls="reply-form-{{ $comment->id }}"><i class="fas fa-reply me-1"></i>Trả lời</button>
            @endauth
            @if(auth()->check() && (auth()->user()->isAdmin || auth()->id() === $comment->user_id))
                {{-- Issue #388: both actions were icon-only, so a screen reader
                     announced "link" and "button" with no purpose — the delete one
                     is irreversible. aria-label, never id: this partial is
                     recursive, so an id would collide on nested replies (same
                     doctrine as the #384 reply textarea). --}}
                <a href="{{ route('comments.edit', $comment) }}" class="btn btn-sm btn-outline-primary rounded-pill" aria-label="Sửa bình luận của {{ $comment->user->name ?? 'ẩn danh' }}"><i class="fas fa-edit" aria-hidden="true"></i></a>
                <form action="{{ route('comments.destroy', $comment) }}" method="POST" class="d-inline form-delete">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" aria-label="Xóa bình luận của {{ $comment->user->name ?? 'ẩn danh' }}"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
                </form>
            @endif
            @auth
                @php
                    // Likes were eager-loaded by the show pages (issue #25):
                    // read from the loaded collection instead of re-querying.
                    $isLiked = $comment->likes->contains('user_id', auth()->id());
                    $likeCount = $comment->likes->count();
                @endphp
                {{-- Issue #408: the button is an icon glyph plus a count, so its
                     accessible name was empty — a screen reader announced
                     "button" with no purpose. Same defect #388 closed for the
                     neighbouring edit/delete icons. The label tracks state
                     because the JS below swaps the icon on toggle; the count is
                     separate, so the name must not decay to "0" when it lands
                     on a comment with no likes yet. aria-label, never an id:
                     this partial is recursive, so an id would collide on
                     nested replies (same doctrine as the #384 textarea). --}}
                <button type="button" class="btn btn-like-comment px-2 py-0{{ $isLiked ? ' liked' : '' }}" data-id="{{ $comment->id }}" data-liked="{{ $isLiked ? '1' : '0' }}" aria-label="{{ $isLiked ? 'Bỏ thích bình luận' : 'Thích bình luận' }}" aria-pressed="{{ $isLiked ? 'true' : 'false' }}">
                    <i class="fa-heart {{ $isLiked ? 'fa-solid text-danger' : 'fa-regular text-secondary' }}" aria-hidden="true"></i>
                    <span class="like-count">{{ $likeCount }}</span>
                </button>
            @else
                {{-- Issue #408: title= is a tooltip hint, not an accessible
                     name (WCAG does not treat title as a name source), so this
                     guest link was named "link". --}}
                <a href="{{ route('login') }}" class="btn btn-like-comment px-2 py-0" title="Đăng nhập để thích" aria-label="Đăng nhập để thích">
                    <i class="fa-regular fa-heart text-secondary" aria-hidden="true"></i>
                    <span class="like-count">{{ $comment->likes->count() }}</span>
                </a>
            @endauth
        </div>
    </div>
    <div class="comment-content ps-4">{{ $comment->content }}</div>
    <!-- Form reply (ẩn/hiện bằng JS) -->
    {{-- Issue #410: role="group" + aria-label makes the form an announced
         landmark once it is visible; a polite live region beside it reports
         the open/close so a screen reader user learns the button did
         something. --}}
    <div class="reply-form-container mt-2" id="reply-form-{{ $comment->id }}" role="group" aria-label="Form trả lời bình luận" style="display:none;">
        <span class="reply-status sr-only" role="status" aria-live="polite"></span>
        <form action="{{ route('comments.store') }}" method="POST">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
            <input type="hidden" name="{{ $parentType }}_id" value="{{ $parentId }}">
            <div class="mb-2">
                <textarea name="content" class="form-control rounded-3" rows="2" placeholder="Viết trả lời..." required aria-label="Nội dung trả lời cho bình luận của {{ $comment->user?->name }}" id="reply-content-{{ $comment->id }}"></textarea>
            </div>
            <button type="submit" class="btn btn-success btn-sm rounded-pill px-3"><i class="fas fa-reply me-1"></i> Gửi trả lời</button>
            <button type="button" class="btn btn-link btn-sm text-secondary cancel-reply-btn" data-comment-id="{{ $comment->id }}">Hủy</button>
        </form>
    </div>
    <!-- Hiển thị replies lồng nhau: issue #28's fixed chain ended here at
         depth 2; #258 hands the partial a flat parent_id map of the whole
         thread, so recursion costs zero extra queries at any depth. The
         map's buckets are already oldest-first (#25/#89 tiebreak). -->
    @foreach(($children[$comment->id] ?? collect()) as $reply)
        @include('comments._item', ['comment' => $reply, 'children' => $children, 'parentType' => $parentType, 'parentId' => $parentId, 'level' => (isset($level) ? $level + 1 : 1)])
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
/* Issue #410: Bootstrap's .sr-only utility is not loaded by this app, so the
   live region above needs its own visually-hidden rule. Kept in the DOM and
   in the accessibility tree — display:none would silence the announcements. */
.reply-status.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Issue #410: opening the form is a state change the user has to be told
    // about, and a keyboard user has to land in the textarea without tabbing
    // through the whole comment above it. Closing returns focus to the reply
    // button, or it is stranded inside the region just hidden.
    function setReplyOpen(btn, open) {
        var id = btn.getAttribute('data-comment-id');
        var form = document.getElementById('reply-form-' + id);
        if (!form) return;
        form.style.display = open ? 'block' : 'none';
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        var status = form.querySelector('.reply-status');
        if (status) status.textContent = open ? 'Đã mở form trả lời' : 'Đã đóng form trả lời';
        if (open) {
            var textarea = document.getElementById('reply-content-' + id);
            if (textarea) textarea.focus();
        } else {
            btn.focus();
        }
    }
    document.querySelectorAll('.reply-btn').forEach(function(btn) {
        btn.onclick = function() {
            setReplyOpen(btn, true);
        };
    });
    document.querySelectorAll('.cancel-reply-btn').forEach(function(btn) {
        btn.onclick = function() {
            var id = btn.getAttribute('data-comment-id');
            var replyBtn = document.querySelector('.reply-btn[data-comment-id="' + id + '"]');
            if (replyBtn) setReplyOpen(replyBtn, false);
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
                    // Issue #408: keep the announced state in step with the
                    // icon swap above, or the label says "Thích" while the
                    // glyph already shows a filled heart.
                    btn.setAttribute('aria-pressed', liked ? 'false' : 'true');
                    btn.setAttribute('aria-label', liked ? 'Thích bình luận' : 'Bỏ thích bình luận');
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