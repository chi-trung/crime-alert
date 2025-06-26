@extends('layouts.app')
@section('content')
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-4 text-success"><i class="fas fa-plus-circle me-2"></i>Gửi bài chia sẻ kinh nghiệm</h1>
    <form action="{{ route('experiences.store') }}" method="POST" enctype="multipart/form-data" class="mx-auto" style="max-width:600px;">
        @csrf
        @if(Auth::user())
            <input type="hidden" name="name" value="{{ Auth::user()->name }}">
            <div class="mb-3">
                <label class="form-label fw-bold">Tên người gửi</label>
                <input type="text" class="form-control" value="{{ Auth::user()->name }}" disabled>
            </div>
        @else
            <div class="mb-3">
                <label class="form-label fw-bold">Tên người gửi <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endif
        <div class="mb-3">
            <label class="form-label fw-bold">Tiêu đề <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Nội dung chia sẻ <span class="text-danger">*</span></label>
            <textarea name="content" rows="6" class="form-control @error('content') is-invalid @enderror" required>{{ old('content') }}</textarea>
            @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Ảnh đại diện (tùy chọn)</label>
            <input type="file" name="avatar" class="form-control @error('avatar') is-invalid @enderror" accept="image/*">
            @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-success btn-lg">Gửi bài chia sẻ</button>
        </div>
    </form>
</div>
@endsection 