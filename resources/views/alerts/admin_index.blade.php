@extends('layouts.app')

@section('content')
<div class="container px-5 py-4">
    <!-- Header với breadcrumb và nút tạo mới -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-2 text-gray-800">Quản lý cảnh báo tội phạm</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-primary"><i class="fas fa-home me-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item active text-gray-600" aria-current="page">Cảnh báo</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('alerts.create') }}" class="btn btn-danger px-4 py-2 shadow-sm">
                <i class="fas fa-plus-circle me-2"></i>Tạo cảnh báo mới
            </a>
        </div>
    </div>

    <!-- Thông báo thành công -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-lg shadow-sm mb-4 border-0" role="alert">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle fa-lg me-3"></i>
                </div>
                <div class="flex-grow-1 me-3">
                    <h6 class="alert-heading mb-1">Thành công!</h6>
                    <div>{{ session('success') }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif

    <!-- Card chứa bảng dữ liệu -->
    <div class="card shadow-sm border-0 rounded-lg overflow-hidden">
        <div class="card-header bg-white border-0 py-4 px-5">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0 fw-semibold text-gray-700">Danh sách cảnh báo</h5>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end">
                        <form method="GET" class="w-100" style="max-width: 400px;">
                            <div class="input-group border rounded-pill shadow-sm">
                                <span class="input-group-text bg-white border-0 ps-4">
                                    <i class="fas fa-search text-gray-500"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-0 py-2" 
                                       placeholder="Tìm kiếm cảnh báo..." value="{{ request('search') }}">
                                <button class="btn btn-primary rounded-pill px-4" type="submit">
                                    Tìm
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-5 text-gray-700 fw-semibold" style="width: 80px;">ID</th>
                            <th class="text-gray-700 fw-semibold">Tiêu đề</th>
                            <th class="text-gray-700 fw-semibold" style="width: 200px;">Người đăng</th>
                            <th class="text-gray-700 fw-semibold" style="width: 150px;">Loại tội phạm</th>
                            <th class="text-center text-gray-700 fw-semibold" style="width: 150px;">Trạng thái</th>
                            <th class="text-gray-700 fw-semibold" style="width: 150px;">Ngày đăng</th>
                            <th class="text-center text-gray-700 fw-semibold" style="width: 250px;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($alerts as $alert)
                            <tr class="border-top">
                                <td class="ps-5 fw-semibold text-gray-600">#{{ $alert->id }}</td>
                                <td>
                                    <a href="{{ route('alerts.show', $alert) }}" 
                                       class="text-decoration-none text-dark hover-text-primary fw-medium">
                                        {{ Str::limit($alert->title, 40) }}
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3">
                                            <div class="avatar-title bg-light rounded-circle text-primary fw-bold">
                                                {{ substr($alert->user->name ?? 'N/A', 0, 1) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-gray-800">{{ $alert->user->name ?? 'N/A' }}</div>
                                            <small class="text-muted">{{ $alert->user->email ?? '' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2">
                                        <i class="fas fa-exclamation-triangle me-2"></i>{{ $alert->type ?? 'Không rõ' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($alert->status == 'pending')
                                        <span class="badge bg-warning text-dark px-3 py-2">
                                            <i class="fas fa-clock me-2"></i> Chờ duyệt
                                        </span>
                                    @elseif($alert->status == 'approved')
                                        <span class="badge bg-success text-white px-3 py-2">
                                            <i class="fas fa-check-circle me-2"></i> Đã duyệt
                                        </span>
                                    @else
                                        <span class="badge bg-danger text-white px-3 py-2">
                                            <i class="fas fa-times-circle me-2"></i> Từ chối
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold text-gray-800">{{ $alert->created_at->format('d/m/Y') }}</span>
                                        <small class="text-muted">{{ $alert->created_at->format('H:i') }}</small>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        @if($alert->status == 'pending')
                                            <form action="{{ route('admin.alerts.approve', $alert) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success px-3 shadow-sm"
                                                        onclick="return confirm('Duyệt cảnh báo này?')">
                                                    <i class="fas fa-check me-2"></i> Duyệt
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.alerts.reject', $alert) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-warning px-3 shadow-sm"
                                                        onclick="return confirm('Từ chối cảnh báo này?')">
                                                    <i class="fas fa-times me-2"></i> Từ chối
                                                </button>
                                            </form>
                                        @endif
                                        @if(auth()->user()->isAdmin)
                                            <a href="{{ route('admin.alerts.edit', $alert) }}"
                                               class="btn btn-sm btn-primary px-3 shadow-sm">
                                                <i class="fas fa-edit me-2"></i> Sửa
                                            </a>
                                        @endif
                                        <form action="{{ route('admin.alerts.destroy', $alert) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger px-3 shadow-sm"
                                                    onclick="return confirm('Xóa cảnh báo này?')">
                                                <i class="fas fa-trash-alt me-2"></i> Xóa
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Hiển thị khi không có dữ liệu -->
            @if($alerts->count() === 0)
                <div class="text-center py-5">
                    <a href="{{ route('alerts.create') }}" class="btn btn-danger px-4 py-2 shadow-sm">
                        <i class="fas fa-plus-circle me-2"></i>Tạo cảnh báo mới
                    </a>
                </div>
            @endif
        </div>
        <div class="card-footer bg-white border-0 py-4 px-5">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Hiển thị <span class="fw-semibold text-gray-800">{{ $alerts->firstItem() }}</span> đến 
                    <span class="fw-semibold text-gray-800">{{ $alerts->lastItem() }}</span> trong 
                    <span class="fw-semibold text-gray-800">{{ $alerts->total() }}</span> kết quả
                </div>
                <div class="d-flex align-items-center">
                    {{ $alerts->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    body {
        background-color: #f8f9fa;
    }
    
    .hover-text-primary:hover {
        color: #0d6efd !important;
    }
    
    .avatar-sm {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .avatar-title {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.03);
    }
    
    .card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .table {
        margin-bottom: 0;
    }
    
    .table th {
        border-top: none;
        border-bottom: 2px solid #f0f0f0;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        color: #6c757d;
        padding: 1rem 1.5rem;
    }
    
    .table td {
        vertical-align: middle;
        padding: 1.25rem 1.5rem;
        border-top: 1px solid #f0f0f0;
    }
    
    .badge {
        padding: 0.5em 1em;
        font-weight: 500;
        border-radius: 8px;
    }
    
    .breadcrumb {
        font-size: 0.9rem;
    }
    
    .form-control, .input-group-text {
        border: none;
        background-color: transparent;
    }
    
    .form-control:focus {
        box-shadow: none;
    }
    
    .rounded-lg {
        border-radius: 12px !important;
    }
    
    .shadow-sm {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
    }
</style>
@endsection