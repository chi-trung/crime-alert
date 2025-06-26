@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2 class="mb-4 fw-bold text-primary">Bản đồ cảnh báo tội phạm</h2>
    <div id="map" style="height: 600px; border-radius: 16px; overflow: hidden;"></div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('map').setView([10.762622, 106.660172], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);
    setTimeout(function () { map.invalidateSize(); }, 0);

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

    var markers = L.markerClusterGroup();
    var alertData = @json($alerts);
    alertData.forEach(function(alert) {
        if (!alert.latitude || !alert.longitude) return;
        var marker = L.marker([alert.latitude, alert.longitude]);
        // Thêm vòng tròn đỏ mờ quanh marker (bán kính 200m)
        var circle = L.circle([alert.latitude, alert.longitude], {
            color: '#dc3545',
            fillColor: '#dc3545',
            fillOpacity: 0.18,
            radius: 200
        }).addTo(map);
        var popup = `<div style='min-width:200px'>
            <b>${alert.title}</b><br>
            <span class='badge bg-danger mb-1' style='color:#fff;font-weight:bold;'>${alert.type || 'Không rõ'}</span><br>
            <span>${alert.location || ''}</span><br>
            <a href='/alerts/${alert.id}' class='btn btn-sm mt-2' style='background:#dc3545;color:#fff;font-weight:bold;border:none;'>Xem chi tiết</a>
        </div>`;
        marker.bindPopup(popup);
        markers.addLayer(marker);
    });
    map.addLayer(markers);

    // Đánh dấu vị trí hiện tại bằng icon đặc biệt
    function addCurrentLocationMarker(lat, lng) {
        var myIcon = L.icon({
            iconUrl: 'https://cdn-icons-png.flaticon.com/512/684/684908.png',
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            popupAnchor: [0, -32]
        });
        L.marker([lat, lng], {icon: myIcon}).addTo(map).bindPopup('Vị trí của bạn').openPopup();
    }
    var locateBtn = document.getElementById('locateMeBtn');
    if (locateBtn) {
        locateBtn.onclick = function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;
                    map.setView([lat, lng], 15);
                    addCurrentLocationMarker(lat, lng);
                }, function() {
                    alert('Không thể lấy vị trí của bạn!');
                });
            } else {
                alert('Trình duyệt không hỗ trợ định vị!');
            }
        }
    }
});
</script>
@endsection 