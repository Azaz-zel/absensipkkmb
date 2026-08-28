<x-layouts.app title="Absensi">
    @forelse ($sesiList as $sesi)
        @php $statusForThisSesi = $statusBySesi->get($sesi->id, collect()); @endphp
        <div class="mb-8">
            <div class="mb-3 flex items-baseline justify-between">
                <h2 class="font-serif text-xl font-semibold text-slate-900">{{ $sesi->nama_sesi }}</h2>
                <p class="text-sm text-slate-500">{{ $sesi->tanggal->translatedFormat('d M Y') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4">
                @forelse ($kelasSaya as $kelas)
                    @php $status = $statusForThisSesi->get($kelas->id, 'belum_dibuka'); @endphp
                    <x-card padded class="flex flex-col gap-4 transition hover:shadow-md hover:border-slate-300 sm:flex-row sm:items-center sm:justify-between">
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
                            <x-status-badge :status="$status" />
                            <a href="{{ route('absensi.show', [$sesi, $kelas]) }}"
                               class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">
                                {{ $status === 'dibuka' ? 'Isi Absensi' : 'Lihat Absensi' }}
                            </a>
                        </div>
                    </x-card>
                @empty
                    <p class="text-sm text-slate-500">Anda belum ditugaskan ke kelas manapun. Hubungi Admin.</p>
                @endforelse
            </div>
        </div>
    @empty
        <p class="text-sm text-slate-500">Belum ada sesi absensi.</p>
    @endforelse
</x-layouts.app>
