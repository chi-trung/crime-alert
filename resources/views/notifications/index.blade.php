@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/notifications_index.css') }}">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Tất cả thông báo</h2>
        @if($notifications->whereNull('read_at')->count() > 0)
        <form action="{{ route('notifications.readAll') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-check-double me-1"></i> Đánh dấu tất cả đã đọc</button>
        </form>
        @endif
    </div>
    <div class="row g-3">
        @forelse($notifications as $notification)
            <div class="col-12">
                <div class="card shadow-sm notification-card mb-2 @if(is_null($notification->read_at)) notification-unread-card @endif">
                    <a href="{{ route('notifications.read', $notification->id) }}" class="d-flex align-items-center gap-3 text-decoration-none text-dark p-3">
                        <div class="pt-1">
                            <i class="fas fa-comment-dots text-success fa-2x"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="mb-1">
                                @if(isset($notification->data['comment_user'], $notification->data['post_type'], $notification->data['post_title']))
                                    <b>{{ $notification->data['comment_user'] }}</b> đã bình luận vào <span class="text-primary">{{ $notification->data['post_type'] == 'alert' ? 'cảnh báo' : 'kinh nghiệm' }}</span>:
                                    <b>{{ $notification->data['post_title'] }}</b>
                                @else
                                    {{ $notification->data['message'] ?? 'Bạn có thông báo mới' }}
                                @endif
                            </div>
                            @if(isset($notification->data['comment_content']))
                                <div class="small text-muted mb-1">"{{ $notification->data['comment_content'] }}"</div>
                            @endif
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <i class="far fa-clock text-secondary small"></i>
                                <span class="small text-secondary">{{ $notification->created_at->diffForHumans() }}</span>
                                @if(is_null($notification->read_at))
                                    <span class="badge bg-warning text-dark ms-2">Chưa đọc</span>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info text-center">Không có thông báo nào.</div>
            </div>
        @endforelse
    </div>
    <div class="mt-4 d-flex justify-content-center">
        {{ $notifications->links('pagination::bootstrap-4') }}
    </div>
</div>

@endsection 