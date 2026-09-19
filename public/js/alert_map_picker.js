// Issue #349: the map on /alerts/{id}/edit duplicated this logic inline and
// silently dropped two of its pieces — see editPositionableMap() below.
//
// Shared "pick one location" map for the create and edit alert pages: a
// geocoder search box, a click-to-drop handler, a reverse-geocode that fills
// the address field, and a "where am I" button. Both pages must behave the
// same way; the edit page used to offer only click-to-drop.

// Leaflet 1.9 will not draw Default markers at all unless _getIconUrl resolves.
// alerts_create.js carried this fix; alerts/edit.blade.php inline block did
// not, so every marker on the edit page was simply absent (no <img> emitted,
// not a broken one).
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

// Issue #295: latitude/longitude are repopulated verbatim by old() after a
// failed validation, so they are untrusted bytes. The finite gate keeps
// garbage from reaching L.marker (whose "Invalid LatLng object: (<raw>, ...)"
// throw then smuggled the raw value into an innerHTML fallback), and the
// encoding keeps even a valid-looking value percent-escaped in the outbound
// URL. Returns null when the inputs are absent or do not parse.
function readPosition() {
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    if (!latInput || !lngInput) return null;

    const lat = Number(latInput.value);
    const lng = Number(lngInput.value);
    const ok = Number.isFinite(lat) && Number.isFinite(lng)
        && latInput.value !== '' && lngInput.value !== '';

    return ok ? { lat, lng } : null;
}

function writePosition(lat, lng) {
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    if (latInput) latInput.value = lat.toFixed(7);
    if (lngInput) lngInput.value = lng.toFixed(7);
}

// Nominatim fills the address field from the chosen point. The location input
// is optional on the server, so a failed lookup just leaves the field alone.
function reverseGeocode(lat, lng) {
    const locationInput = document.getElementById('location');
    if (!locationInput) return;

    fetch('https://nominatim.openstreetmap.org/reverse?lat=' + encodeURIComponent(lat)
        + '&lon=' + encodeURIComponent(lng) + '&format=json')
        .then(function (response) { return response.json(); })
        .then(function (data) {
            locationInput.value = (data && data.display_name) ? data.display_name : '';
        })
        .catch(function () {
            locationInput.value = '';
        });
}

// The "Vị trí của tôi" leaflet control. The glyph stays: the button's whole
// label is text, and the pin is the button's meaning (#347 doctrine — an icon
// that says the same word as the label is decoration, one that adds a concept
// is not).
function addLocateControl(map, onLocate) {
    var locateBtn = L.control({ position: 'topleft' });
    locateBtn.onAdd = function () {
        var div = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
        // Issue #412: title= is a tooltip, not an accessible name — the same
        // defect #408 closed on the comment like button. The glyph is
        // decorative, so it is hidden from the accessibility tree.
        div.innerHTML = '<button id="locateMeBtn" aria-label="Lấy vị trí của tôi" title="Lấy vị trí của tôi" style="background:white;border:none;padding:6px 10px;cursor:pointer;"><i class="fas fa-location-arrow" aria-hidden="true"></i> Vị trí của tôi</button>';
        return div;
    };
    locateBtn.addTo(map);

    // The control is added asynchronously after map init, so the handler is
    // attached on the next tick rather than inline.
    setTimeout(function () {
        var btn = document.getElementById('locateMeBtn');
        if (!btn) return;
        // Issue #412: a raw alert() is a blocking, styleless, inaccessible
        // dialog, and on the failure paths the button would otherwise look
        // dead. Announce into a polite region built once and reused.
        function announceLocation(message) {
            var host = document.getElementById('map-location-status');
            if (!host) {
                host = document.createElement('span');
                host.id = 'map-location-status';
                host.setAttribute('role', 'status');
                host.setAttribute('aria-live', 'polite');
                // Visually hidden, applied inline so the region needs no
                // stylesheet. Clipped, never display:none — that would remove
                // it from the accessibility tree.
                host.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';
                var mapEl = document.getElementById(btn.closest('.leaflet-container') ? btn.closest('.leaflet-container').id : 'map');
                (mapEl || document.body).appendChild(host);
            }
            host.textContent = message;
        }
        btn.onclick = function () {
            if (!navigator.geolocation) {
                announceLocation('Trình duyệt không hỗ trợ định vị!');
                return;
            }
            navigator.geolocation.getCurrentPosition(function (position) {
                onLocate(position.coords.latitude, position.coords.longitude);
                announceLocation('Đã định vị được vị trí của bạn.');
            }, function () {
                announceLocation('Không thể lấy vị trí của bạn!');
            });
        };
    }, 0);
}

