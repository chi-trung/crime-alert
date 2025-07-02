@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="{{ asset('css/experiences_admin_index.css') }}">
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-4 text-success"><i class="fas fa-comments me-2"></i>Quản lý bài chia sẻ kinh nghiệm</h1>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="card shadow rounded-4 border-0 mb-4">
        <div class="card-header bg-success text-white rounded-top-4 d-flex align-items-center gap-2">
            <i class="fas fa-comments"></i>
            <h4 class="mb-0 fw-bold">Danh sách bài chia sẻ</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">#</th>
                            <th>Tiêu đề</th>
                            <th>Người gửi</th>
                            <th class="text-center">Ngày gửi</th>
                            <th class="text-center">Trạng thái</th>
                            <th class="text-center">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($experiences as $i => $exp)
                        <tr class="align-middle">
                            <td class="text-center text-muted">{{ ($experiences->currentPage() - 1) * $experiences->perPage() + $i + 1 }}</td>
                            <td class="fw-semibold">{{ $exp->title }}</td>
                            <td>{{ $exp->name }}</td>
                            <td class="text-center">{{ $exp->created_at->format('d/m/Y H:i') }}</td>
                            <td class="text-center">
                                @if($exp->status == 'approved')
                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Đã duyệt</span>
                                @elseif($exp->status == 'pending')
                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Chờ duyệt</span>
                                @else
                                    <span class="badge bg-danger px-3 py-2 rounded-pill">Từ chối</span>
                                @endif
                            </td>
                            <td class="text-center" style="min-width: 120px;">
                                <div class="d-flex flex-column align-items-center gap-1">
                                    <a href="{{ route('experiences.show', $exp) }}" class="btn btn-outline-info btn-sm rounded-pill px-3 mb-1">
                                        <i class="fas fa-eye me-1"></i> Xem
                                    </a>
                                    @if($exp->status == 'pending')
                                        <form action="{{ route('admin.experiences.approve', $exp) }}" method="POST" class="d-inline mb-1">
                                            @csrf
                                            <button class="btn btn-success btn-sm rounded-pill px-3" title="Duyệt"><i class="fas fa-check me-1"></i> Duyệt</button>
                                        </form>
                                        <form action="{{ route('admin.experiences.reject', $exp) }}" method="POST" class="d-inline mb-1 form-reject">
                                            @csrf
                                            <button class="btn btn-warning btn-sm rounded-pill px-3" title="Từ chối"><i class="fas fa-times me-1"></i> Từ chối</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('admin.experiences.destroy', $exp) }}" method="POST" class="d-inline form-delete">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm rounded-pill px-3" title="Xóa"><i class="fas fa-trash me-1"></i> Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $experiences->links() }}
            </div>
        </div>
    </div>
</div>
@endsection 