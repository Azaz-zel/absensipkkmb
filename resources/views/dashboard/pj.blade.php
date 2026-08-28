<x-layouts.app title="Dashboard">
    <div class="mb-5 flex items-center justify-between">
        <h2 class="font-serif text-xl font-semibold text-slate-700">Kelas Anda</h2>
        @if ($sesiAktif)
            <p class="text-sm text-slate-500">
                Sesi terbaru: <span class="font-medium text-slate-700">{{ $sesiAktif->nama_sesi }}</span>
                — {{ $sesiAktif->tanggal->translatedFormat('d M Y') }}
            </p>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4">
        @forelse ($kelasSaya as $kelas)
            @php
                $status = $statusPerKelas->firstWhere('kelas_id', $kelas->id);
                $statusValue = $status->status ?? 'belum_dibuka';
            @endphp
            <x-card padded class="flex flex-col gap-4 transition hover:shadow-md sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gold-50 font-serif text-lg font-semibold text-gold-700">
                        {{ $kelas->nama_kelas }}
                    </div>
                    <div>
                        <p class="text-lg font-semibold">Kelas {{ $kelas->nama_kelas }}</p>
                        <p class="text-sm text-slate-500 capitalize">{{ $kelas->kategori }} — {{ $kelas->mahasiswa_count }} mahasiswa</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 sm:pl-4">
                    <x-status-badge :status="$statusValue" />

                    @if ($sesiAktif)
                        <a href="{{ route('absensi.index') }}"
                           class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">
                            {{ $statusValue === 'dibuka' ? 'Isi Absensi' : 'Lihat Absensi' }}
                        </a>
                    @endif
                </div>
            </x-card>
        @empty
            <p class="text-sm text-slate-500">Anda belum ditugaskan ke kelas manapun. Hubungi Admin.</p>
        @endforelse
    </div>
</x-layouts.app>
