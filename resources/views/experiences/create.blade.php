@extends('layouts.app')
@section('content')
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-4 text-success">Gửi bài chia sẻ kinh nghiệm</h1>
    <form action="{{ route('experiences.store') }}" method="POST" enctype="multipart/form-data" class="mx-auto" style="max-width:600px;">
        @csrf
        @if(Auth::user())
            <input type="hidden" name="name" value="{{ Auth::user()->name }}">
            <div class="mb-3">
                <label for="experience-name" class="form-label fw-bold">Tên người gửi</label>
                {{-- Issue #224: the hidden name above is what the server
                     validates. A rejection used to render nowhere for authed
                     users (the only @error('name') lived in the guest @else
                     branch), so an over-long name failed the submit silently.
                     Surface it under the disabled input the user is looking at. --}}
                <input type="text" id="experience-name" class="form-control @error('name') is-invalid @enderror" value="{{ Auth::user()->name }}" disabled aria-describedby="experience-name-error">
                @error('name')<div id="experience-name-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        @else
            {{-- Issue #224: this branch is unreachable today — the route is
                 behind `auth` — but it is the branch that would actually POST
                 a user-entered name, and it is where the name-cap error of
                 the same issue renders if the route ever opens to guests.
                 Kept labeled so that opening it is not an a11y regression. --}}
            <div class="mb-3">
                <label for="experience-name-guest" class="form-label fw-bold">Tên người gửi <span class="text-danger">*</span></label>
                <input type="text" id="experience-name-guest" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required aria-describedby="experience-name-guest-error">
                @error('name')<div id="experience-name-guest-error" class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endif
        <div class="mb-3">
            <label for="experience-title" class="form-label fw-bold">Tiêu đề <span class="text-danger">*</span></label>
            <input type="text" id="experience-title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required aria-describedby="experience-title-error">
            @error('title')<div id="experience-title-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="experience-content" class="form-label fw-bold">Nội dung chia sẻ <span class="text-danger">*</span></label>
            <textarea id="experience-content" name="content" rows="6" class="form-control @error('content') is-invalid @enderror" required aria-describedby="experience-content-error">{{ old('content') }}</textarea>
            @error('content')<div id="experience-content-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-success btn-lg">Gửi bài chia sẻ</button>
        </div>
    </form>
</div>
@endsection 