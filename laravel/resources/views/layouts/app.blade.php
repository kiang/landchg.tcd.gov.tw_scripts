<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>國土利用監測變異點查詢</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" rel="stylesheet">
    <style>
        html, body { height: 100%; margin: 0; }
        #map { height: 100%; }
        .sidebar { height: 100vh; overflow-y: auto; }
        .result-item { cursor: pointer; }
        .result-item:hover { background-color: #f8f9fa; }
        .marker-cluster-violation { background-color: rgba(220, 53, 69, 0.6); }
        .marker-cluster-violation div { background-color: rgba(220, 53, 69, 0.8); }
    </style>
    @yield('styles')
</head>
<body>
    @yield('content')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    @yield('scripts')
</body>
</html>
