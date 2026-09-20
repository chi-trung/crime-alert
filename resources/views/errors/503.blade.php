{{-- Issue #418: maintenance mode (php artisan down) renders this. The stock
     page is English with no app chrome. --}}
@extends('layouts.app')

@section('title', "Đang bảo trì - Crime Alert Web")
@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-5">
                    <p class="display-3 fw-bold text-warning mb-2" aria-hidden="true">503</p>
                    <h1 class="h3 fw-semibold mb-3">Đang bảo trì</h1>
                    <p class="text-muted mb-4">
                        Hệ thống đang bảo trì để cải thiện dịch vụ. Vui lòng quay lại sau ít phút.
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
