@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4 gap-2">
        <h2 class="mb-0"><i class="fas fa-life-ring text-warning me-2"></i>Tổng hợp yêu cầu hỗ trợ</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle shadow-sm rounded">
            <thead class="table-light">
                <tr>
                    <th>Tiêu đề</th>
                    <th>Người gửi</th>
                    <th>Trạng thái</th>
                    <th>Thời gian</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                    <tr>
                        <td class="fw-semibold">{{ $req->subject }}</td>
                        <td><i class="fas fa-user me-1 text-secondary"></i> {{ $req->user->name ?? 'N/A' }}</td>
                        <td>
                            @if($req->status == 'open')
                                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="fas fa-hourglass-half me-1"></i>Đang mở</span>
                            @else
                                <span class="badge bg-secondary px-3 py-2 rounded-pill">Đã đóng</span>
                            @endif
                        </td>
                        <td><i class="far fa-calendar-alt me-1 text-muted"></i> {{ $req->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="{{ route('support.show', $req) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fas fa-eye me-1"></i> Xem chi tiết</a>
                            @if($req->status == 'open')
                                <form action="{{ route('admin.support.close', $req) }}" method="POST" class="d-inline ms-1">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm rounded-pill px-3" title="Đóng yêu cầu"><i class="fas fa-lock me-1"></i> Đóng</button>
                                </form>
                            @endif
                            <form action="{{ route('admin.support.destroy', $req) }}" method="POST" class="d-inline form-delete ms-1">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm rounded-pill px-3" title="Xóa"><i class="fas fa-trash me-1"></i> Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="card border-0 shadow-sm text-center py-4 my-4 bg-light">
                                <div class="card-body">
                                    <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                    <div class="fw-bold mb-2">Chưa có yêu cầu hỗ trợ nào.</div>
                                    <div class="text-muted">Hệ thống sẽ hiển thị các yêu cầu hỗ trợ của người dùng tại đây.</div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection 