@extends('layouts.app')
@section('content')
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-4 text-success"><i class="fas fa-edit me-2"></i>Chỉnh sửa bài chia sẻ</h1>
    <form action="{{ route('experiences.update', $experience) }}" method="POST" class="mx-auto" style="max-width:600px;">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label fw-bold">Tên người gửi</label>
            {{-- Issue #224: same silent-rejection hole as create — the hidden
                 field below re-posts the stored name, so a rejection (any
                 future bound change) must be visible under the field the user
                 sees, not only in the add form's guest branch. --}}
            <input type="text" class="form-control @error('name') is-invalid @enderror" value="{{ $experience->name }}" disabled>
            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <input type="hidden" name="name" value="{{ $experience->name }}">
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Tiêu đề <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $experience->title) }}" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
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