@extends('layouts.app')

@section('content')
<!-- Nhúng thư viện Leaflet và plugin -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<script>
    window.ALERTS_DATA = @json($alerts);
</script>
@vite(['resources/css/alerts_map.css', 'resources/js/alerts_map.js'])
<div class="container py-4">
    <h2 class="mb-4 fw-bold text-primary">Bản đồ cảnh báo tội phạm</h2>
    <div id="map" style="height: 600px; border-radius: 16px; overflow: hidden;"></div>
</div>
@endsection