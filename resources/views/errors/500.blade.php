{{-- Issue #418: the stock 500 page leaks Laravel's stack trace in debug and
     shows a raw "Server Error" in English in production. Vietnamese, no
     technical detail beyond the status code, and a route back. --}}
@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-5">
                    <p class="display-3 fw-bold text-danger mb-2" aria-hidden="true">500</p>
                    <h1 class="h3 fw-semibold mb-3">Lỗi máy chủ</h1>
                    <p class="text-muted mb-4">
                        Hệ thống gặp sự cố khi xử lý yêu cầu. Vui lòng thử lại sau ít phút, nếu vẫn lỗi hãy liên hệ quản trị viên.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                        <a href="{{ url("/") }}" class="btn btn-danger rounded-pill px-4">
                            Về trang chủ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
