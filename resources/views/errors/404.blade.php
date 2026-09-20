{{-- Issue #418: the project had no resources/views/errors/ at all, so every
     404 fell through to the framework's stock page — measured live: English
     copy ("Not Found"), <html lang="en"> on a Vietnamese app, and no app
     chrome at all, so the only way back was the browser's back button.

     Extends layouts.app so the nav, the footer and the dynamic lang tag all
     come along; the layout's own @auth branch already renders either the
     login link or the profile menu, so this file needs no auth handling of
     its own. --}}
@extends('layouts.app')

@section('title', "Không tìm thấy trang - Crime Alert Web")
@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-5">
                    <p class="display-3 fw-bold text-danger mb-2" aria-hidden="true">404</p>
                    <h1 class="h3 fw-semibold mb-3">Không tìm thấy trang</h1>
                    <p class="text-muted mb-4">
                        Trang bạn đang tìm không tồn tại, đã bị di chuyển hoặc bạn nhập sai địa chỉ.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                        <a href="{{ url("/") }}" class="btn btn-danger rounded-pill px-4">
                            Về trang chủ
                        </a>
                        <a href="{{ route('alerts.map') }}" class="btn btn-outline-secondary rounded-pill px-4">
                            Xem bản đồ an ninh
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
