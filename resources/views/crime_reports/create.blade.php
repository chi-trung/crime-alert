@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-lg py-8">
    <h2 class="text-2xl font-bold mb-6">Gửi cảnh báo tội phạm</h2>
    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-2 mb-4 rounded">{{ session('success') }}</div>
    @endif
    <form action="{{ route('crime-reports.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
            <label class="block font-semibold">Tiêu đề</label>
            <input type="text" name="title" class="w-full border rounded p-2" required value="{{ old('title') }}">
            @error('title')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="block font-semibold">Mô tả</label>
            <textarea name="description" class="w-full border rounded p-2" rows="4" required>{{ old('description') }}</textarea>
            @error('description')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="block font-semibold">Địa điểm</label>
            <input type="text" name="location" class="w-full border rounded p-2" value="{{ old('location') }}">
            @error('location')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <div class="flex gap-2">
            <div class="w-1/2">
                <label class="block font-semibold">Vĩ độ (latitude)</label>
                <input type="text" name="latitude" class="w-full border rounded p-2" value="{{ old('latitude') }}">
                @error('latitude')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
            </div>
            <div class="w-1/2">
                <label class="block font-semibold">Kinh độ (longitude)</label>
                <input type="text" name="longitude" class="w-full border rounded p-2" value="{{ old('longitude') }}">
                @error('longitude')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
            </div>
        </div>
        <div>
            <label class="block font-semibold">Hình ảnh (nếu có)</label>
            <input type="file" name="image" class="w-full border rounded p-2">
            @error('image')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="block font-semibold">Thời gian xảy ra</label>
            <input type="datetime-local" name="reported_at" class="w-full border rounded p-2" value="{{ old('reported_at') }}">
            @error('reported_at')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Gửi cảnh báo</button>
    </form>
</div>
@endsection 