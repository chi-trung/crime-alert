@extends('layouts.app')

@section('content')
@vite(['resources/css/alerts_admin_index.css', 'resources/js/alerts_admin_index.js'])
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
                <table class="table table-striped table-bordered align-middle mb-0">
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
                            <td class="text-center" style="min-width: 120px;">
                                <div class="d-flex flex-column align-items-center gap-1">
                                    <a href="{{ route('alerts.show', $alert) }}" class="btn btn-outline-info btn-sm rounded-pill px-3 mb-1">
                                        <i class="fas fa-eye me-1"></i> Xem
                                    </a>
                                    @if($alert->status == 'pending')
                                        <form action="{{ route('admin.alerts.approve', $alert) }}" method="POST" class="d-inline mb-1">
                                            @csrf
                                            <button class="btn btn-success btn-sm rounded-pill px-3" title="Duyệt"><i class="fas fa-check me-1"></i> Duyệt</button>
                                        </form>
                                        <form action="{{ route('admin.alerts.reject', $alert) }}" method="POST" class="d-inline mb-1 form-reject">
                                            @csrf
                                            <button class="btn btn-warning btn-sm rounded-pill px-3" title="Từ chối"><i class="fas fa-times me-1"></i> Từ chối</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('admin.alerts.destroy', $alert) }}" method="POST" class="d-inline form-delete">
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form.form-reject').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Bạn có chắc chắn muốn từ chối cảnh báo này?',
                text: 'Hành động này sẽ từ chối cảnh báo và không thể hoàn tác!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Từ chối',
                cancelButtonText: 'Hủy',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>
@endsection