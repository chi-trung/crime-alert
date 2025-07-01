@if(isset($latestSupportRequest))
<div class="card border-0 shadow-sm mb-4 mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0">
        <h5 class="card-title mb-0"><i class="fas fa-life-ring me-2 text-warning"></i>Yêu cầu hỗ trợ gần đây</h5>
        <a href="{{ route('support.index') }}" class="btn btn-outline-warning btn-sm rounded-pill px-3">Xem tất cả</a>
    </div>
    <div class="card-body">
        <div class="d-flex align-items-center gap-3">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center mb-1 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0 me-2">{{ $latestSupportRequest->subject }}</h6>
                    <span class="badge {{ $latestSupportRequest->status == 'open' ? 'bg-warning text-dark' : 'bg-secondary' }} px-3 py-2 rounded-pill d-flex align-items-center" style="font-size: 0.95em;">
                        @if($latestSupportRequest->status == 'open')
                            <i class="fas fa-hourglass-half me-1"></i> Đang mở
                        @else
                            Đã đóng
                        @endif
                    </span>
                </div>
                <div class="text-muted small mb-2"><i class="far fa-calendar-alt me-1"></i> {{ $latestSupportRequest->created_at->format('d/m/Y H:i') }}</div>
                <a href="{{ route('support.show', $latestSupportRequest) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fas fa-eye me-1"></i> Xem chi tiết</a>
            </div>
        </div>
    </div>
</div>
@else
<div class="alert alert-info mt-4">Bạn chưa gửi yêu cầu hỗ trợ nào.</div>
@endif 