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
        {{-- Issue #349: this page used to ship a stripped-down inline map that
             only handled click-to-drop — no geocoder, no address field — so a
             user editing the position could move the point but never enter an
             address, leaving a stale location next to new coordinates. It also
             omitted fixLeafletIcons(), so its marker did not render at all.
             The map is now the shared alert_map_picker.js used by the create
             page. The location input is readonly: the address is derived from
             the chosen point (Nominatim), same as on create. --}}
        <div class="mb-3">
            <label class="form-label fw-bold">Chọn vị trí trên bản đồ</label>
            <div id="map" style="height: 350px; border-radius: 12px; overflow: hidden;"></div>
            <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $alert->latitude) }}">
            <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $alert->longitude) }}">
            <input type="text" id="location" name="location" class="form-control mt-2" placeholder="Địa chỉ sẽ tự động điền khi chọn vị trí" value="{{ old('location', $alert->location) }}" readonly>
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
                <div class="mb-2 position-relative d-inline-block image-preview-block" id="image-preview-block">
                    {{-- Issue #137: the old src="/{{ $alert->image }}" built
                         /alerts/x.jpg, but the file lives under the public
                         disk's storage/ URL (see the asset('storage/...')
                         siblings) — the preview never loaded, so the
                         #125 remove-image button was operated blind. --}}
                    <img src="{{ asset('storage/'.$alert->image) }}" alt="Ảnh hiện tại" class="preview-img" style="max-width: 350px; max-height: 350px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
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
            {{-- Issue #343: the edit form was the #211 fix's blind spot. The
                 create page advertises the enforced cap, the edit page did
                 not — same 2MB validator rule, same rejection, no notice, so
                 a user replacing an image here got a validation error with no
                 prior warning. Mirrors the create view's copy; AlertUploadCopyTest
                 pins the number to the rule so both pages stay in step. --}}
            <div class="form-text">Chỉ chấp nhận ảnh (JPEG, PNG, GIF) tối đa 2MB.</div>
            @error('image')<div class="text-danger">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary">Cập nhật</button>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Quay lại</a>
    </form>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H" crossorigin="anonymous"/>
{{-- Issue #349: the edit page needs the geocoder too — without it there is no
     way to enter an address here, only drag a marker. Hashes per #167/#327. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@4.0.0/dist/Control.Geocoder.css" integrity="sha384-dtZhMVplthx1XPTPFEKMM5M6e369Paz7gy0QTqvuQKB42lq4FIPsrqe125Ho6bfO" crossorigin="anonymous" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH" crossorigin="anonymous"></script>
<script src="https://unpkg.com/leaflet-control-geocoder@4.0.0/dist/Control.Geocoder.js" integrity="sha384-GwOxBPYQUJoAtZlP9zcDGxDFHdgRasiwmwj4JQoxhWpOBaETX1aOU/qm8fsP4Hf5" crossorigin="anonymous"></script>
<script src="{{ asset('js/alert_map_picker.js') }}"></script>
<script>
window.addEventListener('load', function () {
    fixLeafletIcons();
    editPositionableMap({ containerId: 'map', geocodeOnLoad: false });

    // #125's remove-image toggle. Kept here rather than in the shared picker:
    // the create page has no stored image to remove.
    document.querySelectorAll('.remove-image-btn').forEach(function (removeBtn) {
        removeBtn.onclick = function () {
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
});
</script>
@endsection 