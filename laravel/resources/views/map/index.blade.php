@extends('layouts.app')

@section('content')
<div class="container-fluid p-0">
    <div class="row g-0">
        <div class="col-md-3 sidebar p-3 bg-light">
            <h5 class="mb-3">國土利用監測變異點查詢</h5>

            <div class="mb-2">
                <label class="form-label small mb-1">年度範圍</label>
                <div class="d-flex gap-2">
                    <select id="year_from" class="form-select form-select-sm">
                        <option value="">起始</option>
                        @foreach($years as $y)
                            <option value="{{ $y }}">{{ $y }} ({{ $y + 1911 }})</option>
                        @endforeach
                    </select>
                    <select id="year_to" class="form-select form-select-sm">
                        <option value="">結束</option>
                        @foreach($years as $y)
                            <option value="{{ $y }}">{{ $y }} ({{ $y + 1911 }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small mb-1">縣市</label>
                <select id="county_city" class="form-select form-select-sm">
                    <option value="">全部</option>
                    @foreach($counties as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label small mb-1">變異類型</label>
                <select id="change_type" class="form-select form-select-sm">
                    <option value="">全部</option>
                    @foreach($changeTypes as $ct)
                        <option value="{{ $ct }}">{{ $ct }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label small mb-1">查證結果</label>
                <select id="verification_result" class="form-select form-select-sm">
                    <option value="">全部</option>
                    @foreach($verificationResults as $vr)
                        <option value="{{ $vr }}">{{ $vr }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label small mb-1">搜尋半徑</label>
                <select id="radius" class="form-select form-select-sm">
                    <option value="100">100 公尺</option>
                    <option value="500">500 公尺</option>
                    <option value="1000" selected>1 公里</option>
                    <option value="5000">5 公里</option>
                    <option value="10000">10 公里</option>
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label small mb-1">快速輸入座標</label>
                <input type="text" id="latlng_paste" class="form-control form-control-sm mb-1" placeholder="貼上 緯度,經度 例如 25.033,121.565">
            </div>

            <div class="mb-2">
                <label class="form-label small mb-1">搜尋中心點（點擊地圖設定）</label>
                <div class="d-flex gap-2">
                    <input type="text" id="lat" class="form-control form-control-sm" placeholder="緯度">
                    <input type="text" id="lng" class="form-control form-control-sm" placeholder="經度">
                </div>
            </div>

            <button id="searchBtn" class="btn btn-primary btn-sm w-100 mb-3" disabled>搜尋</button>

            <div id="resultInfo" class="small text-muted mb-2"></div>

            <div id="resultList" class="list-group list-group-flush" style="max-height: 50vh; overflow-y: auto;"></div>
        </div>

        <div class="col-md-9 p-0">
            <div id="map"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const map = L.map('map').setView([23.7, 120.9], 7);

    const nlscEmap = L.tileLayer('https://wmts.nlsc.gov.tw/wmts/EMAP/default/GoogleMapsCompatible/{z}/{y}/{x}', {
        attribution: '&copy; 內政部國土測繪中心',
        maxZoom: 20
    });
    const nlscPhoto = L.tileLayer('https://wmts.nlsc.gov.tw/wmts/PHOTO2/default/GoogleMapsCompatible/{z}/{y}/{x}', {
        attribution: '&copy; 內政部國土測繪中心',
        maxZoom: 20
    });
    const nlscEmap2 = L.tileLayer('https://wmts.nlsc.gov.tw/wmts/EMAP2/default/GoogleMapsCompatible/{z}/{y}/{x}', {
        attribution: '&copy; 內政部國土測繪中心',
        maxZoom: 20
    });

    nlscEmap.addTo(map);

    L.control.layers({
        '通用版電子地圖': nlscEmap,
        '正射影像': nlscPhoto,
        '臺灣通用電子地圖(淺色)': nlscEmap2
    }).addTo(map);

    const markers = L.markerClusterGroup({
        iconCreateFunction: function(cluster) {
            const children = cluster.getAllChildMarkers();
            const hasViolation = children.some(m => m.options.isViolation);
            const count = cluster.getChildCount();
            let size = 'small';
            if (count >= 100) size = 'large';
            else if (count >= 10) size = 'medium';
            const className = 'marker-cluster marker-cluster-' + size +
                (hasViolation ? ' marker-cluster-violation' : '');
            return L.divIcon({
                html: '<div><span>' + count + '</span></div>',
                className: className,
                iconSize: L.point(40, 40)
            });
        }
    });
    map.addLayer(markers);

    let searchMarker = null;
    let searchCircle = null;

    const latInput = document.getElementById('lat');
    const lngInput = document.getElementById('lng');
    const searchBtn = document.getElementById('searchBtn');
    const pasteInput = document.getElementById('latlng_paste');

    function updatePasteDisplay() {
        if (latInput.value && lngInput.value) {
            pasteInput.value = latInput.value + ',' + lngInput.value;
        }
    }

    latInput.addEventListener('input', updatePasteDisplay);
    lngInput.addEventListener('input', updatePasteDisplay);

    function setSearchPoint(lat, lng) {
        latInput.value = lat;
        lngInput.value = lng;
        pasteInput.value = lat + ',' + lng;
        searchBtn.disabled = false;
        if (searchMarker) map.removeLayer(searchMarker);
        searchMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'search-center',
                html: '<div style="background:#0d6efd;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.5)"></div>',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            })
        }).addTo(map).bindPopup('<b>搜尋中心點</b><br><button class="btn btn-primary btn-sm mt-1" onclick="document.getElementById(\'searchBtn\').click()">搜尋此位置</button>').openPopup();
        map.setView([lat, lng], 14);
    }

    pasteInput.addEventListener('input', function() {
        const val = this.value.trim();
        const parts = val.split(/[,\s]+/);
        if (parts.length >= 2) {
            const a = parseFloat(parts[0]);
            const b = parseFloat(parts[1]);
            if (!isNaN(a) && !isNaN(b)) {
                setSearchPoint(a, b);
                this.value = '';
            }
        }
    });

    function getMarkerColor(verificationResult) {
        if (!verificationResult) return '#888';
        if (verificationResult === '非違規' || verificationResult === '合法') return '#28a745';
        if (verificationResult.includes('違規')) return '#dc3545';
        return '#ffc107';
    }

    function createIcon(color) {
        return L.divIcon({
            className: 'custom-marker',
            html: `<div style="background:${color};width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.4)"></div>`,
            iconSize: [12, 12],
            iconAnchor: [6, 6]
        });
    }

    map.on('click', function(e) {
        setSearchPoint(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6));
    });

    searchBtn.addEventListener('click', doSearch);

    function doSearch() {
        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);
        const radius = document.getElementById('radius').value;

        if (isNaN(lat) || isNaN(lng)) return;

        searchBtn.disabled = true;
        searchBtn.textContent = '搜尋中...';

        const params = new URLSearchParams({
            lat, lng, radius,
            year_from: document.getElementById('year_from').value,
            year_to: document.getElementById('year_to').value,
            county_city: document.getElementById('county_city').value,
            change_type: document.getElementById('change_type').value,
            verification_result: document.getElementById('verification_result').value,
        });

        fetch(`/search?${params}`)
            .then(r => r.json())
            .then(data => {
                markers.clearLayers();
                if (searchCircle) map.removeLayer(searchCircle);

                searchCircle = L.circle([lat, lng], {
                    radius: parseInt(radius),
                    color: '#0d6efd',
                    fillColor: '#0d6efd',
                    fillOpacity: 0.08,
                    weight: 1
                }).addTo(map);

                const resultList = document.getElementById('resultList');
                resultList.innerHTML = '';
                document.getElementById('resultInfo').textContent =
                    `找到 ${data.count} 筆結果` + (data.count >= 1000 ? '（顯示前 1000 筆）' : '');

                data.results.forEach((p, i) => {
                    const color = getMarkerColor(p.verification_result);
                    const isViolation = p.verification_result && p.verification_result.includes('違規') && p.verification_result !== '非違規';
                    const marker = L.marker([p.latitude, p.longitude], { icon: createIcon(color), isViolation });
                    marker.bindPopup(`
                        <b>${p.point_id}</b><br>
                        年度: ${p.year} (${p.year + 1911})<br>
                        縣市: ${p.county_city}<br>
                        權責單位: ${p.authority}<br>
                        變異類型: ${p.change_type || '-'}<br>
                        查證結果: <span style="color:${color};font-weight:bold">${p.verification_result || '-'}</span><br>
                        距離: ${p.distance} m
                    `);
                    markers.addLayer(marker);

                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action result-item p-2';
                    item.innerHTML = `
                        <div class="d-flex justify-content-between">
                            <small class="fw-bold">${p.point_id}</small>
                            <small class="text-muted">${p.distance}m</small>
                        </div>
                        <small class="text-muted">${p.year}(${p.year+1911}) ${p.county_city} -
                            <span style="color:${color}">${p.verification_result || '-'}</span></small><br>
                        <small class="text-truncate d-block" style="max-width:100%">${p.change_type || '-'}</small>
                    `;
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        map.setView([p.latitude, p.longitude], 16);
                        marker.openPopup();
                    });
                    resultList.appendChild(item);
                });

                map.fitBounds(searchCircle.getBounds());
                searchBtn.disabled = false;
                searchBtn.textContent = '搜尋';
            })
            .catch(err => {
                console.error(err);
                searchBtn.disabled = false;
                searchBtn.textContent = '搜尋';
                alert('搜尋失敗: ' + err.message);
            });
    }
})();
</script>
@endsection
