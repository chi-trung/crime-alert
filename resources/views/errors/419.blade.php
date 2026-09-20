{{-- Issue #418: 419 is the CSRF-token-expiry page, and it is the error a user
     hits by leaving a form open too long (the alert create form is long) then
     submitting it. The stock page says "Page Expired" in English with no
     explanation, and the user's typed content is gone either way — the least
     this page can do is say what happened and that re-submitting after a
     reload usually works. --}}
@extends('layouts.app')

@section('title', "Phiên đã hết hạn - Crime Alert Web")
@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-5">
                    <p class="display-3 fw-bold text-warning mb-2" aria-hidden="true">419</p>
                    <h1 class="h3 fw-semibold mb-3">Phiên đã hết hạn</h1>
                    <p class="text-muted mb-4">
                        Form này đã mở quá lâu nên bảo mật phiên hết hạn. Tải lại trang và gửi lại là được — nội dung đã nhập có thể cần gõ lại.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                        <a href="javascript:history.back()" class="btn btn-danger rounded-pill px-4">
                            Quay lại form
                        </a>
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
