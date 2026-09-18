@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/alerts_admin_index.css') }}">
{{-- Issue #210: this page used to ship TWO extra form-reject confirm
     handlers (public/js/alerts_admin_index.js and an inline block), both
     byte-identical to each other and near-identical to the layout's.
     sweetalert2 is a singleton — only the LAST-registered handler's dialog
     (the layout's, generic wording) ever rendered, so the page-specific
     wording was dead code shipped twice. Both copies are deleted; the
     layout's single handler reads the alert wording from the data
     attributes on each reject form below. --}}
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-4 text-danger">Quản lý cảnh báo tội phạm</h1>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="card shadow rounded-4 border-0">
        <div class="card-header bg-danger text-white rounded-top-4 d-flex align-items-center gap-2">
            
            <h4 class="mb-0 fw-bold">Danh sách cảnh báo</h4>
            <a href="{{ route('alerts.create') }}" class="btn btn-light btn-sm rounded-pill ms-auto px-3">Tạo mới</a>
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
                                            {{-- Issue #131: substr() cut at byte 1, so a name
                                 starting with a 2-byte Vietnamese letter (Đặng, Đào,
                                 Đình...) yielded a lone 0xC4 lead byte — mojibake in
                                 the avatar. Same mb idiom as navigation's profile
                                 avatar (line 161), which also uppercases. --}}
                                            {{ mb_strtoupper(mb_substr($alert->user->name ?? 'N/A', 0, 1, 'UTF-8'), 'UTF-8') }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $alert->user->name ?? 'N/A' }}</div>
                                        <small class="text-muted">{{ $alert->user->email ?? '' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-danger bg-opacity-10 text-danger">{{ $alert->type ?? 'Không rõ' }}</span>
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
                                        Xem
                                    </a>
                                    @if($alert->status == 'pending')
                                        <form action="{{ route('admin.alerts.approve', $alert) }}" method="POST" class="d-inline mb-1">
                                            @csrf
                                            <button class="btn btn-success btn-sm rounded-pill px-3" title="Duyệt">Duyệt</button>
                                        </form>
                                        <form action="{{ route('admin.alerts.reject', $alert) }}" method="POST" class="d-inline mb-1 form-reject"
              data-reject-title="Bạn có chắc chắn muốn từ chối cảnh báo này?"
              data-reject-text="Hành động này sẽ từ chối cảnh báo và không thể hoàn tác!">
                                            @csrf
                                            <button class="btn btn-warning btn-sm rounded-pill px-3" title="Từ chối">Từ chối</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('admin.alerts.destroy', $alert) }}" method="POST" class="d-inline form-delete">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm rounded-pill px-3" title="Xóa">Xóa</button>
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
                    <a href="{{ route('alerts.create') }}" class="btn btn-danger rounded-pill px-4 py-2">Tạo cảnh báo mới</a>
                </div>
            @endif
        </div>
        {{-- Issue #227: on an empty result set firstItem()/lastItem() return
             null, so this footer rendered "Hiển thị  đến  trong  0 kết quả"
             with two blank spans while links() emitted nothing. The summary
             row only describes page content that exists — the @if(count()===0)
             CTA block above already carries the empty state.
             Issue #317: the guard said total(), but firstItem()/lastItem()
             answer for the CURRENT page — an int page past the last one kept
             total()>0 while the slice was empty and re-rendered the exact
             blank-span sentence #227 killed. BoundedPaginator clamps the page
             upstream now, so total()>0 and count()>0 agree again; guarding on
             count() keeps the sentence honest even if a future call site
             forgets the clamp. --}}
        @if($alerts->count() > 0)
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
        @endif
    </div>
</div>
@endsection