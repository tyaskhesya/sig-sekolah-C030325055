<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Peta Sekolah</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">

    <style>
        html, body {
            height: 100%;
            margin: 0;
            font-family: system-ui, sans-serif;
        }

        header {
            min-height: 56px;
            box-sizing: border-box;
            padding: 0 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            background: #1e3a8a;
            color: white;
            flex-wrap: wrap;
        }

        header h1 {
            font-size: 18px;
            margin: 0;
        }

        header a {
            color: #bfdbfe;
            margin-left: auto;
        }

        #map {
            height: calc(100% - 56px);
        }

        #cari-sekolah {
            padding: 6px 8px;
            width: 180px;
        }
    </style>
</head>
<body>
    <header>
        <h1>Peta Sekolah</h1>

        <select id="filter-jenjang">
            <option value="">Semua jenjang</option>
            <option value="SD">SD</option>
            <option value="SMP">SMP</option>
            <option value="SMA">SMA</option>
            <option value="SMK">SMK</option>
        </select>

        <select id="filter-status">
            <option value="">Semua status</option>
            <option value="Negeri">Negeri</option>
            <option value="Swasta">Swasta</option>
        </select>

        <input
            type="text"
            id="cari-sekolah"
            placeholder="Cari nama sekolah..."
        >

        <span id="jumlah"></span>

        @auth
            <a href="{{ route('dashboard') }}">Dashboard</a>
        @else
            <a href="{{ route('login') }}">Masuk</a>
        @endauth
    </header>

    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    <script>
        const map = L.map('map').setView([-3.3194, 114.5908], 12);

        // Peta dasar OpenStreetMap
        const osm = L.tileLayer(
            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }
        );

        // Peta dasar Satelit
        const satelit = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                maxZoom: 19,
                attribution: 'Tiles &copy; Esri'
            }
        );

        // Peta dasar alternatif
        const carto = L.tileLayer(
            'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
            {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap &copy; CARTO'
            }
        );

        osm.addTo(map);

        const baseMaps = {
            "OpenStreetMap": osm,
            "Satelit": satelit,
            "CartoDB Light": carto
        };

        // Kelompok marker
        const cluster = L.markerClusterGroup();
        cluster.addTo(map);

        L.control.layers(baseMaps, null, {
            collapsed: false
        }).addTo(map);

        function warnaMarker(jenjang) {
            const warna = {
                SD: 'green',
                SMP: 'blue',
                SMA: 'orange',
                SMK: 'red'
            };

            return warna[jenjang] || 'gray';
        }

        // Pengaman XSS: semua teks dari pengguna lewat fungsi ini
        function esc(teks) {
            if (teks === null || teks === undefined) return '';
            return String(teks)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        const lapisan = L.geoJSON(null, {
            pointToLayer: (fitur, latlng) => {
                const warna = warnaMarker(fitur.properties.jenjang);

                return L.marker(latlng, {
                    icon: L.divIcon({
                        className: '',
                        html: `<div style="
                            width: 16px;
                            height: 16px;
                            background: ${warna};
                            border: 2px solid white;
                            border-radius: 50%;
                        "></div>`,
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    })
                });
            },

            onEachFeature: (fitur, layer) => {
                const p = fitur.properties;
                const [lng, lat] = fitur.geometry.coordinates;

                layer.bindPopup(`
                    <strong>${esc(p.nama)}</strong><br>
                    NPSN: ${esc(p.npsn)}<br>
                    ${esc(p.jenjang)} - ${esc(p.status)}<br>
                    ${esc(p.alamat)}, ${esc(p.kecamatan)}<br>
                    Telp: ${esc(p.telepon) || '-'}<br>
                    Kepala sekolah: ${esc(p.kepala_sekolah) || '-'}<br>
                    Akreditasi: ${esc(p.akreditasi) || '-'}<br>
                    Siswa: ${p.jumlah_siswa ?? '-'} | Guru: ${p.jumlah_guru ?? '-'}<br>
                    <small>Koordinat: ${lat}, ${lng}</small>
                `);
            }
        });

        async function muatData() {
            try {
                const jenjang = document.getElementById('filter-jenjang').value;
                const status = document.getElementById('filter-status').value;

                const url = "{{ route('peta.data') }}" +
                    '?jenjang=' + encodeURIComponent(jenjang) +
                    '&status=' + encodeURIComponent(status);

                const respon = await fetch(url);

                if (!respon.ok) {
                    throw new Error('Gagal mengambil data sekolah');
                }

                const data = await respon.json();

                lapisan.clearLayers();
                lapisan.addData(data);

                cluster.clearLayers();
                cluster.addLayer(lapisan);

                document.getElementById('jumlah').textContent =
                    data.features.length + ' sekolah';

                if (data.features.length > 0) {
                    map.fitBounds(cluster.getBounds().pad(0.1));
                }
            } catch (error) {
                console.error(error);
            }
        }

        async function cariSekolah() {
            const keyword = document.getElementById('cari-sekolah').value
                .trim().toLowerCase();

            if (!keyword) {
                muatData();
                return;
            }

            try {
                const respon = await fetch("{{ route('peta.data') }}");
                const data = await respon.json();

                const sekolah = data.features.find(fitur =>
                    (fitur.properties.nama || '').toLowerCase().includes(keyword)
                );

                if (sekolah) {
                    let ditemukan = false;

                    lapisan.eachLayer(layer => {
                        if (layer.feature.properties.npsn === sekolah.properties.npsn) {
                            ditemukan = true;

                            cluster.zoomToShowLayer(layer, () => {
                                layer.openPopup();
                            });
                        }
                    });

                    if (!ditemukan) {
                        alert('Sekolah ditemukan, tetapi tidak sesuai dengan filter yang sedang dipilih. Silakan ubah filter terlebih dahulu.');
                    }
                } else {
                    alert('Sekolah tidak ditemukan!');
                }
            } catch (error) {
                console.error(error);
            }
        }

        document.getElementById('filter-jenjang')
            .addEventListener('change', muatData);

        document.getElementById('filter-status')
            .addEventListener('change', muatData);

        document.getElementById('cari-sekolah')
            .addEventListener('change', cariSekolah);

        muatData();
    </script>
</body>
</html>