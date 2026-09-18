@extends('layouts.app')
@section('content')
<div class="container py-5">
    {{-- Issue #343: this was the only unstyled form in the app — a bare <h4>
         and a raw textarea, while experiences/edit and alerts/edit both use a
         card with an icon heading, a labelled field, is-invalid error
         display and matching buttons. Brought in line with them; the logic
         is unchanged (same action, same fields, same #143 back-link). --}}
    <div class="card shadow-sm border-0 rounded-4 mx-auto" style="max-width: 720px;">
        <div class="card-body p-4 p-md-5">
            <h1 class="h4 fw-bold mb-4 text-primary">
                Sửa bình luận
            </h1>
            <form action="{{ route('comments.update', $comment) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="content" class="form-label fw-bold">Nội dung <span class="text-danger">*</span></label>
                    <textarea id="content" name="content" class="form-control @error('content') is-invalid @enderror" rows="4" required>{{ old('content', $comment->content) }}</textarea>
                    @error('content')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="d-flex justify-content-between">
                    {{-- Issue #143: hardcoded alerts.show 500s (UrlGenerationException)
                         on experience comments, whose alert_id is NULL. Branch on the
                         owning post exactly like CommentController::update()'s
                         redirect. --}}
                    <a href="{{ $comment->experience_id ? route('experiences.show', $comment->experience_id) : route('alerts.show', $comment->alert_id) }}" class="btn btn-outline-secondary">
                        Quay lại
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
