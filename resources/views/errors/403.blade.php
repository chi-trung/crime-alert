{{-- Issue #418: as 404.blade.php. The framework default showed "Forbidden"
     in English with no way back; AlertController/CommentController both
     abort(403) on a non-owner editing someone else's record. --}}
@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-5">
                    <p class="display-3 fw-bold text-warning mb-2" aria-hidden="true">403</p>
                    <h1 class="h3 fw-semibold mb-3">Không có quyền truy cập</h1>
                    <p class="text-muted mb-4">
                        Bạn không có quyền xem nội dung này, hoặc hành động này chỉ dành cho chủ sở hữu và quản trị viên.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-danger rounded-pill px-4">
                                Về bảng điều khiển
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-danger rounded-pill px-4">
                                Đăng nhập
                            </a>
                        @endauth
                        <a href="{{ url("/") }}" class="btn btn-outline-secondary rounded-pill px-4">
                            Về trang chủ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
