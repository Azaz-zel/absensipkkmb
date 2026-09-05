<x-layouts.app title="Rekap per Mahasiswa">
    <div class="mb-5 flex gap-4 border-b border-slate-200">
        <a href="{{ route('rekap.mahasiswa') }}"
           class="border-b-2 border-gold-600 px-1 pb-2 text-sm font-medium text-gold-700">
            Per Mahasiswa
        </a>
        <a href="{{ route('rekap.kelas') }}"
           class="border-b-2 border-transparent px-1 pb-2 text-sm font-medium text-slate-500 transition hover:text-slate-700">
            Per Kelas
        </a>
    </div>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-serif text-xl font-semibold text-slate-700">Rekap Kehadiran per Mahasiswa</h2>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('export.rekap', request()->query()) }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Export Excel
            </a>
        @endif
    </div>

    <div class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-5">
        <x-card padded>
            <p class="text-xs font-medium text-slate-500">Total Kehadiran</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-emerald-700">{{ $totalHadir }}</p>
        </x-card>
        <x-card padded>
            <p class="text-xs font-medium text-slate-500">Tidak Hadir</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-rose-700">{{ $totalTidakHadir }}</p>
            <p class="mt-1 text-xs text-slate-400">Izin {{ $totalIzin }} &middot; Sakit {{ $totalSakit }} &middot; Alpa {{ $totalAlpa }}</p>
        </x-card>
        <x-ringkasan-pengisian :ringkasan="$ringkasan" :sesi-terpilih="$sesiList->firstWhere('id', request()->integer('sesi'))" class="col-span-2" />
    </div>

    <x-card padded class="mb-5">
        <form method="GET" action="{{ route('rekap.mahasiswa') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama / NIM"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">

            <select name="sort" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="nama" @selected($sort === 'nama')>Urutkan: Nama (A-Z)</option>
                <option value="persen_desc" @selected($sort === 'persen_desc')>Persentase: Tertinggi ke Terendah</option>
                <option value="persen_asc" @selected($sort === 'persen_asc')>Persentase: Terendah ke Tertinggi</option>
            </select>

            <select name="jurusan" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Jurusan</option>
                @foreach ($jurusanList as $j)
                    <option value="{{ $j }}" @selected(request('jurusan') === $j)>{{ $j }}</option>
                @endforeach
            </select>

            <select name="kelas_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Kelas</option>
                @foreach ($kelasList as $k)
                    <option value="{{ $k->id }}" @selected((string) request('kelas_id') === (string) $k->id)>{{ $k->nama_kelas }} ({{ $k->kategori }})</option>
                @endforeach
            </select>

            <select name="kategori" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Kategori</option>
                <option value="pagi" @selected(request('kategori') === 'pagi')>Pagi</option>
                <option value="malam" @selected(request('kategori') === 'malam')>Malam</option>
            </select>

            <select name="sesi" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Sesi</option>
                @foreach ($sesiList as $s)
                    <option value="{{ $s->id }}" @selected((string) request('sesi') === (string) $s->id)>{{ $s->nama_sesi }}</option>
                @endforeach
            </select>

            <select name="kehadiran" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Keterangan</option>
                <option value="hadir" @selected($kehadiran === 'hadir')>Hadir</option>
                <option value="tidak_hadir" @selected($kehadiran === 'tidak_hadir')>Tidak Hadir (Izin/Sakit/Alpa)</option>
            </select>

            <div class="sm:col-span-2 lg:col-span-4 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Cari</button>
                <a href="{{ route('rekap.mahasiswa') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </x-card>

    @php
        $activeFilters = collect([
            'Cari' => request('q'),
            'Jurusan' => request('jurusan'),
            'Kelas' => $kelasList->firstWhere('id', (int) request('kelas_id'))?->nama_kelas,
            'Kategori' => request('kategori') ? ucfirst(request('kategori')) : null,
            'Sesi' => $sesiList->firstWhere('id', (int) request('sesi'))?->nama_sesi,
            'Kehadiran' => match ($kehadiran) {
                'hadir' => 'Hadir',
                'tidak_hadir' => 'Tidak Hadir',
                default => null,
            },
        ])->filter();
    @endphp

    @if ($activeFilters->isNotEmpty())
        <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
            <span class="text-[11px] font-medium uppercase tracking-[0.2em] text-slate-600">Filter aktif:</span>
            @foreach ($activeFilters as $label => $value)
                <span class="inline-flex items-center rounded-full bg-gold-50 px-3 py-1 text-xs font-medium text-gold-700 ring-1 ring-inset ring-gold-200">
                    {{ $label }}: {{ $value }}
                </span>
            @endforeach
        </div>
    @endif

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 lg:px-6">Nama</th>
                        <th class="px-4 py-2.5 lg:px-6">NIM</th>
                        <th class="px-4 py-2.5 lg:px-6">Kelas</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Hadir</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Izin</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Sakit</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Alpa</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">%</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Poin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($mahasiswa as $m)
                        @php
                            $total = $m->hadir_count + $m->izin_count + $m->sakit_count + $m->alpa_count;
                            $persen = $total > 0 ? round($m->hadir_count / $total * 100, 1) : 0;
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-medium lg:px-6">
                                <a href="{{ route('rekap.mahasiswa.detail', $m) }}{{ request()->query() ? '?'.http_build_query(request()->query()) : '' }}"
                                   class="hover:text-gold-700 hover:underline">{{ $m->nama }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->nim }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->kelas->nama_kelas }}</td>
                            <td class="px-4 py-3 text-center text-emerald-700 lg:px-6">{{ $m->hadir_count }}</td>
                            <td class="px-4 py-3 text-center text-sky-700 lg:px-6">{{ $m->izin_count }}</td>
                            <td class="px-4 py-3 text-center text-amber-700 lg:px-6">{{ $m->sakit_count }}</td>
                            <td class="px-4 py-3 text-center text-rose-700 lg:px-6">{{ $m->alpa_count }}</td>
                            <td class="px-4 py-3 text-center font-medium lg:px-6">{{ $persen }}%</td>
                            <td class="px-4 py-3 text-center font-medium lg:px-6">{{ $m->poin() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center text-slate-500">Tidak ada data.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($mahasiswa->count() > 0)
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50/70 text-sm font-semibold text-slate-800">
                        <tr>
                            <td class="px-4 py-3 lg:px-6" colspan="3">Total{{ $filteredByKelas ? '' : ' (semua hasil filter)' }}</td>
                            <td class="px-4 py-3 text-center text-emerald-700 lg:px-6">{{ $totalHadir }}</td>
                            <td class="px-4 py-3 text-center lg:px-6">{{ $totalIzin }}</td>
                            <td class="px-4 py-3 text-center lg:px-6">{{ $totalSakit }}</td>
                            <td class="px-4 py-3 text-center text-rose-700 lg:px-6">{{ $totalAlpa }}</td>
                            <td class="px-4 py-3 text-center lg:px-6">
                                {{ $ringkasan['sudah_diabsen'] > 0 ? round($totalHadir / $ringkasan['sudah_diabsen'] * 100, 1) : 0 }}%
                            </td>
                            <td class="px-4 py-3 text-center lg:px-6">&mdash;</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($filteredByKelas)
            <div class="border-t border-slate-200 px-4 py-3 text-sm text-slate-500 lg:px-6">
                Menampilkan {{ $mahasiswa->count() }} mahasiswa
            </div>
        @else
            <div class="border-t border-slate-200 px-4 py-3 lg:px-6">
                {{ $mahasiswa->links() }}
            </div>
        @endif
    </x-card>
</x-layouts.app>
