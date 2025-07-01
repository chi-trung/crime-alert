@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2 class="mb-4">Gửi yêu cầu hỗ trợ</h2>
    <form action="{{ route('support.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="subject" class="form-label">Tiêu đề yêu cầu</label>
            <input type="text" name="subject" id="subject" class="form-control" required value="{{ old('subject') }}">
        </div>
        <div class="mb-3">
            <label for="message" class="form-label">Nội dung</label>
            <textarea name="message" id="message" class="form-control" rows="5" required>{{ old('message') }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Gửi yêu cầu</button>
        <a href="{{ route('support.index') }}" class="btn btn-link">Quay lại danh sách</a>
    </form>
</div>
@endsection 