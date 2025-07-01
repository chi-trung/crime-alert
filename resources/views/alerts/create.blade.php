@vite(['resources/css/alerts_create.css', 'resources/js/alerts_create.js'])
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Card chính -->
            <div class="card border-0 shadow-lg rounded-3 overflow-hidden">
                <!-- Header với gradient -->
                <div class="card-header bg-white border-bottom py-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                        </div>
                        <div class="flex-grow-1 ms-4">
                            <h1 class="h3 mb-0 text-dark fw-bold">Đăng cảnh báo tội phạm</h1>
                            <p class="mb-0 text-secondary">Chia sẻ thông tin để cảnh báo cộng đồng</p>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-5">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-5">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle me-3 fs-4"></i>
                                <div class="flex-grow-1">
                                    <h5 class="alert-heading mb-1">Thành công!</h5>
                                    <div class="mb-0">Báo cáo đã được gửi và đang chờ duyệt. Cảm ơn bạn đã đóng góp cho cộng đồng!</div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        </div>
                    @endif
                    
                    <form action="{{ route('alerts.store') }}" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        @csrf
                        
                        <!-- Tiêu đề -->
                        <div class="mb-4">
                            <label for="title" class="form-label fw-bold fs-5 text-gray-700">
                                <i class="fas fa-heading me-2 text-danger"></i>Tiêu đề cảnh báo <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control form-control-lg border-2 py-3 px-4 @error('title') is-invalid @enderror" 
                                   id="title" name="title" value="{{ old('title') }}" 
                                   placeholder="Ví dụ: Cảnh giác trộm cắp tại quận 1" required>
                            <div class="form-text">Tiêu đề ngắn gọn, mô tả rõ sự việc</div>
                            @error('title')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <!-- Loại tội phạm -->
                        <div class="mb-4">
                            <label for="type" class="form-label fw-bold fs-5 text-gray-700">
                                <i class="fas fa-tag me-2 text-danger"></i>Loại tội phạm <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg border-2 py-3 px-4 @error('type') is-invalid @enderror" 
                                    id="type" name="type" required>
                                <option value="" disabled selected>-- Chọn loại tội phạm --</option>
                                <option value="Cướp giật" {{ old('type') == 'Cướp giật' ? 'selected' : '' }}>Cướp giật</option>
                                <option value="Trộm cắp" {{ old('type') == 'Trộm cắp' ? 'selected' : '' }}>Trộm cắp</option>
                                <option value="Lừa đảo" {{ old('type') == 'Lừa đảo' ? 'selected' : '' }}>Lừa đảo</option>
                                <option value="Bạo lực" {{ old('type') == 'Bạo lực' ? 'selected' : '' }}>Bạo lực</option>
                                <option value="Khác" {{ old('type') == 'Khác' ? 'selected' : '' }}>Khác</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <!-- Bản đồ chọn vị trí -->
                        <div class="mb-4">
                            <label class="form-label fw-bold fs-5 text-gray-700">
                                <i class="fas fa-map-marked-alt me-2 text-danger"></i>Chọn vị trí trên bản đồ
                            </label>
                            <div id="map" style="height: 350px; border-radius: 12px; overflow: hidden;"></div>
                            <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude') }}">
                            <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude') }}">
                            <input type="text" id="location" name="location" class="form-control mt-2" placeholder="Địa chỉ sẽ tự động điền khi chọn vị trí" value="{{ old('location') }}" readonly>
                            <div class="form-text">Nhấn vào bản đồ để chọn vị trí xảy ra sự việc (có thể bỏ qua nếu không rõ).</div>
                        </div>
                        
                        <!-- Mô tả -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-bold fs-5 text-gray-700">
                                <i class="fas fa-align-left me-2 text-danger"></i>Nội dung cảnh báo <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control border-2 py-3 px-4 @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="6" 
                                      placeholder="Mô tả chi tiết về sự việc, đặc điểm nghi phạm, phương thức hoạt động..."
                                      required>{{ old('description') }}</textarea>
                            <div class="form-text">Mô tả càng chi tiết càng hữu ích cho cộng đồng</div>
                            @error('description')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <!-- Ảnh minh họa -->
                        <div class="mb-5">
                            <label for="image" class="form-label fw-bold fs-5 text-gray-700">
                                <i class="fas fa-image me-2 text-danger"></i>Ảnh minh họa
                            </label>
                            <div class="file-upload-wrapper border-2 rounded-3 overflow-hidden">
                                <input type="file" 
                                       class="form-control form-control-lg visually-hidden" 
                                       id="image" name="image" accept="image/*">
                                <label for="image" class="file-upload-label w-100 py-4 px-4 text-center cursor-pointer">
                                    <div class="mb-3">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-danger opacity-50"></i>
                                    </div>
                                    <h5 class="mb-2">Chọn ảnh</h5>
                                    <p class="text-muted mb-0">Chỉ chấp nhận ảnh (JPEG, PNG, GIF) tối đa 5MB</p>
                                </label>
                            </div>
                            <div class="mt-3" id="image-preview"></div>
                            @error('image')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <!-- Checkbox cam đoan -->
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" value="1" id="confirmCheckbox" name="confirmCheckbox" required style="accent-color: #495057; border: 2px solid #e63946; width: 1.2em; height: 1.2em;">
                            <label class="form-check-label fw-semibold text-danger" for="confirmCheckbox">
                                Tôi cam đoan thông tin trên là đúng sự thật, chịu trách nhiệm trước pháp luật.
                            </label>
                            <div class="invalid-feedback">Bạn phải xác nhận cam đoan thông tin là đúng sự thật.</div>
                        </div>
                        
                        <!-- Nút submit -->
                        <div class="d-grid gap-3 mt-5">
                            <button type="submit" class="btn btn-danger btn-lg py-3 fw-bold rounded-3 shadow">
                                <i class="fas fa-paper-plane me-2"></i>Đăng cảnh báo
                            </button>
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-lg py-3 rounded-3">
                                <i class="fas fa-arrow-left me-2"></i>Quay lại
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Thông tin hỗ trợ -->
            <div class="card border-0 shadow-sm rounded-3 mt-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 text-danger">
                            <i class="fas fa-info-circle fa-2x"></i>
                        </div>
                        <div class="flex-grow-1 ms-4">
                            <h3 class="h5 fw-bold mb-3">Lưu ý quan trọng khi đăng cảnh báo</h3>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item bg-transparent border-0 px-0 py-1">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Cung cấp thông tin chính xác, tránh bịa đặt thông tin
                                </li>
                                <li class="list-group-item bg-transparent border-0 px-0 py-1">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Trong trường hợp khẩn cấp, vui lòng gọi ngay <span class="fw-bold">113</span>
                                </li>
                                <li class="list-group-item bg-transparent border-0 px-0 py-1">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Thông tin đăng tải sẽ được kiểm duyệt trước khi hiển thị công khai
                                </li>
                                <li class="list-group-item bg-transparent border-0 px-0 py-1">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Không chia sẻ thông tin cá nhân nhạy cảm
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
@endsection