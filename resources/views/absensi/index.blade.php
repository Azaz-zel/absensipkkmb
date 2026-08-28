<x-layouts.app title="Absensi">
    <x-card padded class="mb-6 max-w-sm">
        <form method="GET" action="{{ route('absensi.index') }}">
            <label for="sesi" class="mb-1 block text-sm font-medium text-slate-700">Pilih Sesi</label>
            <select id="sesi" name="sesi" onchange="this.form.submit()"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">— Pilih Sesi —</option>
                @foreach ($sesiList as $s)
                    <option value="{{ $s->id }}" @selected($sesi && $sesi->id === $s->id)>
                        {{ $s->nama_sesi }} ({{ $s->tanggal->translatedFormat('d M Y') }})
                    </option>
                @endforeach
            </select>
        </form>
    </x-card>

    @if (! $sesi)
        <p class="text-sm text-slate-500">Pilih sesi absensi terlebih dahulu untuk melihat daftar kelas.</p>
    @else
        <div class="mb-4 flex items-center gap-2 text-sm">
            <span class="text-slate-500">Menampilkan sesi:</span>
            <span class="font-semibold text-slate-900">{{ $sesi->nama_sesi }}</span>
            <span class="text-slate-400">—</span>
            <span class="text-slate-600">{{ $sesi->tanggal->translatedFormat('d M Y') }}</span>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
            @forelse ($kelasList as $kelas)
                @php $status = $statusMap->get($kelas->id, 'belum_dibuka'); @endphp
                <x-card padded class="transition hover:shadow-md hover:border-slate-300">
                    <div class="flex items-center justify-between">
                        <p class="text-lg font-semibold">Kelas {{ $kelas->nama_kelas }}</p>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium uppercase text-slate-500">{{ $kelas->kategori }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $kelas->mahasiswa_count }} mahasiswa</p>
                    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
                        <x-status-badge :status="$status" />
                        <a href="{{ route('absensi.show', [$sesi, $kelas]) }}"
                           class="rounded-full bg-gold-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-gold-700">
                            Isi Absensi
                        </a>
                    </div>
                </x-card>
            @empty
                <p class="text-sm text-slate-500">Tidak ada kelas untuk ditampilkan.</p>
            @endforelse
        </div>
    @endif
</x-layouts.app>
