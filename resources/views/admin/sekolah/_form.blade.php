@php
    $teks = [
        'npsn' => 'NPSN (8 digit)',
        'nama' => 'Nama Sekolah',
        'kecamatan' => 'Kecamatan',
        'telepon' => 'Telepon',
    ];

    $pilihan = [
        'jenjang' => ['SD', 'SMP', 'SMA', 'SMK'],
        'status' => ['Negeri', 'Swasta'],
    ];

    $cls = 'w-full border rounded px-3 py-2';
@endphp

<div class="bg-white rounded shadow p-6 grid md:grid-cols-2 gap-6">
    <div class="space-y-4">
        @foreach ($teks as $name => $label)
            <div>
                <label for="{{ $name }}" class="block font-medium">
                    {{ $label }}
                </label>

                <input type="text" id="{{ $name }}" name="{{ $name }}"
                       value="{{ old($name, $sekolah->$name) }}"
                       @readonly($name === 'npsn' && auth()->user()->isSekolah())
                       class="{{ $cls }}">

                @error($name)
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        @foreach ($pilihan as $name => $opsi)
            <div>
                <label for="{{ $name }}" class="block font-medium">
                    {{ ucfirst($name) }}
                </label>

                <select id="{{ $name }}" name="{{ $name }}" class="{{ $cls }}">
                    <option value="">-- Pilih --</option>

                    @foreach ($opsi as $o)
                        <option value="{{ $o }}"
                            @selected(old($name, $sekolah->$name) === $o)>
                            {{ $o }}
                        </option>
                    @endforeach
                </select>

                @error($name)
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <div>
            <label for="alamat" class="block font-medium">Alamat</label>

            <textarea id="alamat" name="alamat" rows="3"
                class="{{ $cls }}">{{ old('alamat', $sekolah->alamat) }}</textarea>

            <div class="flex items-center gap-3 mt-2">
                <button type="button" id="isi-alamat"
                    class="px-4 py-2 bg-gray-700 text-white rounded">
                    Isi alamat dari peta
                </button>
                <span id="status-alamat" class="text-sm text-gray-600"></span>
            </div>

            @error('alamat')
                <p class="text-red-600 text-sm">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="space-y-4">
        <p class="font-medium">
            Klik peta atau geser marker untuk menentukan lokasi sekolah
        </p>

        <div id="map-picker" class="h-80 rounded border"></div>

        <button type="button" id="lokasi-saya"
            class="px-4 py-2 bg-green-600 text-white rounded">
            Gunakan Lokasi Saya
        </button>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="latitude" class="block font-medium">Latitude</label>

                <input type="number" step="any" id="latitude" name="latitude"
                    value="{{ old('latitude', $sekolah->latitude) }}"
                    class="{{ $cls }}">

                @error('latitude')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="longitude" class="block font-medium">Longitude</label>

                <input type="number" step="any" id="longitude" name="longitude"
                    value="{{ old('longitude', $sekolah->longitude) }}"
                    class="{{ $cls }}">

                @error('longitude')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                class="px-5 py-2 bg-blue-700 text-white rounded">
                Simpan
            </button>

            <a href="{{ auth()->user()->isDinas()
                ? route('dinas.sekolah.index')
                : route('dashboard') }}"
               class="px-5 py-2 bg-gray-300 rounded">Batal</a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');

    const sudahAda = latInput.value !== '' && lngInput.value !== '';

    const pusat = sudahAda
        ? [parseFloat(latInput.value), parseFloat(lngInput.value)]
        : [-3.3194, 114.5908];

    const map = L.map('map-picker').setView(pusat, sudahAda ? 16 : 13);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const marker = L.marker(pusat, {
        draggable: true
    }).addTo(map);

    function isiKoordinat(lat, lng) {
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
    }

    map.on('click', (e) => {
        marker.setLatLng(e.latlng);
        isiKoordinat(e.latlng.lat, e.latlng.lng);
    });

    marker.on('dragend', () => {
        const p = marker.getLatLng();
        isiKoordinat(p.lat, p.lng);
    });

    [latInput, lngInput].forEach((el) => {
        el.addEventListener('change', () => {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);

            if (!isNaN(lat) && !isNaN(lng)) {
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], 16);
            }
        });
    });

    document.getElementById('lokasi-saya').addEventListener('click', () => {
        if (!navigator.geolocation) {
            alert('Browser tidak mendukung fitur lokasi.');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                isiKoordinat(lat, lng);
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], 16);

                alert('Lokasi berhasil ditemukan!');
            },
            (error) => {
                if (error.code === 1) {
                    alert('Akses lokasi ditolak. Silakan izinkan akses lokasi di browser.');
                } else {
                    alert('Lokasi tidak dapat ditemukan. Silakan coba lagi.');
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    });

    // Isi alamat dari peta (reverse geocoding Nominatim)
    const tombolAlamat = document.getElementById('isi-alamat');
    const statusAlamat = document.getElementById('status-alamat');
    const alamatInput = document.getElementById('alamat');

    tombolAlamat.addEventListener('click', async () => {
        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);

        if (isNaN(lat) || isNaN(lng)) {
            statusAlamat.textContent = 'Tentukan lokasi pada peta terlebih dahulu.';
            return;
        }

        if (alamatInput.value.trim() !== ''
            && !confirm('Alamat sudah terisi. Timpa dengan alamat dari peta?')) {
            return;
        }

        // Tombol dikunci sementara agar tidak melebihi batas 1 permintaan per detik
        tombolAlamat.disabled = true;
        statusAlamat.textContent = 'Mencari alamat...';

        try {
            const url = 'https://nominatim.openstreetmap.org/reverse'
                + '?format=jsonv2&addressdetails=1&accept-language=id'
                + `&lat=${lat}&lon=${lng}`;

            const respon = await fetch(url);
            if (!respon.ok) throw new Error('HTTP ' + respon.status);

            const data = await respon.json();
            const a = data.address || {};

            // Susun alamat singkat: jalan, kelurahan/desa, kota
            const bagian = [
                a.road,
                a.suburb || a.neighbourhood || a.village,
                a.city || a.town || a.municipality || a.county,
            ].filter(Boolean);

            alamatInput.value = bagian.length > 0
                ? bagian.join(', ')
                : (data.display_name || '');

            statusAlamat.textContent = alamatInput.value
                ? 'Alamat terisi. Periksa dan sesuaikan bila perlu.'
                : 'Alamat tidak ditemukan untuk titik ini.';
        } catch (err) {
            console.error(err);
            statusAlamat.textContent = 'Gagal mengambil alamat. Coba lagi sebentar.';
        }

        setTimeout(() => { tombolAlamat.disabled = false; }, 1200);
    });
</script>
@endpush