@extends('layouts.app')
@section('content')
@vite(['resources/css/profile_my_history.css'])
<div class="container py-5">
    <h1 class="fw-bold mb-4 display-5 text-primary"><i class="fas fa-history me-2"></i>Lịch sử bài đăng của bạn</h1>
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow rounded-4 border-0 mb-4">
                <div class="card-header bg-primary text-white rounded-top-4 d-flex align-items-center gap-2">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4 class="mb-0 fw-bold">Cảnh báo đã đăng</h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tiêu đề</th>
                                    <th>Loại</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày đăng</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($myAlerts as $alert)
                                <tr>
                                    <td class="fw-semibold">{{ $alert->title }}</td>
                                    <td><span class="badge bg-primary bg-opacity-10 text-primary">{{ $alert->type }}</span></td>
                                    <td>
                                        @if($alert->status == 'approved')
                                            <span class="badge bg-success">Đã duyệt</span>
                                        @elseif($alert->status == 'pending')
                                            <span class="badge bg-warning text-dark">Chờ duyệt</span>
                                        @else
                                            <span class="badge bg-danger">Từ chối</span>
                                        @endif
                                    </td>
                                    <td>{{ $alert->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('alerts.show', $alert) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fas fa-eye me-1"></i> Xem</a>
                                        @if($alert->status != 'approved')
                                            <a href="{{ route('alerts.edit', $alert) }}" class="btn btn-outline-success btn-sm rounded-pill px-3"><i class="fas fa-edit me-1"></i> Sửa</a>
                                            <form action="{{ route('alerts.destroy', $alert) }}" method="POST" class="d-inline form-delete">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3"><i class="fas fa-trash me-1"></i> Xóa</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <img src="https://cdn-icons-png.flaticon.com/512/4076/4076478.png" alt="No alerts" width="60" class="mb-2 opacity-50">
                                        <div>Bạn chưa đăng cảnh báo nào</div>
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
            <div class="card shadow rounded-4 border-0 mb-4">
                <div class="card-header bg-success text-white rounded-top-4 d-flex align-items-center gap-2">
                    <i class="fas fa-comments"></i>
                    <h4 class="mb-0 fw-bold">Bài chia sẻ kinh nghiệm đã đăng</h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tiêu đề</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày gửi</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($myExperiences as $exp)
                                <tr>
                                    <td class="fw-semibold">{{ $exp->title }}</td>
                                    <td>
                                        @if($exp->status == 'approved')
                                            <span class="badge bg-success">Đã duyệt</span>
                                        @elseif($exp->status == 'pending')
                                            <span class="badge bg-warning text-dark">Chờ duyệt</span>
                                        @else
                                            <span class="badge bg-danger">Từ chối</span>
                                        @endif
                                    </td>
                                    <td>{{ $exp->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('experiences.show', $exp) }}" class="btn btn-outline-success btn-sm rounded-pill px-3"><i class="fas fa-eye me-1"></i> Xem</a>
                                        @if($exp->status != 'approved')
                                            <a href="{{ route('experiences.edit', $exp) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fas fa-edit me-1"></i> Sửa</a>
                                            <form action="{{ route('experiences.destroy', $exp) }}" method="POST" class="d-inline form-delete">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3"><i class="fas fa-trash me-1"></i> Xóa</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <img src="https://cdn-icons-png.flaticon.com/512/4076/4076478.png" alt="No exp" width="60" class="mb-2 opacity-50">
                                        <div>Bạn chưa có bài chia sẻ nào</div>
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
</div>
@endsection 