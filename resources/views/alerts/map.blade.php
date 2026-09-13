@extends('layouts.app')

@section('content')
<!-- Nhúng thư viện Leaflet và plugin -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
{{-- Issue #167: version-pin + SRI, matching the leaflet@1.9.4 /
     markercluster@1.5.3 siblings — the two unpinned geocoder tags silently
     followed upstream latest (which already auto-moved 3.x -> 4.0.0,
     executing an unreviewed major rewrite on every authed map page).
     Hashes verified against the @4.0.0 dist files at fix time. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@4.0.0/dist/Control.Geocoder.css" integrity="sha384-dtZhMVplthx1XPTPFEKMM5M6e369Paz7gy0QTqvuQKB42lq4FIPsrqe125Ho6bfO" crossorigin="anonymous" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder@4.0.0/dist/Control.Geocoder.js" integrity="sha384-GwOxBPYQUJoAtZlP9zcDGxDFHdgRasiwmwj4JQoxhWpOBaETX1aOU/qm8fsP4Hf5" crossorigin="anonymous"></script>
<link rel="stylesheet" href="{{ asset('css/alerts_map.css') }}">
<script src="{{ asset('js/alerts_map.js') }}"></script>
<script>
    window.ALERTS_DATA = @json($alerts);
</script>
<div class="container py-4">
    <h2 class="mb-4 fw-bold text-primary">Bản đồ cảnh báo tội phạm</h2>
    <div id="map" style="height: 600px; border-radius: 16px; overflow: hidden;"></div>
</div>
@endsection