@extends('layouts.app')

@section('content')
<!-- Nhúng thư viện Leaflet và plugin -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H" crossorigin="anonymous"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" integrity="sha384-pmjIAcz2bAn0xukfxADbZIb3t8oRT9Sv0rvO+BR5Csr6Dhqq+nZs59P0pPKQJkEV" crossorigin="anonymous" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" integrity="sha384-wgw+aLYNQ7dlhK47ZPK7FRACiq7ROZwgFNg0m04avm4CaXS+Z9Y7nMu8yNjBKYC+" crossorigin="anonymous" />
{{-- Issue #167: version-pin + SRI, matching the leaflet@1.9.4 /
     markercluster@1.5.3 siblings — the two unpinned geocoder tags silently
     followed upstream latest (which already auto-moved 3.x -> 4.0.0,
     executing an unreviewed major rewrite on every authed map page).
     Hashes verified against the @4.0.0 dist files at fix time. Issue #327
     extended the same treatment to the leaflet + markercluster siblings
     themselves (they were pinned but integrity-less, and this comment
     wrongly read as though they already had hashes). --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@4.0.0/dist/Control.Geocoder.css" integrity="sha384-dtZhMVplthx1XPTPFEKMM5M6e369Paz7gy0QTqvuQKB42lq4FIPsrqe125Ho6bfO" crossorigin="anonymous" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH" crossorigin="anonymous"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" integrity="sha384-eXVCORTRlv4FUUgS/xmOyr66XBVraen8ATNLMESp92FKXLAMiKkerixTiBvXriZr" crossorigin="anonymous"></script>
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