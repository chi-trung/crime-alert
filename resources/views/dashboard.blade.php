@extends('layouts.app')

@section('content')
<div class="container px-4">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 mt-4">
        <div class="mb-3 mb-md-0">
            <h1 class="fw-bold mb-1">Dashboard</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Trang chủ</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center">
            <div class="me-3 d-none d-sm-block text-end">
                <div class="text-muted small">Cập nhật lần cuối</div>
                <div class="fw-semibold text-primary">{{ now()->format('d/m/Y H:i') }}</div>
            </div>
            <button class="btn btn-light rounded-circle p-2" id="refresh-btn">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Welcome Card -->
    <div class="card border-0 bg-gradient-primary mb-4 overflow-hidden">
        <div class="card-body p-4 position-relative">
            <div class="position-absolute top-0 end-0 opacity-25">
                <svg width="200" height="200" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M100 200C155.228 200 200 155.228 200 100C200 44.7715 155.228 0 100 0C44.7715 0 0 44.7715 0 100C0 155.228 44.7715 200 100 200Z" fill="white"/>
                </svg>
            </div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="mb-3 mb-md-0">
                    <h5 class="fw-bold text-white mb-2">Chào mừng trở lại, {{ auth()->user()->name }}!</h5>
                    <p class="text-white-50 mb-0">Hệ thống đã sẵn sàng để bạn quản lý các cảnh báo tội phạm</p>
                    <a href="{{ auth()->user()->isAdmin ? '#' : route('alerts.create') }}" 
                       class="btn btn-light btn-sm mt-3">
                       {{ auth()->user()->isAdmin ? 'Xem báo cáo' : 'Tạo cảnh báo mới' }}
                    </a>
                    <a href="{{ route('alerts.map') }}" class="btn btn-outline-light btn-lg mt-3">
                        <i class="fas fa-map-marked-alt me-1"></i> Xem bản đồ tội phạm
                    </a>
                </div>
                <div class="d-none d-md-block">
                    <img src="https://cdn-icons-png.flaticon.com/512/4400/4400621.png" alt="Welcome" style="height: 120px;" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    @if(auth()->user()->isAdmin)
        <!-- Admin Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Tổng cảnh báo</span>
                                <h3 class="mb-0 mt-1">{{ $totalAlerts }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary bg-opacity-10 text-primary rounded fs-4">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> {{ $totalAlertsPercent }}%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Chờ duyệt</span>
                                <h3 class="mb-0 mt-1">{{ $pendingAlerts }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning bg-opacity-10 text-warning rounded fs-4">
                                    <i class="fas fa-clock"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-danger bg-opacity-10 text-danger">
                                <i class="fas fa-arrow-down me-1"></i> {{ $pendingPercent }}%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Đã duyệt</span>
                                <h3 class="mb-0 mt-1">{{ $approvedAlerts }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success bg-opacity-10 text-success rounded fs-4">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> {{ $approvedPercent }}%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Từ chối</span>
                                <h3 class="mb-0 mt-1">{{ $rejectedAlerts }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger bg-opacity-10 text-danger rounded fs-4">
                                    <i class="fas fa-times-circle"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> {{ $rejectedPercent }}%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Tổng user</span>
                                <h3 class="mb-0 mt-1">{{ $totalUsers }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info bg-opacity-10 text-info rounded fs-4">
                                    <i class="fas fa-users"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> {{ $totalUsersPercent }}%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Tỷ lệ duyệt</span>
                                <h3 class="mb-0 mt-1">{{ $totalAlerts > 0 ? round(($approvedAlerts / $totalAlerts) * 100) : 0 }}%</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-secondary bg-opacity-10 text-secondary rounded fs-4">
                                    <i class="fas fa-percentage"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> 3%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Thống kê cảnh báo theo tháng</h5>
                            <div class="dropdown">
                                <button class="btn btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#">Xuất báo cáo</a></li>
                                    <li><a class="dropdown-item" href="#">Xem chi tiết</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <canvas id="alertsChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0">
                        <h5 class="card-title mb-0">Phân loại cảnh báo</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="flex-grow-1">
                            <canvas id="alertsPieChart" height="250"></canvas>
                        </div>
                        <div class="mt-3">
                            <div class="row text-center">
                                <div class="col-4">
                                    <span class="d-block fw-bold">35%</span>
                                    <span class="text-muted small">Trộm cắp</span>
                                </div>
                                <div class="col-4">
                                    <span class="d-block fw-bold">25%</span>
                                    <span class="text-muted small">Lừa đảo</span>
                                </div>
                                <div class="col-4">
                                    <span class="d-block fw-bold">20%</span>
                                    <span class="text-muted small">Bạo lực</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables Row -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0">
                        <h5 class="card-title mb-0">Cảnh báo mới nhất</h5>
                        <a href="#" class="btn btn-sm btn-outline-primary rounded-pill">
                            Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-borderless table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Tiêu đề</th>
                                        <th>Loại</th>
                                        <th>Trạng thái</th>
                                        <th class="pe-4 text-end">Ngày đăng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($latestAlerts as $alert)
                                    <tr class="border-bottom">
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-2">
                                                    <img src="{{ $alert->image ? asset('storage/' . $alert->image) : 'https://ui-avatars.com/api/?name='.urlencode($alert->title).'&background=random' }}" 
                                                         alt="Ảnh cảnh báo" 
                                                         class="rounded-circle" 
                                                         width="36" 
                                                         height="36">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0">{{ Str::limit($alert->title, 20) }}</h6>
                                                    <small class="text-muted">{{ $alert->user->name ?? 'N/A' }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary">{{ $alert->type ?? 'Không rõ' }}</span>
                                        </td>
                                        <td>
                                            @if($alert->status == 'approved')
                                                <span class="badge bg-success bg-opacity-10 text-success">Đã duyệt</span>
                                            @elseif($alert->status == 'pending')
                                                <span class="badge bg-warning bg-opacity-10 text-warning">Chờ duyệt</span>
                                            @else
                                                <span class="badge bg-danger bg-opacity-10 text-danger">Từ chối</span>
                                            @endif
                                        </td>
                                        <td class="pe-4 text-end">
                                            <small class="text-muted">{{ $alert->created_at->format('d/m/Y') }}</small>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="py-4">
                                                <img src="https://cdn-icons-png.flaticon.com/512/4076/4076478.png" alt="No data" width="80" class="mb-3 opacity-50">
                                                <p class="text-muted">Chưa có cảnh báo nào</p>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0">
                        <h5 class="card-title mb-0">Cảnh báo chờ duyệt</h5>
                        <a href="#" class="btn btn-sm btn-outline-warning rounded-pill">
                            Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-borderless table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Tiêu đề</th>
                                        <th>Loại</th>
                                        <th>Người đăng</th>
                                        <th class="pe-4 text-end">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($latestPending as $alert)
                                    <tr class="border-bottom">
                                        <td class="ps-4">
                                            <h6 class="mb-0">{{ Str::limit($alert->title, 25) }}</h6>
                                            <small class="text-muted">{{ $alert->created_at->diffForHumans() }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning bg-opacity-10 text-warning">{{ $alert->type ?? 'Không rõ' }}</span>
                                        </td>
                                        <td>{{ $alert->user->name ?? 'N/A' }}</td>
                                        <td class="pe-4 text-end">
                                            <div class="btn-group btn-group-sm">
                                                <form action="{{ route('admin.alerts.approve', $alert) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-success btn-sm rounded" title="Duyệt">
                                                        <i class="fas fa-check"></i> Duyệt
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.alerts.reject', $alert) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-danger btn-sm rounded mx-1" title="Từ chối">
                                                        <i class="fas fa-times"></i> Từ chối
                                                    </button>
                                                </form>
                                                <a href="{{ route('alerts.show', $alert) }}" class="btn btn-info btn-sm rounded" title="Xem chi tiết">
                                                    <i class="fas fa-eye"></i> Xem
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="py-4">
                                                <img src="https://cdn-icons-png.flaticon.com/512/190/190411.png" alt="No pending" width="80" class="mb-3 opacity-50">
                                                <p class="text-muted">Không có cảnh báo chờ duyệt</p>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- User Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Cảnh báo đã đăng</span>
                                <h3 class="mb-0 mt-1">{{ $myTotal }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary bg-opacity-10 text-primary rounded fs-4">
                                    <i class="fas fa-bullhorn"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> 5%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Đã được duyệt</span>
                                <h3 class="mb-0 mt-1">{{ $myApproved }}</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success bg-opacity-10 text-success rounded fs-4">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> 12%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card card-stat bg-white border-0 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-muted small">Tỷ lệ duyệt</span>
                                <h3 class="mb-0 mt-1">{{ $myTotal > 0 ? round(($myApproved / $myTotal) * 100) : 0 }}%</h3>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info bg-opacity-10 text-info rounded fs-4">
                                    <i class="fas fa-percentage"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <i class="fas fa-arrow-up me-1"></i> 8%
                            </span>
                            <span class="text-muted small ms-1">so tháng trước</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Latest Alert Section -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0">
                        <h5 class="card-title mb-0">Cảnh báo mới nhất của bạn</h5>
                        <a href="{{ route('alerts.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>Tạo cảnh báo mới
                        </a>
                    </div>
                    <div class="card-body">
                        @if($myLatest)
                            <div class="row g-4">
                                <div class="col-lg-8">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <img src="{{ $myLatest->image ? asset('storage/' . $myLatest->image) : 'https://via.placeholder.com/100' }}" 
                                                 alt="Ảnh cảnh báo" 
                                                 class="rounded" 
                                                 width="80" 
                                                 height="80">
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="mb-1">{{ $myLatest->title }}</h5>
                                            <div class="d-flex align-items-center">
                                                <span class="badge 
                                                    @if($myLatest->status == 'approved') bg-success
                                                    @elseif($myLatest->status == 'pending') bg-warning
                                                    @else bg-danger
                                                    @endif
                                                    me-2">
                                                    @if($myLatest->status == 'approved') Đã duyệt
                                                    @elseif($myLatest->status == 'pending') Chờ duyệt
                                                    @else Từ chối
                                                    @endif
                                                </span>
                                                <small class="text-muted"><i class="fas fa-calendar-alt me-1"></i>{{ $myLatest->created_at->format('d/m/Y H:i') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-muted lh-lg">{{ $myLatest->description }}</p>
                                    <div class="d-flex">
                                        <button class="btn btn-outline-primary btn-sm me-2">
                                            <i class="fas fa-edit me-1"></i> Chỉnh sửa
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash me-1"></i> Xóa
                                        </button>
                                    </div>
                                </div>
                                @if($myLatest->image)
                                    <div class="col-lg-4">
                                        <div class="position-relative">
                                            <img src="{{ asset('storage/' . $myLatest->image) }}" 
                                                 alt="Ảnh cảnh báo" 
                                                 class="img-fluid rounded shadow-sm"
                                                 style="max-height: 200px; width: 100%; object-fit: cover;">
                                            <button class="btn btn-primary btn-sm position-absolute top-0 end-0 m-2">
                                                <i class="fas fa-expand"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="text-center py-5">
                                <div class="mb-4">
                                    <img src="https://cdn-icons-png.flaticon.com/512/4076/4076478.png" alt="No alerts" width="120" class="opacity-50">
                                </div>
                                <h5 class="text-muted mb-3">Bạn chưa đăng cảnh báo nào</h5>
                                <p class="text-muted mb-4">Hãy bắt đầu bằng cách tạo cảnh báo đầu tiên của bạn</p>
                                <a href="{{ route('alerts.create') }}" class="btn btn-primary px-4">
                                    <i class="fas fa-plus me-2"></i>Tạo cảnh báo đầu tiên
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@if(auth()->user()->isAdmin)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Refresh button animation
        const refreshBtn = document.getElementById('refresh-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                this.classList.add('refreshing');
                setTimeout(() => {
                    this.classList.remove('refreshing');
                    location.reload();
                }, 1000);
            });
        }

        // Truyền dữ liệu PHP sang JS
        const createdData = @json($createdData);
        const approvedData = @json($approvedData);

        // Alert Statistics Chart
        const ctx = document.getElementById('alertsChart').getContext('2d');
        const alertsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'],
                datasets: [
                    {
                        label: 'Cảnh báo đã tạo',
                        data: typeof createdData !== 'undefined' ? createdData : [],
                        backgroundColor: 'rgba(13, 110, 253, 0.05)',
                        borderColor: '#0d6efd',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#0d6efd',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'Cảnh báo đã duyệt',
                        data: typeof approvedData !== 'undefined' ? approvedData : [],
                        backgroundColor: 'rgba(25, 135, 84, 0.05)',
                        borderColor: '#198754',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#198754',
                        pointBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: '#fff',
                        titleColor: '#000',
                        bodyColor: '#000',
                        borderColor: 'rgba(0,0,0,0.1)',
                        borderWidth: 1,
                        padding: 12,
                        boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        }
                    }
                }
            }
        });

        // Alert Types Pie Chart
        const pieCtx = document.getElementById('alertsPieChart').getContext('2d');
        const alertsPieChart = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Trộm cắp', 'Lừa đảo', 'Bạo lực', 'Khác'],
                datasets: [{
                    data: [35, 25, 20, 20],
                    backgroundColor: [
                        '#0d6efd',
                        '#fd7e14',
                        '#dc3545',
                        '#6c757d'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                },
                cutout: '70%'
            }
        });
    });
</script>
@endif

<style>
:root {
    --card-radius: 16px;
    --card-shadow: 0 2px 16px rgba(0,0,0,0.08);
    --card-hover-shadow: 0 8px 32px rgba(0,0,0,0.15);
    --primary-gradient: linear-gradient(135deg, #0d6efd 0%, #5b9df9 100%);
    --success-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    --danger-gradient: linear-gradient(135deg, #ff5f6d 0%, #ffc371 100%);
    --warning-gradient: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
    --info-gradient: linear-gradient(135deg, #56ccf2 0%, #2f80ed 100%);
    --secondary-gradient: linear-gradient(135deg, #868f96 0%, #596164 100%);
}

body {
    background-color: #f8f9fa;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.card {
    border: none;
    border-radius: var(--card-radius);
    box-shadow: var(--card-shadow);
    transition: all 0.3s ease;
    margin-bottom: 0;
    overflow: hidden;
}

.card-stat {
    border-radius: var(--card-radius);
    box-shadow: var(--card-shadow);
    transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
    background: linear-gradient(135deg, #f8fafc 60%, #e9ecef 100%);
    position: relative;
    overflow: hidden;
}

.card-stat:hover {
    transform: translateY(-6px) scale(1.03);
    box-shadow: var(--card-hover-shadow);
    background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
}

.card-header {
    background-color: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1.25rem 1.5rem;
}

.bg-gradient-primary {
    background: var(--primary-gradient);
}

.avatar-sm {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-title {
    box-shadow: 0 2px 8px rgba(0,0,0,0.10);
    border: 2px solid #fff;
    background: linear-gradient(135deg, #fff 60%, #f0f0f0 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    font-size: 1.5rem;
    border-radius: 50%;
    margin-right: 0.5rem;
    transition: box-shadow 0.2s, background 0.2s;
}

.bg-danger, .bg-danger.bg-opacity-10 {
    background: var(--danger-gradient) !important;
    color: #fff !important;
}

.bg-success, .bg-success.bg-opacity-10 {
    background: var(--success-gradient) !important;
    color: #fff !important;
}

.bg-warning, .bg-warning.bg-opacity-10 {
    background: var(--warning-gradient) !important;
    color: #fff !important;
}

.bg-info, .bg-info.bg-opacity-10 {
    background: var(--info-gradient) !important;
    color: #fff !important;
}

.bg-primary, .bg-primary.bg-opacity-10 {
    background: var(--primary-gradient) !important;
    color: #fff !important;
}

.bg-secondary, .bg-secondary.bg-opacity-10 {
    background: var(--secondary-gradient) !important;
    color: #fff !important;
}

.avatar-title.bg-danger {
    box-shadow: 0 0 12px 2px rgba(255,95,109,0.25);
}

.avatar-title.bg-success {
    box-shadow: 0 0 12px 2px rgba(67,233,123,0.25);
}

.avatar-title.bg-warning {
    box-shadow: 0 0 12px 2px rgba(247,151,30,0.25);
}

.avatar-title.bg-info {
    box-shadow: 0 0 12px 2px rgba(86,204,242,0.25);
}

.avatar-title.bg-primary {
    box-shadow: 0 0 12px 2px rgba(13,110,253,0.25);
}

.avatar-title.bg-secondary {
    box-shadow: 0 0 12px 2px rgba(134,143,150,0.25);
}

.card-stat .badge {
    font-size: 0.85em;
    font-weight: 600;
    border-radius: 8px;
    padding: 0.4em 0.8em;
    box-shadow: 0 1px 4px rgba(0,0,0,0.07);
}

.table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
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
    font-size: 0.75em;
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

/* Animation for refresh button */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

#refresh-btn.refreshing {
    animation: spin 0.6s linear infinite;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-header {
        padding: 1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .display-5 {
        font-size: 2rem;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
</style>
@endsection