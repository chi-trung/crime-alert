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
    var alertData = window.ALERTS_DATA;
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
        // Issue #77: this used to interpolate alert.title/type/location into
        // an HTML template literal. Leaflet's bindPopup(string) innerHTMLs the
        // string, so those three user-authored columns were a stored-XSS sink
        // for every viewer of /alerts/map. Built as DOM nodes with textContent
        // instead (same fix as #18's notification dropdown); bindPopup accepts
        // an element and inserts it without HTML parsing.
        var popup = document.createElement('div');
        popup.style.minWidth = '200px';
        var titleEl = document.createElement('b');
        titleEl.textContent = alert.title;
        popup.appendChild(titleEl);
        popup.appendChild(document.createElement('br'));
        var badge = document.createElement('span');
        badge.className = 'badge bg-danger mb-1';
        badge.style.color = '#fff';
        badge.style.fontWeight = 'bold';
        badge.textContent = alert.type || 'Không rõ';
        popup.appendChild(badge);
        popup.appendChild(document.createElement('br'));
        var locEl = document.createElement('span');
        locEl.textContent = alert.location || '';
        popup.appendChild(locEl);
        popup.appendChild(document.createElement('br'));
        var link = document.createElement('a');
        link.href = '/alerts/' + encodeURIComponent(alert.id);
        link.className = 'btn btn-sm mt-2';
        link.style.background = '#dc3545';
        link.style.color = '#fff';
        link.style.fontWeight = 'bold';
        link.style.border = 'none';
        link.textContent = 'Xem chi tiết';
        popup.appendChild(link);
        marker.bindPopup(popup);
        markers.addLayer(marker);
    });
    map.addLayer(markers);

    // Đánh dấu vị trí hiện tại bằng icon đặc biệt
    function addCurrentLocationMarker(lat, lng) {
        // Issue #347: was a hot-linked flaticon PNG, so every map view
        // fetched a third-party pixel just to draw "you are here". A
        // divIcon renders the pin from inline SVG, no extra request.
        var myIcon = L.divIcon({
            className: 'current-location-marker',
            html: '<svg viewBox="0 0 24 24" width="32" height="32" fill="#0d6efd" stroke="#fff" stroke-width="1.5"><path d="M12 22s7-6.2 7-12A7 7 0 0 0 5 10c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.6" fill="#fff" stroke="none"/></svg>',
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            popupAnchor: [0, -32]
        });
        L.marker([lat, lng], {icon: myIcon}).addTo(map).bindPopup('Vị trí của bạn').openPopup();
    }
    // Issue #361: the DOM button is a different object from the L.control
    // above, but reused the same name — the control reference was shadowed
    // from this line on. Renamed to match the sibling picker
    // (alert_map_picker.js), which also attaches the handler on the next
    // tick: the control is added asynchronously by addTo(), so the button
    // is not guaranteed to exist yet at this point.
    setTimeout(function () {
        var locateButton = document.getElementById('locateMeBtn');
        if (!locateButton) return;

        locateButton.onclick = function() {
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
        };
    }, 0);
});