@extends('layouts.app')
@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-4">
                        <img src="{{ $experience->avatar ? asset('storage/'.$experience->avatar) : 'https://randomuser.me/api/portraits/men/32.jpg' }}" class="rounded-circle me-3" width="56" height="56" alt="avatar">
                        <div>
                            <strong>{{ $experience->name }}</strong>
                            <div class="text-muted small">{{ $experience->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                    <h2 class="mb-3 text-success">{{ $experience->title }}</h2>
                    <div class="mb-4" style="white-space:pre-line;">{{ $experience->content }}</div>
                    <a href="{{ route('experiences.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Quay lại danh sách</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 