@extends('layouts.app')

@section('content')
@vite(['resources/css/support_show.css', 'resources/js/support_show.js'])
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

@endsection 