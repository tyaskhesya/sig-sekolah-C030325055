<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Sekolah;
use Illuminate\Support\Facades\Auth;

class SekolahObserver
{
    public function created(Sekolah $sekolah): void
    {
        $this->catat('tambah', $sekolah);
    }

    public function updated(Sekolah $sekolah): void
    {
        $perubahan = [];

        foreach ($sekolah->getChanges() as $kolom => $baru) {
            if ($kolom === 'updated_at') {
                continue;
            }
            $perubahan[$kolom] = [
                'dari' => $sekolah->getOriginal($kolom),
                'menjadi' => $baru,
            ];
        }

        if ($perubahan) {
            $this->catat('ubah', $sekolah, $perubahan);
        }
    }

    public function deleted(Sekolah $sekolah): void
    {
        $this->catat('hapus', $sekolah);
    }

    private function catat(string $aksi, Sekolah $sekolah, ?array $perubahan = null): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'aksi' => $aksi,
            'sekolah_id' => $sekolah->id,
            'sekolah_nama' => $sekolah->nama,
            'perubahan' => $perubahan,
        ]);
    }
}