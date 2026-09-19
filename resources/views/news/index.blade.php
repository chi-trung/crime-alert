@extends('layouts.app')
@section('content')
<style>
    {{-- Issue #376: /news loads no page stylesheet (unlike alerts/index, which
         pulls css/alerts_index.css), so the placeholder style lives here.
         The old img-default-news class matched no rule in any CSS file. --}}
    .news-thumb-ph {
        height: 180px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: repeating-linear-gradient(
            45deg, #eef2f7, #eef2f7 6px, #e3e9f2 6px, #e3e9f2 12px);
    }
</style>
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
                    @if($item->image_url)
                        {{-- Issue #376: an <img> whose src resolves to the page
                             itself is a broken-image glyph, not a placeholder,
                             so the empty-src branch never ships an <img>. --}}
                        <img src="{{ $item->image_url }}"
                             class="card-img-top"
                             alt="{{ $item->title }}"
                             style="object-fit:cover;height:180px;width:100%;background:#f8f9fa;">
                    @else
                        <div class="news-thumb-ph" role="img" aria-label="{{ $item->title }}"></div>
                    @endif
                    @if($item->is_video)
                        {{-- Issue #376: the badge wrapper shipped empty — no
                             glyph, no text, no label. --}}
                        <span class="position-absolute top-50 start-50 translate-middle" style="pointer-events:none;">
                            <svg viewBox="0 0 24 24" width="34" height="34" aria-hidden="true"
                                 style="filter:drop-shadow(0 1px 3px rgba(0,0,0,.6));">
                                <circle cx="12" cy="12" r="11" fill="rgba(0,0,0,.55)"></circle>
                                <path d="M10 8l6 4-6 4z" fill="#fff"></path>
                            </svg>
                        </span>
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