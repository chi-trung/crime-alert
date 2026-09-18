@php($hideMenu = true)
@extends('layouts.app')

@section('content')
<div class="container d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="card shadow p-4" style="max-width: 420px; width: 100%; border-radius: 18px;">
        <div class="text-center mb-3">
            <div class="empty-state-icon" style="margin-bottom:1rem;"></div>
        </div>
        <h3 class="text-center mb-2 text-success">Xác thực Email của bạn</h3>
        <p class="text-center mb-4">
            Cảm ơn bạn đã đăng ký!<br>Vui lòng kiểm tra email và nhấn vào liên kết xác thực.<br>
            Nếu bạn chưa nhận được email, hãy nhấn nút bên dưới để gửi lại.
        </p>
        @if (session('status') == 'verification-link-sent')
            <div class="alert alert-success text-center">
                Đã gửi lại email xác thực!
            </div>
        @endif
        <form method="POST" action="{{ route('verification.send') }}" class="d-grid gap-2 mb-2">
            @csrf
            <button type="submit" class="btn btn-success btn-lg w-100">
                Gửi lại email xác thực
            </button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="d-grid gap-2">
            @csrf
            <button type="submit" class="btn btn-link text-secondary w-100">
                Đăng xuất
            </button>
        </form>
    </div>
</div>
@endsection
