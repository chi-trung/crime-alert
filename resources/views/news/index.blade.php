@extends('layouts.app')
@section('content')
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-3 text-primary">Tin tức & Thông báo an ninh</h1>
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
                            
                        </div>
                    @else
                        <img src="{{ $item->image_url ?? '' }}"
                             class="card-img-top{{ $item->image_url ? '' : ' img-default-news' }}"
                             alt="{{ $item->title }}"
                             style="object-fit:{{ $item->image_url ? 'cover' : 'contain' }};height:180px;width:100%;background:#f8f9fa;">
                        @if($item->is_video)
                            <span class="position-absolute top-50 start-50 translate-middle" style="pointer-events:none;">
                                
                            </span>
                        @endif
                    @endif
                </div>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2" style="font-size:1.1rem;">{{ $item->title }}</h5>
                    <p class="card-text text-muted small flex-grow-1">{{ $item->description }}</p>
                    <a href="{{ $item->link }}" class="btn btn-outline-primary btn-sm mt-2" target="_blank" rel="noopener">Đọc chi tiết </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <div class="d-flex justify-content-center mt-4">
        {{ $news->links('pagination::bootstrap-4') }}
    </div>
    <div class="text-end mt-3" style="font-size: 0.95rem;">
      <em>Nguồn: <a href="https://vnexpress.net/phap-luat" target="_blank" rel="noopener">VnExpress Pháp luật</a></em>
    </div>
    @endif
</div>
@endsection 