@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-3xl py-8">
    <h2 class="text-2xl font-bold mb-6">Danh sách cảnh báo tội phạm</h2>
    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-2 mb-4 rounded">{{ session('success') }}</div>
    @endif
    <div class="mb-4">
        <a href="{{ route('crime-reports.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded">Gửi cảnh báo mới</a>
    </div>
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
                    <div class="text-sm text-gray-600">
                        Địa điểm: {{ $report->location }}<br>
                        Vĩ độ: {{ $report->latitude }} | Kinh độ: {{ $report->longitude }}
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $reports->links() }}</div>
    @else
        <div class="text-gray-600">Chưa có cảnh báo nào được duyệt.</div>
    @endif
</div>
@endsection 