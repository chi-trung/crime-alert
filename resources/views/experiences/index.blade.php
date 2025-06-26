@extends('layouts.app')
@section('content')
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
        <a href="{{ route('experiences.create') }}" class="btn btn-success btn-lg px-4">
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
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <img src="{{ $item->avatar ? asset('storage/'.$item->avatar) : 'https://randomuser.me/api/portraits/men/32.jpg' }}" class="rounded-circle me-3" width="48" height="48" alt="avatar">
                        <div>
                            <strong>{{ $item->name }}</strong>
                            <div class="text-muted small">{{ $item->created_at->format('d/m/Y') }}</div>
                        </div>
                    </div>
                    <h5 class="card-title text-success">{{ $item->title }}</h5>
                    <p class="card-text">{{ Str::limit($item->content, 120) }}</p>
                    <a href="{{ route('experiences.show', $item) }}" class="btn btn-outline-success btn-sm mt-2">Xem chi tiết</a>
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