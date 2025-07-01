@extends('layouts.app')

@section('content')
<style>
.support-chat-container {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.10);
    padding: 0;
    max-width: 600px;
    margin: 0 auto;
}
.support-chat-messages {
    background: #f6f7fa;
    border-radius: 12px;
    padding: 18px 16px 8px 16px;
    overflow-y: auto;
    max-height: 400px;
    margin-bottom: 1rem;
}
.support-chat-msg {
    display: flex;
    align-items: flex-end;
    margin-bottom: 12px;
}
.support-chat-msg.user {
    justify-content: flex-start;
}
.support-chat-msg.admin {
    justify-content: flex-end;
}
.support-chat-bubble {
    max-width: 75%;
    padding: 10px 16px;
    border-radius: 16px;
    font-size: 15.5px;
    line-height: 1.5;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    word-break: break-word;
}
.support-chat-msg.user .support-chat-bubble {
    background: #1976d2;
    color: #fff;
    border-bottom-left-radius: 4px;
    text-align: left;
}
.support-chat-msg.admin .support-chat-bubble {
    background: #ffe0b2;
    color: #e65100;
    border-bottom-right-radius: 4px;
    text-align: right;
}
.support-chat-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: bold;
}
.support-chat-msg.admin .support-chat-avatar {
    background: #ffe0b2;
    color: #e65100;
    margin-right: 4px;
    margin-left: 0;
}
.support-chat-msg.user .support-chat-avatar {
    background: #e3f0ff;
    color: #1976d2;
    margin-left: 4px;
    margin-right: 0;
}
@media (max-width: 700px) {
    .support-chat-container { max-width: 100%; }
}
</style>
<div class="container py-4">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-3 fs-4"></i>
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
                <i class="fas fa-times-circle me-3 fs-4"></i>
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
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-lock me-1"></i> Đóng</button>
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
        <div class="support-chat-messages" id="chat-messages">
            @foreach($messages as $msg)
                <div class="support-chat-msg {{ ($msg->user->isAdmin ?? false) ? 'admin' : 'user' }}">
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
        <form action="{{ route('support.sendMessage', $supportRequest) }}" method="POST" class="d-flex gap-2 mt-2">
            @csrf
            <textarea name="message" class="form-control" rows="2" required placeholder="Nhập tin nhắn..." id="chat-input"></textarea>
            <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane me-1"></i> Gửi</button>
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
<script>
    // Tự động focus vào textarea khi vào trang
    document.getElementById('chat-input')?.focus();
    // Tự động cuộn xuống cuối khi vào trang hoặc có tin nhắn mới
    function scrollToBottom() {
        var chatBox = document.getElementById('chat-messages');
        if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    }
    scrollToBottom();
</script>
@endsection 