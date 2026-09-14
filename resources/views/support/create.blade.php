@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2 class="mb-4">Gửi yêu cầu hỗ trợ</h2>
    <form action="{{ route('support.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="subject" class="form-label">Tiêu đề yêu cầu</label>
            {{-- Issue #235: store() validates subject max:255 / message
                 max:5000, but nothing rendered $errors and the inputs had no
                 maxlength, so an over-limit submit 302'd back to a form with
                 the rejected text re-filled and zero trace of the rejection —
                 the user believed the request was sent while no row existed.
                 Same shape #224 fixed for experiences: server-verbatim caps
                 on maxlength (client bound == server bound, so the browser
                 truncates to the length that actually validates) plus an
                 inline Bootstrap error so a rejection (or a JS-suppressed
                 maxlength overflow) is visible. --}}
            <input type="text" name="subject" id="subject" class="form-control @error('subject') is-invalid @enderror" maxlength="255" required value="{{ old('subject') }}">
            @error('subject')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="message" class="form-label">Nội dung</label>
            <textarea name="message" id="message" class="form-control @error('message') is-invalid @enderror" rows="5" maxlength="5000" required>{{ old('message') }}</textarea>
            @error('message')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Gửi yêu cầu</button>
        <a href="{{ route('support.index') }}" class="btn btn-link">Quay lại danh sách</a>
    </form>
</div>
@endsection 