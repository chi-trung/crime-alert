@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Danh sách yêu cầu hỗ trợ của bạn</h2>
        <a href="{{ route('support.create') }}" class="btn btn-warning btn-lg rounded-pill shadow-sm">
            Gửi yêu cầu mới
        </a>
    </div>
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:180px">Tiêu đề</th>
                            <th style="min-width:120px">Trạng thái</th>
                            <th style="min-width:140px">Thời gian</th>
                            <th class="text-end" style="min-width:120px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $req->subject }}
                                </td>
                                <td>
                                    @if($req->status == 'open')
                                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Đang mở</span>
                                    @else
                                        <span class="badge bg-secondary px-3 py-2 rounded-pill">Đã đóng</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $req->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('support.show', $req) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                        Xem chi tiết
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">
                                <div class="empty-state-icon small mb-2"></div>
                                Bạn chưa gửi yêu cầu nào.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{-- Issue #75: its own pager; the guard keeps a one-page list from
                 growing the gutter inside the p-0 card body. --}}
            @if($requests->hasPages())
                <div class="px-3 py-2 d-flex justify-content-center">{{ $requests->links('pagination::bootstrap-4') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection 