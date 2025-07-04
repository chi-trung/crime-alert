@extends('layouts.app')
@section('content')
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-4 text-success"><i class="fas fa-edit me-2"></i>Chỉnh sửa bài chia sẻ</h1>
    <form action="{{ route('experiences.update', $experience) }}" method="POST" class="mx-auto" style="max-width:600px;">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label fw-bold">Tên người gửi</label>
            <input type="text" class="form-control" value="{{ $experience->name }}" disabled>
            <input type="hidden" name="name" value="{{ $experience->name }}">
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Tiêu đề <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $experience->title) }}" required>
            @error('title')<div class="in   valid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Nội dung chia sẻ <span class="text-danger">*</span></label>
            <textarea name="content" rows="6" class="form-control @error('content') is-invalid @enderror" required>{{ old('content', $experience->content) }}</textarea>
            @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="d-flex justify-content-between">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Quay lại</a>
            <button type="submit" class="btn btn-success">Cập nhật</button>
        </div>
    </form>
</div>
@endsection 