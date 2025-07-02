@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/profile_edit.css') }}">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Thông tin cá nhân</h4>
                </div>
                
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="info-item">
                                <h5 class="text-muted">Tên</h5>
                                <p class="h5">{{ auth()->user()->name }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <h5 class="text-muted">Email</h5>
                                <p class="h5">{{ auth()->user()->email }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="info-item">
                                <h5 class="text-muted">Xác thực email</h5>
                                <p class="h6 mb-0">
                                    @if(auth()->user()->email_verified_at)
                                        Đã xác thực lúc {{ auth()->user()->email_verified_at->format('d/m/Y H:i') }}
                                    @else
                                        <span class="text-danger">Chưa xác thực</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-item mb-4">
                        <h5 class="text-muted">Loại tài khoản</h5>
                        <span class="badge bg-{{ auth()->user()->isAdmin ? 'danger' : 'success' }}">
                            {{ auth()->user()->isAdmin ? 'Quản trị viên' : 'Người dùng' }}
                        </span>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h4 class="mb-4 text-primary">Đổi mật khẩu</h4>
                    
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    <form action="{{ route('profile.changePassword') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mật khẩu hiện tại</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            @error('current_password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Mật khẩu mới</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            @error('new_password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-4">
                            <label for="new_password_confirmation" class="form-label">Xác nhận mật khẩu mới</label>
                            <input type="password" class="form-control" id="new_password_confirmation" name="new_password_confirmation" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-key-fill me-2"></i> Đổi mật khẩu
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection