@extends('layouts.public')

@section('title', 'Peta Sekolah')

@section('content')
<div class="toolbar">
    <label>Jenjang
        <select id="filter-jenjang">
            <option value="">Semua jenjang</option>
            <option value="SD">SD</option>
            <option value="SMP">SMP</option>
            <option value="SMA">SMA</option>
            <option value="SMK">SMK</option>
        </select>
    </label>
    <label>Status
        <select id="filter-status">
            <option value="">Semua status</option>
            <option value="Negeri">Negeri</option>
            <option value="Swasta">Swasta</option>
        </select>
    </label>
    <button id="unduh" type="button">Unduh GeoJSON</button>
    <span id="jumlah"></span>
</div>

<div id="peta" class="peta"></div>
@endsection

@push('scripts')
<script>
    const map = Peta.buat('peta');
    const lapisan = L.layerGroup().addTo(map);
    let tampilSekarang = []; // fitur yang sedang tampil di peta
    let permintaanKe = 0;    // penanda agar respons lama tidak menimpa yang baru

    async function muat() {
        const jenjang = document.getElementById('filter-jenjang').value;
        const status = document.getElementById('filter-status').value;
        const bbox = Peta.bboxPeta(map);
        const nomor = ++permintaanKe;

        try {
            const fitur = await Peta.ambilSekolah(jenjang, status, bbox);

            // Abaikan bila sudah ada permintaan yang lebih baru
            if (nomor !== permintaanKe) return;

            tampilSekarang = fitur;
            lapisan.clearLayers();

            fitur.forEach((f) => {
                L.marker(Peta.latlng(f))
                    .bindPopup(Peta.popup(f))
                    .addTo(lapisan);
            });

            document.getElementById('jumlah').textContent =
                fitur.length + ' sekolah di area ini';
        } catch (e) {
            document.getElementById('jumlah').textContent =
                'Gagal memuat data';
        }
    }

    function unduh() {
        if (tampilSekarang.length === 0) {
            alert('Tidak ada data untuk diunduh.');
            return;
        }

        const jenjang = document.getElementById('filter-jenjang').value;
        const status = document.getElementById('filter-status').value;

        const data = {
            type: 'FeatureCollection',
            features: tampilSekarang,
        };

        // Nama berkas mengikuti filter, misalnya sekolah-sd-negeri.geojson
        const nama = ['sekolah', jenjang, status]
            .filter(Boolean)
            .join('-')
            .toLowerCase() + '.geojson';

        const blob = new Blob([JSON.stringify(data, null, 2)], {
            type: 'application/geo+json',
        });
        const url = URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = nama;
        document.body.appendChild(a);
        a.click();
        a.remove();

        URL.revokeObjectURL(url);
    }

    // Muat ulang data setiap peta selesai digeser atau di-zoom
    let tunda;
    map.on('moveend', () => {
        clearTimeout(tunda);
        tunda = setTimeout(muat, 400);
    });

    document.getElementById('filter-jenjang').addEventListener('change', muat);
    document.getElementById('filter-status').addEventListener('change', muat);
    document.getElementById('unduh').addEventListener('click', unduh);

    muat();
</script>
@endpush