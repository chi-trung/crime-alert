@extends('layouts.app')

@section('content')
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-4 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Quản lý cảnh báo tội phạm</h1>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="card shadow rounded-4 border-0">
        <div class="card-header bg-danger text-white rounded-top-4 d-flex align-items-center gap-2">
            <i class="fas fa-bullhorn"></i>
            <h4 class="mb-0 fw-bold">Danh sách cảnh báo</h4>
            <a href="{{ route('alerts.create') }}" class="btn btn-light btn-sm rounded-pill ms-auto px-3"><i class="fas fa-plus-circle me-1"></i> Tạo mới</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tiêu đề</th>
                            <th>Người đăng</th>
                            <th>Loại tội phạm</th>
                            <th>Trạng thái</th>
                            <th>Ngày đăng</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($alerts as $alert)
                        <tr>
                            <td class="fw-semibold">#{{ $alert->id }}</td>
                            <td>
                                <a href="{{ route('alerts.show', $alert) }}" class="text-decoration-none text-dark fw-medium">{{ Str::limit($alert->title, 40) }}</a>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-2">
                                        <div class="avatar-title bg-light rounded-circle text-danger fw-bold">
                                            {{ substr($alert->user->name ?? 'N/A', 0, 1) }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $alert->user->name ?? 'N/A' }}</div>
                                        <small class="text-muted">{{ $alert->user->email ?? '' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-danger bg-opacity-10 text-danger"><i class="fas fa-exclamation-triangle me-1"></i>{{ $alert->type ?? 'Không rõ' }}</span>
                            </td>
                            <td>
                                @if($alert->status == 'pending')
                                    <span class="badge bg-warning text-dark">Chờ duyệt</span>
                                @elseif($alert->status == 'approved')
                                    <span class="badge bg-success">Đã duyệt</span>
                                @else
                                    <span class="badge bg-danger">Từ chối</span>
                                @endif
                            </td>
                            <td>
                                <span class="fw-semibold">{{ $alert->created_at->format('d/m/Y') }}</span>
                                <div class="text-muted small">{{ $alert->created_at->format('H:i') }}</div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    @if($alert->status == 'pending')
                                    <form action="{{ route('admin.alerts.approve', $alert) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm rounded-pill px-3"><i class="fas fa-check me-1"></i> Duyệt</button>
                                    </form>
                                    <form action="{{ route('admin.alerts.reject', $alert) }}" method="POST" class="d-inline" onsubmit="return confirm('Từ chối cảnh báo này?');">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-sm rounded-pill px-3"><i class="fas fa-times me-1"></i> Từ chối</button>
                                    </form>
                                    @endif
                                    <a href="{{ route('admin.alerts.edit', $alert) }}" class="btn btn-primary btn-sm rounded-pill px-3"><i class="fas fa-edit me-1"></i> Sửa</a>
                                    <form action="{{ route('admin.alerts.destroy', $alert) }}" method="POST" class="d-inline form-delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm rounded-pill px-3"><i class="fas fa-trash me-1"></i> Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($alerts->count() === 0)
                <div class="text-center py-5">
                    <a href="{{ route('alerts.create') }}" class="btn btn-danger rounded-pill px-4 py-2"><i class="fas fa-plus-circle me-2"></i>Tạo cảnh báo mới</a>
                </div>
            @endif
        </div>
        <div class="d-flex justify-content-between align-items-center card-footer bg-white border-0 py-4 px-5">
            <div class="text-muted">
                Hiển thị <span class="fw-semibold">{{ $alerts->firstItem() }}</span> đến 
                <span class="fw-semibold">{{ $alerts->lastItem() }}</span> trong 
                <span class="fw-semibold">{{ $alerts->total() }}</span> kết quả
            </div>
            <div>
                {{ $alerts->links() }}
            </div>
        </div>
    </div>
</div>
<style>
.card {
    border-radius: 1.25rem;
    box-shadow: 0 2px 16px rgba(0,0,0,0.08);
    margin-bottom: 0;
    overflow: hidden;
}
.card-header {
    font-size: 1.15rem;
    font-weight: 600;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 1.25rem 1.5rem;
}
.table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    color: #6c757d;
    border-top: none;
    white-space: nowrap;
}
.table > :not(:first-child) {
    border-top: none;
}
.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}
.badge {
    font-weight: 500;
    padding: 0.35em 0.65em;
    font-size: 0.85em;
    letter-spacing: 0.5px;
}
.btn {
    font-weight: 500;
    transition: all 0.2s;
}
.btn-sm {
    padding: 0.35rem 0.75rem;
    font-size: 0.825rem;
}
.text-muted {
    color: #6c757d !important;
}
</style>
@endsection