// Build the map itself. containerId, the optional seed point and the optional
// address field are the only things that differ between the two pages.
function editPositionableMap(options) {
    var mapEl = document.getElementById(options.containerId);
    if (!mapEl) {
        console.error('Không tìm thấy phần tử #' + options.containerId);
        return;
    }

    try {
        var map = L.map(options.containerId).setView([10.762622, 106.660172], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19,
        }).addTo(map);

        setTimeout(function () { map.invalidateSize(); }, 0);

        var marker;
        var seed = readPosition();

        function dropMarker(lat, lng) {
            if (marker) marker.setLatLng([lat, lng]);
            else marker = L.marker([lat, lng]).addTo(map);
        }

        if (seed) {
            dropMarker(seed.lat, seed.lng);
            map.setView([seed.lat, seed.lng], 15);
            // Only the create page reverse-geocodes on load: the edit page's
            // stored location is already correct, and re-looking it up would
            // rewrite a human-curated address with whatever Nominatim returns.
            if (options.geocodeOnLoad !== false) reverseGeocode(seed.lat, seed.lng);
        } else if (navigator.geolocation && options.geocodeOnLoad !== false) {
            // Nếu chưa có lat/lng, tự động lấy vị trí hiện tại
            navigator.geolocation.getCurrentPosition(function (position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                writePosition(lat, lng);
                dropMarker(lat, lng);
                map.setView([lat, lng], 15);
                reverseGeocode(lat, lng);
            }, function () {
                // Nếu không lấy được vị trí thì giữ nguyên view mặc định
                console.warn('Không thể lấy vị trí hiện tại.');
            });
        }

        map.on('click', function (e) {
            writePosition(e.latlng.lat, e.latlng.lng);
            dropMarker(e.latlng.lat, e.latlng.lng);
            reverseGeocode(e.latlng.lat, e.latlng.lng);
        });

        // Tìm kiếm địa chỉ
        L.Control.geocoder({
            defaultMarkGeocode: false,
            placeholder: 'Tìm địa chỉ...'
        })
        .on('markgeocode', function (e) {
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
            writePosition(center.lat, center.lng);
            dropMarker(center.lat, center.lng);
            reverseGeocode(center.lat, center.lng);
        })
        .addTo(map);

        addLocateControl(map, function (lat, lng) {
            map.setView([lat, lng], 15);
            writePosition(lat, lng);
            dropMarker(lat, lng);
            reverseGeocode(lat, lng);
        });

        mapEl.classList.add('leaflet-loaded');
    } catch (error) {
        console.error('Lỗi khi tạo bản đồ:', error);
        // Issue #295 (#77's textContent idiom): error.message for an
        // invalid-LatLng throw embeds the RAW bytes of the old('latitude')
        // input, so interpolating it into innerHTML echoed the user's own
        // markup — self-XSS one submit away. Build the alert from a text
        // node: the message is displayed, never parsed.
        var alertEl = document.createElement('div');
        alertEl.className = 'alert alert-danger p-3';
        alertEl.textContent = 'Không thể tải bản đồ: ' + error.message;
        mapEl.replaceChildren(alertEl);
    }
}
