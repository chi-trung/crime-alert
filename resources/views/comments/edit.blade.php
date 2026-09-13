@extends('layouts.app')
@section('content')
<div class="container mt-5">
    <h4>Sửa bình luận</h4>
    <form action="{{ route('comments.update', $comment) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <textarea name="content" class="form-control" rows="3" required>{{ old('content', $comment->content) }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary">Cập nhật</button>
        {{-- Issue #143: hardcoded alerts.show 500s (UrlGenerationException)
             on experience comments, whose alert_id is NULL. Branch on the
             owning post exactly like CommentController::update()'s redirect. --}}
        <a href="{{ $comment->experience_id ? route('experiences.show', $comment->experience_id) : route('alerts.show', $comment->alert_id) }}" class="btn btn-secondary">Quay lại</a>
    </form>
</div>
@endsection 