@extends('layouts.public')

@section('title', 'Wilayah')

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
    <span id="jumlah"></span>
    <span id="sumber" style="color: #6b7280;"></span>
</div>

<div id="peta" class="peta"></div>

<div class="panel" style="right: 12px; bottom: 24px; font-size: 13px;">
    <strong>Jumlah sekolah</strong>
    <div><span style="color: #dc2626;">&#9632;</span> Terbanyak</div>
    <div><span style="color: #ea580c;">&#9632;</span> Banyak</div>
    <div><span style="color: #f59e0b;">&#9632;</span> Sedang</div>
    <div><span style="color: #3b82f6;">&#9632;</span> Sedikit</div>
    <div><span style="color: #9ca3af;">&#9632;</span> Tidak ada sekolah</div>
    <small style="color: #6b7280;">Relatif terhadap kecamatan terbanyak</small>
</div>
@endsection

@push('scripts')
<script>
    const FILE_BATAS = "{{ asset('geo/kecamatan.geojson') }}";

    // Kemungkinan nama kolom yang berisi nama kecamatan pada file GeoJSON
    const KUNCI_NAMA = ['NAME_3', 'NAMOBJ', 'WADMKC', 'NAMA_KEC',
                        'nama', 'kecamatan', 'KECAMATAN', 'district'];

    const map = Peta.buat('peta');
    let lapisan = null;
    let batas = undefined; // undefined = belum dicoba, null = file tidak ada

    function warna(rasio) {
        if (rasio >= 0.8) return '#dc2626';
        if (rasio >= 0.6) return '#ea580c';
        if (rasio >= 0.4) return '#f59e0b';
        return '#3b82f6';
    }

    // Nama kecamatan dari properti sebuah fitur batas
    function namaKec(props) {
        for (const k of KUNCI_NAMA) {
            if (props[k]) return String(props[k]);
        }
        return '';
    }

    // "BANJARMASIN UTARA", "Kecamatan Banjarmasin Utara", dan "Utara" jadi sama
    function kunci(teks) {
        return String(teks ?? '')
            .toLowerCase()
            .replace(/kecamatan|kec\.|banjarmasin/g, '')
            .replace(/[^a-z]/g, '');
    }

    // Memuat file batas resmi satu kali; null bila tidak ada atau rusak
    async function muatBatas() {
        if (batas !== undefined) return batas;
        try {
            const respon = await fetch(FILE_BATAS);
            if (!respon.ok) throw new Error('HTTP ' + respon.status);
            batas = await respon.json();
        } catch (e) {
            console.error('File batas tidak dapat dimuat:', e);
            batas = null;
        }
        return batas;
    }

    // Menghitung sekolah dan siswa per kecamatan, lalu menempelkannya ke batas resmi
    function gabung(fileBatas, sekolah) {
        const hitung = {};
        sekolah.forEach((f) => {
            const k = kunci(f.properties.kecamatan);
            hitung[k] ??= { sekolah: 0, siswa: 0 };
            hitung[k].sekolah += 1;
            hitung[k].siswa += f.properties.jumlah_siswa ?? 0;
        });

        return {
            type: 'FeatureCollection',
            features: fileBatas.features.map((f) => {
                const nama = namaKec(f.properties);
                const h = hitung[kunci(nama)] ?? { sekolah: 0, siswa: 0 };
                return {
                    ...f,
                    properties: {
                        ...f.properties,
                        kecamatan: nama,
                        jumlah_sekolah: h.sekolah,
                        jumlah_siswa: h.siswa,
                    },
                };
            }),
        };
    }

    async function muat(jenjang = '') {
        const fileBatas = await muatBatas();
        let data;
        let sumber;

        if (fileBatas) {
            const sekolah = await Peta.ambilSekolah(jenjang);
            data = gabung(fileBatas, sekolah);
            sumber = 'Sumber: batas kecamatan resmi (file GeoJSON)';
        } else {
            // Cadangan: poligon sebaran dari server (praktikum 24)
            const url = `${window.PETA_URL.wilayah}?jenjang=${encodeURIComponent(jenjang)}`;
            data = await (await fetch(url)).json();
            sumber = 'Sumber: poligon sebaran (file batas resmi tidak ditemukan)';
        }

        if (lapisan) {
            map.removeLayer(lapisan);
        }

        const maks = Math.max(1, ...data.features.map((f) => f.properties.jumlah_sekolah));

        lapisan = L.geoJSON(data, {
            style: (f) => {
                const n = f.properties.jumlah_sekolah;
                const w = n === 0 ? '#9ca3af' : warna(n / maks);
                return { color: w, fillColor: w, fillOpacity: 0.3, weight: 3 };
            },
            onEachFeature: (f, layer) => {
                const p = f.properties;
                layer.bindTooltip(Peta.esc(p.kecamatan), { sticky: true });
                layer.bindPopup(`
                    <strong>${Peta.esc(p.kecamatan)}</strong><br>
                    Jumlah sekolah: ${p.jumlah_sekolah}<br>
                    Jumlah siswa: ${p.jumlah_siswa}
                `);
            },
        }).addTo(map);

        document.getElementById('jumlah').textContent =
            data.features.length + ' wilayah';
        document.getElementById('sumber').textContent = sumber;

        if (data.features.length > 0) {
            map.fitBounds(lapisan.getBounds().pad(0.1));
        }
    }

    document.getElementById('filter-jenjang')
        .addEventListener('change', (e) => muat(e.target.value));

    muat();
</script>
@endpush