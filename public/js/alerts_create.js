// Map initialization and functionality
function initializeMap() {
    const mapEl = document.getElementById('map');
    if (!mapEl) {
        console.error('Không tìm thấy phần tử #map');
        return;
    }

    try {
        const map = L.map('map').setView([10.762622, 106.660172], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19,
        }).addTo(map);

        setTimeout(function () { map.invalidateSize(); }, 0);
        let marker;

        // Nếu đã có giá trị latitude/longitude thì hiển thị marker
        const lat = document.getElementById('latitude').value;
        const lng = document.getElementById('longitude').value;

        function reverseGeocode(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.display_name) {
                        document.getElementById('location').value = data.display_name;
                    } else {
                        document.getElementById('location').value = '';
                    }
                })
                .catch(() => {
                    document.getElementById('location').value = '';
                });
        }

        if (lat && lng) {
            marker = L.marker([lat, lng]).addTo(map);
            map.setView([lat, lng], 15);
            reverseGeocode(lat, lng);
        } else if (navigator.geolocation) {
            // Nếu chưa có lat/lng, tự động lấy vị trí hiện tại
            navigator.geolocation.getCurrentPosition(function(position) {
                const currLat = position.coords.latitude;
                const currLng = position.coords.longitude;
                document.getElementById('latitude').value = currLat.toFixed(7);
                document.getElementById('longitude').value = currLng.toFixed(7);
                marker = L.marker([currLat, currLng]).addTo(map);
                map.setView([currLat, currLng], 15);
                reverseGeocode(currLat, currLng);
            }, function() {
                // Nếu không lấy được vị trí thì giữ nguyên view mặc định
                console.warn('Không thể lấy vị trí hiện tại.');
            });
        }

        map.on('click', function(e) {
            const { lat, lng } = e.latlng;
            document.getElementById('latitude').value = lat.toFixed(7);
            document.getElementById('longitude').value = lng.toFixed(7);
            if (marker) marker.setLatLng(e.latlng);
            else marker = L.marker(e.latlng).addTo(map);
            reverseGeocode(lat, lng);
        });

        // Tìm kiếm địa chỉ
        L.Control.geocoder({
            defaultMarkGeocode: false,
            placeholder: 'Tìm địa chỉ...'
        })
        .on('markgeocode', function(e) {
            var bbox = e.geocode.bbox;
            var poly = L.polygon([
                bbox.getSouthEast(),
                bbox.getNorthEast(),
                bbox.getNorthWest(),
                bbox.getSouthWest()
            ]);
            map.fitBounds(poly.getBounds());
            // Đặt marker tại vị trí tìm được
            var center = e.geocode.center;
            document.getElementById('latitude').value = center.lat.toFixed(7);
            document.getElementById('longitude').value = center.lng.toFixed(7);
            if (marker) marker.setLatLng(center);
            else marker = L.marker(center).addTo(map);
        })
        .addTo(map);

        // Nút lấy vị trí hiện tại
        var locateBtn = L.control({position: 'topleft'});
        locateBtn.onAdd = function(map) {
            var div = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
            div.innerHTML = '<button id="locateMeBtn" title="Lấy vị trí của tôi" style="background:white;border:none;padding:6px 10px;cursor:pointer;"><i class="fas fa-location-arrow"></i> Vị trí của tôi</button>';
            return div;
        };
        locateBtn.addTo(map);

        setTimeout(function() {
            var btn = document.getElementById('locateMeBtn');
            if (btn) {
                btn.onclick = function() {
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(function(position) {
                            var lat = position.coords.latitude;
                            var lng = position.coords.longitude;
                            map.setView([lat, lng], 15);
                            document.getElementById('latitude').value = lat.toFixed(7);
                            document.getElementById('longitude').value = lng.toFixed(7);
                            if (marker) marker.setLatLng([lat, lng]);
                            else marker = L.marker([lat, lng]).addTo(map);
                        }, function() {
                            alert('Không thể lấy vị trí của bạn!');
                        });
                    } else {
                        alert('Trình duyệt không hỗ trợ định vị!');
                    }
                }
            }
        }, 500);

        mapEl.classList.add('leaflet-loaded');
    } catch (error) {
        console.error('Lỗi khi tạo bản đồ:', error);
        mapEl.innerHTML = `<div class="alert alert-danger p-3">Không thể tải bản đồ: ${error.message}</div>`;
    }
}

// Fix icon marker cho Leaflet nếu bị lỗi 404
function fixLeafletIcons() {
    if (window.L && L.Icon && L.Icon.Default) {
        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
            iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        });
    }
}

// Xem trước ảnh trước khi upload
function setupImagePreview() {
    document.getElementById('image').addEventListener('change', function(e) {
        const preview = document.getElementById('image-preview');
        preview.innerHTML = '';
        
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const imgContainer = document.createElement('div');
                imgContainer.className = 'position-relative d-inline-block';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'img-fluid rounded-3 shadow-sm border';
                img.style.maxHeight = '300px';
                
                const btnRemove = document.createElement('button');
                btnRemove.type = 'button';
                btnRemove.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 m-2 rounded-circle';
                btnRemove.innerHTML = '<i class="fas fa-times"></i>';
                btnRemove.onclick = function() {
                    document.getElementById('image').value = '';
                    preview.innerHTML = '';
                };
                
                imgContainer.appendChild(img);
                imgContainer.appendChild(btnRemove);
                preview.appendChild(imgContainer);
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });
}

// Validation form
function setupFormValidation() {
    'use strict'

    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')

    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }

                form.classList.add('was-validated')
            }, false)
        })
}

// Initialize all functions when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeMap();
    fixLeafletIcons();
    setupImagePreview();
    setupFormValidation();
});