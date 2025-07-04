@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <h2>Chỉnh sửa cảnh báo</h2>
    <form action="{{ auth()->user()->isAdmin ? route('admin.alerts.update', $alert) : route('alerts.update', $alert) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label for="title" class="form-label">Tiêu đề</label>
            <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $alert->title) }}" required>
            @error('title')<div class="text-danger">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Mô tả</label>
            <textarea class="form-control" id="description" name="description" rows="4" required>{{ old('description', $alert->description) }}</textarea>
            @error('description')<div class="text-danger">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Chọn vị trí trên bản đồ</label>
            <div id="map" style="height: 350px; border-radius: 12px; overflow: hidden;"></div>
            <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $alert->latitude) }}">
            <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $alert->longitude) }}">
            <div class="form-text">Nhấn vào bản đồ để chọn vị trí xảy ra sự việc (có thể bỏ qua nếu không rõ).</div>
        </div>
        <div class="mb-3">
            <label for="type" class="form-label">Loại tội phạm</label>
            <select class="form-select" id="type" name="type" required>
                <option value="">-- Chọn loại tội phạm --</option>
                <option value="Cướp giật" {{ old('type', $alert->type) == 'Cướp giật' ? 'selected' : '' }}>Cướp giật</option>
                <option value="Trộm cắp" {{ old('type', $alert->type) == 'Trộm cắp' ? 'selected' : '' }}>Trộm cắp</option>
                <option value="Lừa đảo" {{ old('type', $alert->type) == 'Lừa đảo' ? 'selected' : '' }}>Lừa đảo</option>
                <option value="Bạo lực" {{ old('type', $alert->type) == 'Bạo lực' ? 'selected' : '' }}>Bạo lực</option>
                <option value="Khác" {{ old('type', $alert->type) == 'Khác' ? 'selected' : '' }}>Khác</option>
            </select>
            @error('type')<div class="text-danger">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="image" class="form-label">Ảnh minh họa (tùy chọn)</label>
            @if($alert->image)
                <input type="hidden" name="old_image" value="{{ $alert->image }}">
                <div class="mb-2 position-relative d-inline-block image-preview-block" id="image-preview-block">
                    <img src="/{{ $alert->image }}" alt="Ảnh hiện tại" class="preview-img" style="max-width: 350px; max-height: 350px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
                    <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2 rounded-circle remove-image-btn" style="z-index:10;">
                        <i class="fas fa-times"></i>
                    </button>
                    <input type="hidden" name="remove_image" class="remove_image_input" value="0">
                </div>
                <div class="mt-2" id="remove-image-message" style="display:none; color:#d9534f; font-weight:500;">
                    Ảnh sẽ bị xóa khi bạn cập nhật.
                </div>
            @endif
            <input type="file" class="form-control mt-2" id="image" name="image" accept="image/*">
            @error('image')<div class="text-danger">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary">Cập nhật</button>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Quay lại</a>
    </form>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
window.onload = function() {
    const lat = parseFloat(document.getElementById('latitude').value) || 10.762622;
    const lng = parseFloat(document.getElementById('longitude').value) || 106.660172;
    const map = L.map('map').setView([lat, lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);
    setTimeout(function () {
        map.invalidateSize();
    }, 0);
    let marker;
    if (!isNaN(lat) && !isNaN(lng) && (lat !== 10.762622 || lng !== 106.660172)) {
        marker = L.marker([lat, lng]).addTo(map);
    }
    map.on('click', function(e) {
        const { lat, lng } = e.latlng;
        document.getElementById('latitude').value = lat.toFixed(7);
        document.getElementById('longitude').value = lng.toFixed(7);
        if (marker) marker.setLatLng(e.latlng);
        else marker = L.marker(e.latlng).addTo(map);
    });

    document.querySelectorAll('.remove-image-btn').forEach(function(removeBtn) {
        removeBtn.onclick = function() {
            var previewBlock = this.closest('.image-preview-block');
            var removeInput = previewBlock.querySelector('.remove_image_input');
            var removeMsg = document.getElementById('remove-image-message');
            var fileInput = document.getElementById('image');
            if (previewBlock) previewBlock.style.display = 'none';
            if (removeInput) removeInput.value = '1';
            if (removeMsg) removeMsg.style.display = 'block';
            if (fileInput) fileInput.value = '';
        };
    });
};
</script>
@endsection 