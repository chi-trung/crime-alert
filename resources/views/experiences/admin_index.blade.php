@extends('layouts.app')
@section('content')
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-4 text-primary"><i class="fas fa-cogs me-2"></i>Quản lý bài chia sẻ kinh nghiệm</h1>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="card shadow rounded-4 border-0">
        <div class="card-header bg-primary text-white rounded-top-4 d-flex align-items-center gap-2">
            <i class="fas fa-comments"></i>
            <h4 class="mb-0 fw-bold">Danh sách bài chia sẻ</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Tiêu đề</th>
                            <th>Người gửi</th>
                            <th>Ngày gửi</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($experiences as $i => $item)
                        <tr>
                            <td>{{ $experiences->firstItem() + $i }}</td>
                            <td class="fw-semibold">{{ $item->title }}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if($item->status == 'approved')
                                    <span class="badge bg-success">Đã duyệt</span>
                                @elseif($item->status == 'pending')
                                    <span class="badge bg-warning text-dark">Chờ duyệt</span>
                                @else
                                    <span class="badge bg-danger">Từ chối</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('experiences.show', $item) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fas fa-eye me-1"></i> Xem</a>
                                @if($item->status == 'pending')
                                    <form action="{{ route('admin.experiences.approve', $item) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm rounded-pill px-3"><i class="fas fa-check me-1"></i> Duyệt</button>
                                    </form>
                                    <form action="{{ route('admin.experiences.reject', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Từ chối bài này?');">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-sm rounded-pill px-3"><i class="fas fa-times me-1"></i> Từ chối</button>
                                    </form>
                                @endif
                                <form action="{{ route('admin.experiences.destroy', $item) }}" method="POST" class="d-inline form-delete">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm rounded-pill px-3"><i class="fas fa-trash me-1"></i> Xóa</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="d-flex justify-content-center mt-4 mb-3">
            {{ $experiences->links() }}
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