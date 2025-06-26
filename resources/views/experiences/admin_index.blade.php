@extends('layouts.app')
@section('content')
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-4 text-primary"><i class="fas fa-cogs me-2"></i>Quản lý bài chia sẻ kinh nghiệm</h1>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="table-responsive">
        <table class="table table-bordered align-middle bg-white">
            <thead class="table-primary">
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
                    <td>{{ $item->title }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        @if($item->status == 'approved')
                            <span class="badge bg-success">Đã duyệt</span>
                        @else
                            <span class="badge bg-warning text-dark">Chờ duyệt</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('experiences.show', $item) }}" class="btn btn-sm btn-outline-primary">Xem</a>
                        @if($item->status != 'approved')
                        <form action="{{ route('admin.experiences.approve', $item) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success">Duyệt</button>
                        </form>
                        @endif
                        <form action="{{ route('admin.experiences.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa bài này?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="d-flex justify-content-center mt-4">
            {{ $experiences->links() }}
        </div>
    </div>
</div>
@endsection 