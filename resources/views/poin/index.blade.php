<x-layouts.app title="Poin Mahasiswa">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-serif text-xl font-semibold text-slate-700">Poin Pelanggaran Mahasiswa</h2>
        <a href="{{ route('export.poin', request()->query()) }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
            Export Excel
        </a>
    </div>

    <x-card padded class="mb-5">
        <form method="GET" action="{{ route('poin.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama / NIM"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">

            <select name="sort" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="nama" @selected($sort === 'nama')>Urutkan: Nama (A-Z)</option>
                <option value="poin_desc" @selected($sort === 'poin_desc')>Total Poin: Tertinggi ke Terendah</option>
                <option value="poin_asc" @selected($sort === 'poin_asc')>Total Poin: Terendah ke Tertinggi</option>
            </select>

            <select name="kelas_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Kelas</option>
                @foreach ($kelasList as $k)
                    <option value="{{ $k->id }}" @selected((string) request('kelas_id') === (string) $k->id)>{{ $k->nama_kelas }} ({{ $k->kategori }})</option>
                @endforeach
            </select>

            <select name="sesi" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Sesi</option>
                @foreach ($sesiList as $s)
                    <option value="{{ $s->id }}" @selected((string) request('sesi') === (string) $s->id)>{{ $s->nama_sesi }}</option>
                @endforeach
            </select>

            <div class="sm:col-span-2 lg:col-span-4 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Cari</button>
                <a href="{{ route('poin.index') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </x-card>

    @php
        $activeFilters = collect([
            'Cari' => request('q'),
            'Kelas' => $kelasList->firstWhere('id', (int) request('kelas_id'))?->nama_kelas,
            'Sesi' => $sesiList->firstWhere('id', (int) request('sesi'))?->nama_sesi,
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

    @if (request('sesi'))
        <p class="mb-4 rounded-2xl bg-sky-50 px-4 py-3 text-sm text-sky-700 ring-1 ring-inset ring-sky-600/20">
            Filter sesi cuma memengaruhi <strong>poin absensi</strong>. Poin pelanggaran dicatat manual dan tidak terikat sesi, jadi tetap dihitung penuh.
        </p>
    @endif

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 lg:px-6">Nama</th>
                        <th class="px-4 py-2.5 lg:px-6">NIM</th>
                        <th class="px-4 py-2.5 lg:px-6">Kelas</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Poin Absensi</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Poin Pelanggaran</th>
                        <th class="px-4 py-2.5 text-center lg:px-6">Total Poin</th>
                        <th class="px-4 py-2.5 lg:px-6">Sanksi</th>
                        <th class="px-4 py-2.5 text-right lg:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($mahasiswa as $m)
                        <tr class="transition-colors hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-medium lg:px-6">{{ $m->nama }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->nim }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->kelas->nama_kelas }}</td>
                            <td class="px-4 py-3 text-center text-slate-600 lg:px-6">{{ $m->poin() }}</td>
                            <td class="px-4 py-3 text-center text-rose-700 lg:px-6">{{ $m->poinPelanggaran() }}</td>
                            <td class="px-4 py-3 text-center text-base font-semibold lg:px-6">{{ $m->totalPoin() }}</td>
                            <td class="px-4 py-3 lg:px-6"><x-sanksi-badge :sanksi="$m->sanksi()" /></td>
                            <td class="px-4 py-3 text-right lg:px-6">
                                <button type="button" onclick="document.getElementById('dialog-poin-{{ $m->id }}').showModal()"
                                        class="text-sm font-medium text-slate-700 hover:underline">
                                    Catat Pelanggaran
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-slate-500">Tidak ada data.</td>
                        </tr>
                    @endforelse
                </tbody>
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

    @foreach ($mahasiswa as $m)
        <dialog id="dialog-poin-{{ $m->id }}" class="fixed left-1/2 top-1/2 w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-6 shadow-lg backdrop:bg-slate-900/60">
            <h3 class="font-serif text-xl font-semibold text-slate-900">{{ $m->nama }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ $m->nim }} &middot; Kelas {{ $m->kelas->nama_kelas }}</p>

            <div class="mt-4 flex flex-wrap items-center gap-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                <span class="text-slate-600">Absensi: <strong>{{ $m->poin() }}</strong></span>
                <span class="text-slate-600">Pelanggaran: <strong class="text-rose-700">{{ $m->poinPelanggaran() }}</strong></span>
                <span class="text-slate-900">Total: <strong>{{ $m->totalPoin() }}</strong></span>
                <x-sanksi-badge :sanksi="$m->sanksi()" />
            </div>

            <form method="POST" action="{{ route('poin.store', $m) }}" class="mt-5">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_110px]">
                    <div>
                        <label for="jenis-{{ $m->id }}" class="mb-1 block text-sm font-medium text-slate-700">Jenis Pelanggaran</label>
                        <input id="jenis-{{ $m->id }}" name="jenis" type="text" required maxlength="255" placeholder="mis. Telat apel"
                               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                    </div>
                    <div>
                        <label for="poin-{{ $m->id }}" class="mb-1 block text-sm font-medium text-slate-700">Poin <span class="font-normal text-slate-400">(maks 20)</span></label>
                        <input id="poin-{{ $m->id }}" name="poin" type="text" inputmode="numeric" pattern="[0-9]*"
                               maxlength="2" data-poin-maks="20" required placeholder="3"
                               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Catat Pelanggaran</button>
                    <button type="button" onclick="document.getElementById('dialog-poin-{{ $m->id }}').close()"
                            class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Tutup
                    </button>
                </div>
            </form>

            <div class="mt-6 border-t border-slate-200 pt-4">
                <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Riwayat Pelanggaran</p>
                @forelse ($riwayat->get($m->id, collect()) as $p)
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 py-2.5 last:border-0">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $p->jenis }}</p>
                            <p class="text-xs text-slate-500">
                                +{{ $p->poin }} poin &middot; {{ $p->created_at->translatedFormat('d M Y H:i') }}
                                @if ($p->pencatat)
                                    &middot; {{ $p->pencatat->name }}
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('pelanggaran.destroy', $p) }}"
                              onsubmit="return confirm('Hapus catatan pelanggaran ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="shrink-0 text-xs font-medium text-rose-600 hover:underline">Hapus</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada pelanggaran tercatat.</p>
                @endforelse
            </div>
        </dialog>
    @endforeach

    <script>
        // Poin pelanggaran dibatasi 1-20 (20 = cap merah). Server tetap memvalidasi ulang.
        document.addEventListener('input', function (e) {
            var input = e.target;
            if (!input.matches || !input.matches('input[data-poin-maks]')) {
                return;
            }

            var maks = parseInt(input.dataset.poinMaks, 10);
            var angka = input.value.replace(/[^0-9]/g, '');

            if (angka !== '' && parseInt(angka, 10) > maks) {
                angka = String(maks);
            }

            if (input.value !== angka) {
                input.value = angka;
            }
        });
    </script>
</x-layouts.app>
