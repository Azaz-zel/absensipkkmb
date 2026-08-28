@php
    $sesiClosed = $sesiKelas->status !== 'dibuka';
    $isAdmin = auth()->user()->isAdmin();
    $canEdit = ! $sesiClosed || $isAdmin;
    $hasData = $absensiMap->isNotEmpty();
    $editMode = $canEdit && $editRequested;

    $statusLabels = ['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'];
@endphp

<x-layouts.app :title="'Absensi Kelas '.$kelas->nama_kelas.' - '.$sesi->nama_sesi">
    <a href="{{ route('absensi.index', ['sesi' => $sesi->id]) }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
        </svg>
        Kembali
    </a>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">{{ $sesi->nama_sesi }} — {{ $sesi->tanggal->translatedFormat('d M Y') }}</p>
            <h2 class="font-serif text-2xl font-semibold tracking-tight">Kelas {{ $kelas->nama_kelas }} <span class="font-normal text-slate-400">({{ $kelas->kategori }})</span></h2>
        </div>
        <x-status-badge :status="$sesiKelas->status" />
    </div>

    @if ($sesiClosed)
        @if ($isAdmin)
            <div class="mb-5 rounded-2xl bg-sky-50 px-4 py-3 text-sm font-medium text-sky-700 ring-1 ring-inset ring-sky-600/20">
                Sesi absensi untuk kelas ini sedang tidak dibuka. Sebagai Admin, Anda tetap dapat mengubah data absensi.
            </div>
        @else
            <div class="mb-5 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                Sesi absensi untuk kelas ini sedang tidak dibuka. Data hanya dapat dilihat, tidak dapat diubah.
            </div>
        @endif
    @endif

    <form method="GET" action="{{ route('absensi.show', [$sesi, $kelas]) }}" class="mb-5 flex items-center gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama / NIM"
               class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
        @if ($editMode)
            <input type="hidden" name="edit" value="1">
        @endif
        <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Cari</button>
        @if (request('q'))
            <a href="{{ route('absensi.show', [$sesi, $kelas]) }}{{ $editMode ? '?edit=1' : '' }}"
               class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Reset
            </a>
        @endif
    </form>

    @if ($editMode)
        <form method="POST" action="{{ route('absensi.update', [$sesi, $kelas]) }}">
            @csrf
            @method('PUT')

            <x-card>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5 lg:px-6">Nama</th>
                                <th class="px-4 py-2.5 lg:px-6">NIM</th>
                                <th class="px-4 py-2.5 lg:px-6">Jurusan</th>
                                <th class="px-4 py-2.5 lg:px-6">Status Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($mahasiswa as $m)
                                @php $current = $absensiMap->get($m->id); @endphp
                                <tr class="transition-colors hover:bg-slate-50/60">
                                    <td class="px-4 py-3 font-medium lg:px-6">{{ $m->nama }}</td>
                                    <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->nim }}</td>
                                    <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->jurusan }}</td>
                                    <td class="px-4 py-3 lg:px-6">
                                        <select name="absensi[{{ $m->id }}]"
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                                            <option value="" @selected(! $current)>— Belum diisi —</option>
                                            @foreach ($statusLabels as $value => $label)
                                                <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-500">Tidak ada mahasiswa di kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <div class="mt-5 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">
                    Simpan Absensi
                </button>
                <a href="{{ route('absensi.show', [$sesi, $kelas]) }}{{ request('q') ? '?q='.request('q') : '' }}"
                   class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Batal
                </a>
            </div>
        </form>
    @else
        <x-card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 lg:px-6">Nama</th>
                            <th class="px-4 py-2.5 lg:px-6">NIM</th>
                            <th class="px-4 py-2.5 lg:px-6">Jurusan</th>
                            <th class="px-4 py-2.5 lg:px-6">Status Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($mahasiswa as $m)
                            @php $current = $absensiMap->get($m->id); @endphp
                            <tr class="transition-colors hover:bg-slate-50/60">
                                <td class="px-4 py-3 font-medium lg:px-6">{{ $m->nama }}</td>
                                <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->nim }}</td>
                                <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->jurusan }}</td>
                                <td class="px-4 py-3 lg:px-6">
                                    @if ($current)
                                        <x-status-badge :status="$current" />
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-300">
                                            Belum diisi
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-500">Tidak ada mahasiswa di kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        @if ($canEdit)
            <a href="{{ route('absensi.show', [$sesi, $kelas]) }}?edit=1{{ request('q') ? '&q='.request('q') : '' }}"
               class="mt-5 inline-block rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">
                {{ $hasData ? 'Edit Kehadiran' : 'Mulai Absensi' }}
            </a>
        @endif
    @endif
</x-layouts.app>
