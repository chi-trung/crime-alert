@extends('layouts.app')
@section('content')
@vite(['resources/css/experiences_index.css'])
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-3 text-success"><i class="fas fa-comments me-2"></i>Chia sẻ kinh nghiệm & Cảnh giác</h1>
    <p class="lead text-muted mb-4">Nơi mọi người chia sẻ trải nghiệm thực tế, cảnh báo, kinh nghiệm phòng chống tội phạm, lừa đảo, góp phần xây dựng cộng đồng an toàn hơn.</p>
    <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
        <i class="fas fa-lightbulb fa-2x me-3"></i>
        <div>
            <strong>Hãy chia sẻ trải nghiệm của bạn!</strong> Những câu chuyện thực tế, bài học cảnh giác của bạn có thể giúp người khác tránh rủi ro và lan tỏa sự an toàn.
        </div>
    </div>
    @auth
    <div class="d-flex justify-content-end mb-4">
        <a href="{{ route('experiences.create') }}" class="btn btn-success btn-lg rounded-pill px-4">
            <i class="fas fa-plus-circle me-2"></i> Gửi bài chia sẻ
        </a>
    </div>
    @endauth
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="row g-4">
        @forelse($experiences as $item)
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow rounded-4 border-0">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center mb-3">
                        <img src="{{ $item->user && $item->user->avatar ? asset('storage/'.$item->user->avatar) : 'https://ui-avatars.com/api/?name='.urlencode($item->user->name ?? 'U').'&background=0D8ABC&color=fff' }}" class="rounded-circle border me-3" width="48" height="48" alt="avatar">
                        <div>
                            <strong>{{ $item->name }}</strong>
                            <div class="text-muted small">{{ $item->created_at->format('d/m/Y') }}</div>
                        </div>
                    </div>
                    <h5 class="card-title text-success fw-bold">{{ $item->title }}</h5>
                    <p class="card-text flex-grow-1">{{ Str::limit($item->content, 120) }}</p>
                    <div class="d-flex align-items-center gap-2 mt-auto">
                        <a href="{{ route('experiences.show', $item) }}" class="btn btn-outline-success btn-sm rounded-pill px-3"><i class="fas fa-eye me-1"></i> Xem chi tiết</a>
                        @if($item->status == 'pending')
                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill d-flex align-items-center" style="font-size: 0.95em;">
                                <i class="fas fa-hourglass-half me-1"></i> Chờ duyệt
                            </span>
                        @elseif($item->status == 'rejected')
                            <span class="badge bg-danger px-3 py-2 rounded-pill d-flex align-items-center" style="font-size: 0.95em;">
                                Từ chối
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="alert alert-info text-center">Chưa có bài chia sẻ nào. Hãy là người đầu tiên đóng góp!</div>
        </div>
        @endforelse
    </div>
    <div class="d-flex justify-content-center mt-4">
        {{ $experiences->links() }}
    </div>
</div>
@endsection 