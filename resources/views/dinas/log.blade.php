@extends('layouts.app')
@section('title', 'Log Aktivitas')
@section('content')
<h1 class="text-2xl font-bold mb-4">Log Aktivitas Sekolah</h1>

<div class="bg-white rounded shadow overflow-x-auto">
<table class="w-full text-sm">
    <thead class="bg-gray-200 text-left">
        <tr>
            <th class="p-3">Waktu</th>
            <th class="p-3">Pengguna</th>
            <th class="p-3">Aksi</th>
            <th class="p-3">Sekolah</th>
            <th class="p-3">Perubahan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($logs as $l)
            <tr class="border-t align-top">
                <td class="p-3">{{ $l->created_at->format('d-m-Y H:i') }}</td>
                <td class="p-3">
                    {{ $l->user?->name ?? '-' }}
                    @if ($l->user)
                        <span class="text-gray-500">({{ $l->user->roleLabel() }})</span>
                    @endif
                </td>
                <td class="p-3 capitalize">{{ $l->aksi }}</td>
                <td class="p-3">{{ $l->sekolah_nama }}</td>
                <td class="p-3">
                    @forelse ($l->perubahan ?? [] as $kolom => $nilai)
                        <div>
                            <strong>{{ $kolom }}</strong>:
                            {{ $nilai['dari'] ?? '-' }} → {{ $nilai['menjadi'] ?? '-' }}
                        </div>
                    @empty
                        -
                    @endforelse
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="p-4 text-center">Belum ada aktivitas.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection