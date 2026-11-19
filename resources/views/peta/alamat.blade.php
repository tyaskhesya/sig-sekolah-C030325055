@extends('layouts.public')

@section('title', 'Cek Lokasi')

@section('content')
<div class="toolbar">
    <button id="lokasi-saya" type="button">Lokasi saya</button>
    <span id="status-lokasi"></span>
    <span style="color: #6b7280;">
        Atau klik di mana saja pada peta untuk melihat alamat, sekolah terdekat, dan jangkauan 1 km.
    </span>
</div>

<div id="peta" class="peta"></div>

<div id="hasil" class="panel"
     style="top: 64px; right: 12px; width: 320px; max-height: 70%;
            overflow: auto; display: none;"></div>
@endsection

@push('scripts')
<script>
    const RADIUS_KM = 1;

    const map = Peta.buat('peta');
    const lapisan = L.layerGroup().addTo(map);
    const panel = document.getElementById('hasil');
    const statusLokasi = document.getElementById('status-lokasi');
    let semua = [];
    let terakhir = 0;

    Peta.ambilSekolah().then((fitur) => { semua = fitur; });

    // Semua sekolah beserta jaraknya dari titik [lat, lng], diurutkan dari terdekat
    function urutJarak(titik) {
        return semua
            .map((f) => ({ fitur: f, jarak: Peta.jarakKm(titik, Peta.latlng(f)) }))
            .sort((a, b) => a.jarak - b.jarak);
    }

    async function ambilAlamat(lat, lng) {
        const url = 'https://nominatim.openstreetmap.org/reverse'
            + '?format=jsonv2&addressdetails=1&accept-language=id'
            + `&lat=${lat}&lon=${lng}`;

        const respon = await fetch(url);
        if (!respon.ok) throw new Error('HTTP ' + respon.status);
        return respon.json();
    }

    // Dipakai bersama oleh klik peta dan tombol "Lokasi saya"
    async function periksa(lat, lng) {
        // Kebijakan Nominatim: maksimal 1 permintaan per detik
        const sekarang = Date.now();
        if (sekarang - terakhir < 1200) return;
        terakhir = sekarang;

        const titik = [lat, lng];
        const diurutkan = urutJarak(titik);
        const dekat = diurutkan.slice(0, 3);
        const dalamRadius = diurutkan.filter((d) => d.jarak <= RADIUS_KM);

        lapisan.clearLayers();

        // Lingkaran jangkauan (L.circle memakai satuan meter)
        L.circle(titik, {
            radius: RADIUS_KM * 1000,
            color: '#16a34a', weight: 2,
            fillColor: '#22c55e', fillOpacity: 0.12,
        }).addTo(lapisan);

        // Sekolah di dalam jangkauan diberi titik hijau
        dalamRadius.forEach((d) => {
            L.circleMarker(Peta.latlng(d.fitur), {
                radius: 6, color: '#fff', weight: 2,
                fillColor: '#16a34a', fillOpacity: 1,
            }).bindPopup(Peta.popup(d.fitur)).addTo(lapisan);
        });

        // Titik yang diklik
        L.circleMarker(titik, {
            radius: 9, color: '#fff', weight: 3,
            fillColor: '#ef4444', fillOpacity: 1,
        }).addTo(lapisan);

        // Garis putus-putus ke tiga sekolah terdekat
        dekat.forEach((d) => {
            L.polyline([titik, Peta.latlng(d.fitur)], {
                color: '#2563eb', weight: 2, dashArray: '6 6',
            }).addTo(lapisan);
        });

        panel.style.display = 'block';
        panel.textContent = 'Mencari alamat...';

        let bagianAlamat = '<em>Alamat tidak dapat dimuat.</em>';
        try {
            const data = await ambilAlamat(lat, lng);
            const a = data.address || {};
            const baris = [
                ['Jalan', a.road],
                ['Kelurahan/Desa', a.suburb || a.neighbourhood || a.village],
                ['Kota/Kabupaten', a.city || a.town || a.municipality || a.county],
                ['Provinsi', a.state],
                ['Kode pos', a.postcode],
            ].map(([k, v]) => `
                <tr>
                    <td style="padding: 2px 8px 2px 0; color: #6b7280;">${k}</td>
                    <td>${Peta.esc(v) || '-'}</td>
                </tr>`).join('');

            bagianAlamat = `
                <p style="margin: 6px 0;">${Peta.esc(data.display_name || '-')}</p>
                <table style="font-size: 13px;">${baris}</table>`;
        } catch (err) {
            console.error(err);
        }

        const daftar = dekat.map((d) => `
            <li data-npsn="${Peta.esc(d.fitur.properties.npsn)}"
                style="padding: 6px 0; border-top: 1px solid #e5e7eb; cursor: pointer;">
                <strong>${Peta.esc(d.fitur.properties.nama)}</strong><br>
                <small>${d.jarak.toFixed(2)} km (garis lurus)</small>
            </li>`).join('');

        // Rincian jumlah sekolah dalam radius per jenjang
        const perJenjang = {};
        dalamRadius.forEach((d) => {
            const j = d.fitur.properties.jenjang || '-';
            perJenjang[j] = (perJenjang[j] || 0) + 1;
        });
        const rincian = Object.entries(perJenjang)
            .map(([j, n]) => `${Peta.esc(j)}: ${n}`)
            .join(', ');

        panel.innerHTML = `
            <strong>Koordinat</strong>
            <div style="font-family: monospace;">${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
            <strong style="display: block; margin-top: 8px;">Alamat</strong>
            ${bagianAlamat}
            <strong style="display: block; margin-top: 8px;">
                Jangkauan ${RADIUS_KM} km
            </strong>
            <div style="font-size: 18px; font-weight: 600;">
                ${dalamRadius.length} sekolah
            </div>
            <small style="color: #6b7280;">${rincian || 'Tidak ada sekolah dalam radius ini'}</small>
            <strong style="display: block; margin-top: 8px;">Sekolah terdekat</strong>
            <ul style="list-style: none; margin: 4px 0 0; padding: 0;">${daftar}</ul>`;
    }

    map.on('click', (e) => periksa(e.latlng.lat, e.latlng.lng));

    // Tombol "Lokasi saya"
    document.getElementById('lokasi-saya').addEventListener('click', () => {
        if (!navigator.geolocation) {
            statusLokasi.textContent = 'Browser tidak mendukung geolokasi.';
            return;
        }

        const tombol = document.getElementById('lokasi-saya');
        tombol.disabled = true;
        statusLokasi.textContent = 'Mencari posisi Anda...';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                tombol.disabled = false;
                statusLokasi.textContent =
                    `Akurasi sekitar ${Math.round(pos.coords.accuracy)} m`;

                const { latitude, longitude } = pos.coords;
                map.flyTo([latitude, longitude], 15);
                periksa(latitude, longitude);
            },
            (err) => {
                tombol.disabled = false;
                const pesan = {
                    1: 'Izin lokasi ditolak. Aktifkan izin lokasi pada browser.',
                    2: 'Posisi tidak dapat ditentukan.',
                    3: 'Waktu pencarian posisi habis. Coba lagi.',
                };
                statusLokasi.textContent = pesan[err.code] || 'Gagal mengambil lokasi.';
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    // Klik pada daftar sekolah terdekat: terbang ke sekolah tersebut
    panel.addEventListener('click', (e) => {
        const li = e.target.closest('[data-npsn]');
        if (!li) return;

        const f = semua.find((x) => x.properties.npsn === li.dataset.npsn);
        if (f) {
            L.popup().setLatLng(Peta.latlng(f)).setContent(Peta.popup(f)).openOn(map);
            map.flyTo(Peta.latlng(f), 17);
        }
    });
</script>
@endpush