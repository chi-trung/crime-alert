@extends('layouts.app')
@section('content')
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-3 text-primary"><i class="fas fa-newspaper me-2"></i>Tin tức & Thông báo an ninh</h1>
    <p class="lead text-muted">Cập nhật tin tức mới nhất về tình hình an ninh, cảnh báo lừa đảo, truy nã đặc biệt...</p>
    @if($news->count() === 0)
        <div class="alert alert-info mt-4 text-center">Chức năng đang phát triển. Vui lòng quay lại sau!</div>
    @else
    <div class="row g-4 mt-2">
        @foreach($news as $item)
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="position-relative" style="height:180px;">
                    @if($item->is_video && !$item->image_url)
                        <div style="background:#222;height:100%;width:100%;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-play-circle fa-4x text-white" style="text-shadow:0 0 8px #000;"></i>
                        </div>
                    @else
                        <img src="{{ $item->image_url ?? asset('images/default-news.jpg') }}" class="card-img-top" alt="{{ $item->title }}" style="object-fit:cover;height:180px;width:100%;">
                        @if($item->is_video)
                            <span class="position-absolute top-50 start-50 translate-middle" style="pointer-events:none;">
                                <i class="fas fa-play-circle fa-3x text-white" style="text-shadow:0 0 8px #000;"></i>
                            </span>
                        @endif
                    @endif
                </div>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2" style="font-size:1.1rem;">{{ $item->title }}</h5>
                    <p class="card-text text-muted small flex-grow-1">{{ $item->description }}</p>
                    <a href="{{ $item->link }}" class="btn btn-outline-primary btn-sm mt-2" target="_blank">Đọc chi tiết <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <div class="d-flex justify-content-center mt-4">
        {{ $news->links() }}
    </div>
    @endif
</div>
@endsection 