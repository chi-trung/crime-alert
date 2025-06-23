@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-4xl py-8">
    <h2 class="text-2xl font-bold mb-6">Quản lý cảnh báo tội phạm</h2>
    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-2 mb-4 rounded">{{ session('success') }}</div>
    @endif
    @if($reports->count())
        <div class="space-y-4">
            @foreach($reports as $report)
                <div class="border rounded p-4 bg-white shadow">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-lg font-semibold">{{ $report->title }}</h3>
                        <span class="text-xs text-gray-500">{{ $report->reported_at ? $report->reported_at->format('d/m/Y H:i') : '' }}</span>
                    </div>
                    <div class="mb-2 text-gray-700">{{ $report->description }}</div>
                    @if($report->image)
                        <div class="mb-2">
                            <img src="{{ asset('storage/'.$report->image) }}" alt="Hình ảnh" class="max-w-xs rounded">
                        </div>
                    @endif
                    <div class="text-sm text-gray-600 mb-2">
                        Địa điểm: {{ $report->location }}<br>
                        Vĩ độ: {{ $report->latitude }} | Kinh độ: {{ $report->longitude }}<br>
                        Trạng thái: <span class="font-semibold {{ $report->status == 'approved' ? 'text-green-600' : ($report->status == 'rejected' ? 'text-red-600' : 'text-yellow-600') }}">{{ $report->status }}</span>
                    </div>
                    <div class="flex gap-2">
                        @if($report->status == 'pending')
                            <form action="{{ route('admin.crime-reports.approve', $report->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded">Duyệt</button>
                            </form>
                            <form action="{{ route('admin.crime-reports.reject', $report->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="bg-yellow-600 text-white px-3 py-1 rounded">Từ chối</button>
                            </form>
                        @endif
                        <form action="{{ route('admin.crime-reports.destroy', $report->id) }}" method="POST" onsubmit="return confirm('Bạn chắc chắn muốn xóa?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded">Xóa</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $reports->links() }}</div>
    @else
        <div class="text-gray-600">Không có cảnh báo nào.</div>
    @endif
</div>
@endsection 