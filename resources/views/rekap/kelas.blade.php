<x-layouts.app title="Rekap per Kelas">
    <div class="mb-5 flex gap-4 border-b border-slate-200">
        <a href="{{ route('rekap.mahasiswa') }}"
           class="border-b-2 border-transparent px-1 pb-2 text-sm font-medium text-slate-500 transition hover:text-slate-700">
            Per Mahasiswa
        </a>
        <a href="{{ route('rekap.kelas') }}"
           class="border-b-2 border-gold-600 px-1 pb-2 text-sm font-medium text-gold-700">
            Per Kelas
        </a>
    </div>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-serif text-xl font-semibold text-slate-700">Rekap Kehadiran per Kelas</h2>
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
        <form method="GET" action="{{ route('rekap.kelas') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <select name="sort" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="kelas" @selected($sort === 'kelas')>Urutkan: Kelas (A–AI)</option>
                <option value="persen_desc" @selected($sort === 'persen_desc')>Persentase: Tertinggi ke Terendah</option>
                <option value="persen_asc" @selected($sort === 'persen_asc')>Persentase: Terendah ke Tertinggi</option>
            </select>

            <select name="kelas_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Kelas</option>
                @foreach ($allKelas as $k)
                    <option value="{{ $k->id }}" @selected((string) request('kelas_id') === (string) $k->id)>{{ $k->nama_kelas }}</option>
                @endforeach
            </select>

            <select name="kategori" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Kategori</option>
                <option value="pagi" @selected(request('kategori') === 'pagi')>Pagi</option>
                <option value="malam" @selected(request('kategori') === 'malam')>Malam</option>
            </select>

            <select name="jurusan" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Jurusan</option>
                @foreach ($jurusanList as $j)
                    <option value="{{ $j }}" @selected(request('jurusan') === $j)>{{ $j }}</option>
                @endforeach
            </select>

            <select name="sesi" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Sesi</option>
                @foreach ($sesiList as $s)
                    <option value="{{ $s->id }}" @selected((string) request('sesi') === (string) $s->id)>{{ $s->nama_sesi }}</option>
                @endforeach
            </select>

            <input type="date" name="tanggal" value="{{ request('tanggal') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">

            <div class="sm:col-span-2 lg:col-span-6 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Cari</button>
                <a href="{{ route('rekap.kelas') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </x-card>

    @php
        $activeFilters = collect([
            'Kelas' => $allKelas->firstWhere('id', (int) request('kelas_id'))?->nama_kelas,
            'Kategori' => request('kategori') ? ucfirst(request('kategori')) : null,
            'Jurusan' => request('jurusan'),
            'Sesi' => $sesiList->firstWhere('id', (int) request('sesi'))?->nama_sesi,
            'Tanggal' => request('tanggal') ? \Carbon\Carbon::parse(request('tanggal'))->translatedFormat('d M Y') : null,
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
                        <th class="px-4 py-2.5 lg:px-6">Kelas</th>
                        <th class="px-4 py-2.5 lg:px-6">Kategori</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Jumlah Maba</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Hadir</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Izin</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Sakit</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Alpa</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Belum</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">%</th>
                        @if (auth()->user()->isAdmin())
                            <th class="px-4 py-2.5 text-right lg:px-6">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rekap as $row)
                        <tr class="transition-colors hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-medium lg:px-6">{{ $row['kelas']->nama_kelas }}</td>
                            <td class="px-4 py-3 text-slate-600 capitalize lg:px-6">{{ $row['kelas']->kategori }}</td>
                            <td class="px-4 py-3 text-center text-slate-600 lg:px-6">{{ $row['kelas']->mahasiswa_count }}</td>
                            <td class="px-4 py-3 text-center text-emerald-700 lg:px-6">{{ $row['hadir'] }}</td>
                            <td class="px-4 py-3 text-center text-sky-700 lg:px-6">{{ $row['izin'] }}</td>
                            <td class="px-4 py-3 text-center text-amber-700 lg:px-6">{{ $row['sakit'] }}</td>
                            <td class="px-4 py-3 text-center text-rose-700 lg:px-6">{{ $row['alpa'] }}</td>
                            <td class="px-4 py-3 text-center lg:px-6">
                                @if ($row['belum'] > 0)
                                    <span class="font-medium text-amber-700">{{ $row['belum'] }}</span>
                                @else
                                    <span class="text-emerald-600">&check;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center font-medium lg:px-6">{{ $row['persen'] }}%</td>
                            @if (auth()->user()->isAdmin())
                                <td class="px-4 py-3 text-right lg:px-6">
                                    <a href="{{ route('export.detailKelas', array_merge(['kelas' => $row['kelas']], request()->query())) }}" class="text-sm font-medium text-slate-700 hover:underline">Unduh</a>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-6 text-center text-slate-500">Tidak ada data.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rekap->isNotEmpty())
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50/70 text-sm font-semibold text-slate-800">
                        <tr>
                            <td class="px-4 py-3 lg:px-6" colspan="2">Total {{ $rekap->count() }} kelas</td>
                            <td class="px-4 py-3 text-center lg:px-6">{{ $totalMaba }}</td>
                            <td class="px-4 py-3 text-center text-emerald-700 lg:px-6">{{ $totalHadir }}</td>
                            <td class="px-4 py-3 text-center text-sky-700 lg:px-6">{{ $totalIzin }}</td>
                            <td class="px-4 py-3 text-center text-amber-700 lg:px-6">{{ $totalSakit }}</td>
                            <td class="px-4 py-3 text-center text-rose-700 lg:px-6">{{ $totalAlpa }}</td>
                            <td class="px-4 py-3 text-center lg:px-6">
                                @if ($ringkasan['belum_diabsen'] > 0)
                                    <span class="text-amber-700">{{ $ringkasan['belum_diabsen'] }}</span>
                                @else
                                    <span class="text-emerald-600">&check;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center lg:px-6">
                                {{ $ringkasan['sudah_diabsen'] > 0 ? round($totalHadir / $ringkasan['sudah_diabsen'] * 100, 1) : 0 }}%
                            </td>
                            @if (auth()->user()->isAdmin())
                                <td class="px-4 py-3 lg:px-6"></td>
                            @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-card>
</x-layouts.app>
