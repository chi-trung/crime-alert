@extends('layouts.app')

@section('title', "Trao đổi với hỗ trợ - Crime Alert Web")
@section('content')
<link rel="stylesheet" href="{{ asset('css/support_show.css') }}">
<script src="{{ asset('js/support_show.js') }}"></script>
<div class="container py-4">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                
                <div class="flex-grow-1">
                    <h5 class="alert-heading mb-1">Thành công!</h5>
                    <div class="mb-0">{{ session('success') }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                
                <div class="flex-grow-1">
                    <h5 class="alert-heading mb-1">Lỗi!</h5>
                    <div class="mb-0">{{ session('error') }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif
    <div class="support-chat-container p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Trao đổi với hỗ trợ</h2>
            @if(auth()->user()->isAdmin && $supportRequest->status == 'open')
                <form action="{{ route('admin.support.close', $supportRequest) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm">Đóng</button>
                </form>
            @endif
        </div>
        <div class="mb-2">
            <strong>Tiêu đề:</strong> {{ $supportRequest->subject }}<br>
            <strong>Trạng thái:</strong>
            @if($supportRequest->status == 'open')
                <span class="badge bg-warning text-dark">Đang mở</span>
            @else
                <span class="badge bg-secondary">Đã đóng</span>
            @endif
        </div>
        {{-- Issue #402: the poll appends a new bubble every 3s but the region
             was not a live region, so a screen reader user never learned an
             admin had replied without scrolling to check. role="log" is the
             semantics for an ordered, self-scrolling history; the appended
             rows below and in the JS builder carry role="listitem" so the
             region has structure, not just text. --}}
        <div class="support-chat-messages" id="chat-messages" role="log" aria-live="polite" aria-label="Hội thoại hỗ trợ">
            {{-- Issue #234: $messages is the latest-100 window, oldest-first.
                 Each bubble carries its row id (data-msg-id, escaped by {{ }}
                 so nothing raw reaches the DOM) — the poll script keys its
                 delta cursor off these ids. --}}
            @if($hasMoreOlder)
                <div class="text-center mb-2">
                    <button type="button" class="btn btn-sm btn-link" id="load-older"
                        data-oldest="{{ $oldestShown }}">Xem tin cũ hơn</button>
                </div>
            @endif
            @foreach($messages as $msg)
                <div class="support-chat-msg {{ ($msg->user->isAdmin ?? false) ? 'admin' : 'user' }}" data-msg-id="{{ $msg->id }}" role="listitem">
                    <div class="support-chat-bubble">
                        <div class="small fw-bold mb-1">
                            {{ $msg->user->name ?? 'Admin' }}
                            @if(isset($msg->user) && ($msg->user->isAdmin ?? false))
                                <span class="badge bg-warning text-dark ms-2" style="font-size:0.85em;vertical-align:middle;">Quản trị viên</span>
                            @endif
                        </div>
                        <div>{{ $msg->message }}</div>
                        <div class="small text-muted mt-1">{{ $msg->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        @if($supportRequest->status == 'open')
        {{-- Issue #235: sendMessage() validates message max:5000, and this
             form is also the no-JS POST target the chat script falls back to.
             A rejected over-length message used to land back here with no
             visible error (layouts/app.blade.php flashes only
             success/error/info, never $errors). Rendered in its own block
             BELOW the d-flex row — inside it the div would become a third
             flex column next to the Send button. maxlength matches the
             server cap exactly, as on the create form. --}}
        <form action="{{ route('support.sendMessage', $supportRequest) }}" method="POST">
            @csrf
            <div class="d-flex gap-2 mt-2">
                <textarea name="message" class="form-control @error('message') is-invalid @enderror" rows="2" maxlength="5000" required placeholder="Nhập tin nhắn..." id="chat-input" aria-label="Nội dung tin nhắn">{{ old('message') }}</textarea>
                <button type="submit" class="btn btn-success">Gửi</button>
            </div>
            @error('message')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </form>
        @else
        <div class="alert alert-info mt-2">Yêu cầu đã đóng, bạn không thể gửi thêm tin nhắn.</div>
        @endif
        <div class="d-flex justify-content-end">
            @if(auth()->user()->isAdmin)
                <a href="{{ route('admin.support.index') }}" class="btn btn-link mt-3">Quay lại danh sách</a>
            @else
                <a href="{{ route('support.index') }}" class="btn btn-link mt-3">Quay lại danh sách</a>
            @endif
        </div>
    </div>
</div>

{{-- Issue #149: @push('scripts') targets a @stack that layouts/app.blade.php
     never renders (it only has @yield('scripts') at line 755), so this
     entire live-chat block was silently dropped from the page. Same idiom
     as alerts/edit.blade.php:66. --}}
@endsection

@section('scripts')
<script>
// Issue #206: client-side rejection notice (the server's flash branches
// never reach this fetch path anymore). textContent only — no user content
// into innerHTML (#149 doctrine).
function showChatError(message) {
    const form = document.querySelector('form[action$="/message"]');
    if (!form) return;
    let box = document.getElementById('chat-error');
    if (!box) {
        box = document.createElement('div');
        box.id = 'chat-error';
        box.className = 'alert alert-danger mt-2';
        form.after(box);
    }
    box.textContent = message;
    box.hidden = false;
    clearTimeout(showChatError.timer);
    showChatError.timer = setTimeout(() => { box.hidden = true; }, 6000);
}
// Issue #234: the poll used to fetch the WHOLE thread every 3 seconds and
// discard the response when the COUNT was unchanged (if length matched, the
// bytes were still read, hydrated, serialized and transferred every time; if
// it differed, the entire chat was torn down and rebuilt). Now the client
// tracks the newest message id it has rendered (lastMessageId, seeded from
// the server-rendered data-msg-id bubbles) and polls only
// ?after_id=<lastMessageId> — an idle tab pays one indexed, bounded, empty
// delta read. Rendering is append-only: existing DOM nodes are never touched
// (no innerHTML='' rebuild, so the scroll position and any text the user is
// selecting survive new messages). The textContent-only builders are kept
// verbatim — stored-XSS doctrine from #149, mirroring the server markup.
let lastMessageId = {{ $messages->max('id') ?? 0 }};
function buildBubble(msg) {
    const wrapper = document.createElement('div');
    wrapper.className = 'support-chat-msg ' + (msg.is_admin ? 'admin' : 'user');
    wrapper.dataset.msgId = msg.id;
    // Issue #402: match the server-rendered bubbles so a row the poll builds
    // keeps the same list semantics as the ones above it.
    wrapper.setAttribute('role', 'listitem');

    const bubble = document.createElement('div');
    bubble.className = 'support-chat-bubble';

    const nameRow = document.createElement('div');
    nameRow.className = 'small fw-bold mb-1';
    nameRow.textContent = msg.user;
    if (msg.is_admin) {
        const badge = document.createElement('span');
        badge.className = 'badge bg-warning text-dark ms-2';
        badge.style.cssText = 'font-size:0.85em;vertical-align:middle;';
        badge.textContent = 'Quản trị viên';
        nameRow.appendChild(badge);
    }

    const body = document.createElement('div');
    body.textContent = msg.content;

    const time = document.createElement('div');
    time.className = 'small text-muted mt-1';
    time.textContent = msg.created_at;

    bubble.append(nameRow, body, time);
    wrapper.appendChild(bubble);
    return wrapper;
}
function fetchMessages() {
    const chatBox = document.getElementById('chat-messages');
    if (!chatBox) return;
    fetch(window.location.pathname + '/messages?after_id=' + lastMessageId, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.messages || data.messages.length === 0) {
            // Advance the cursor even on an empty delta so a re-render of the
            // page mid-thread doesn't re-request history we already hold.
            if (data.latest_id) lastMessageId = data.latest_id;
            return;
        }
        // Append-only: render each delta row, keep everything already shown.
        const nearBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 80;
        data.messages.forEach(msg => {
            chatBox.appendChild(buildBubble(msg));
        });
        if (data.latest_id > lastMessageId) lastMessageId = data.latest_id;
        // Only yank the view down when the user was already at the bottom —
        // reading history shouldn't be interrupted by a new message.
        if (nearBottom) chatBox.scrollTop = chatBox.scrollHeight;
    })
    .catch(() => { /* transient poll failure: next tick retries */ });
}
setInterval(fetchMessages, 3000);

// Issue #234: "load older" walks the thread backwards through the same
// bounded endpoint (?before_id=oldest), PREPENDING the window. After the
// first click the button's data-oldest advances to the new oldest_id so
// repeated clicks keep paging until has_more_older is false.
document.addEventListener('click', function(e) {
    const btn = e.target.closest('#load-older');
    if (!btn) return;
    const chatBox = document.getElementById('chat-messages');
    if (!chatBox) return;
    fetch(window.location.pathname + '/messages?before_id=' + btn.dataset.oldest, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        const prevHeight = chatBox.scrollHeight;
        (data.messages || []).slice().reverse().forEach(msg => {
            chatBox.insertBefore(buildBubble(msg), chatBox.firstChild);
        });
        if (data.has_more_older && data.oldest_id) {
            btn.dataset.oldest = data.oldest_id;
        } else {
            btn.hidden = true;
        }
        // Keep the message the user was looking at on screen after prepend.
        chatBox.scrollTop += chatBox.scrollHeight - prevHeight;
    })
    .catch(() => { });
});

document.addEventListener('DOMContentLoaded', function() {
    // URL thật là /support/{id}/message — selector cũ ("support/sendMessage") không bao giờ khớp
    const form = document.querySelector('form[action$="/message"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const textarea = form.querySelector('textarea[name="message"]');
            const message = textarea.value.trim();
            if (!message) return;
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message })
            })
            .then(res => {
                // Issue #206: the server now answers JSON rejections with a
                // real 403/404/409, so res.ok genuinely tracks acceptance.
                // On rejection the draft must survive (the old bug silently
                // ate it): surface the server's message and keep the text.
                if (res.ok) {
                    textarea.value = '';
                    fetchMessages();
                    return;
                }
                res.json().catch(() => null).then(data => {
                    showChatError((data && data.message) || 'Không gửi được tin nhắn, vui lòng thử lại.');
                });
            });
        });
    }
});
</script>
@endsection 