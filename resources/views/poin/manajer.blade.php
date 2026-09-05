<x-layouts.app title="Poin Mahasiswa">
    <div class="mb-5">
        <h2 class="font-serif text-xl font-semibold text-slate-700">Cari Mahasiswa</h2>
        <p class="mt-1 text-sm text-slate-500">Masukkan NIM (atau nama) mahasiswa untuk mencatat poin pelanggaran.</p>
    </div>

    <x-card padded class="mb-5">
        <form method="GET" action="{{ route('poin.index') }}" class="flex flex-col gap-3 sm:flex-row">
            <input type="text" name="q" value="{{ $term }}" placeholder="Cari NIM / nama mahasiswa" autofocus
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
            <div class="flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Cari</button>
                @if ($term !== '')
                    <a href="{{ route('poin.index') }}" class="rounded-full border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Reset</a>
                @endif
            </div>
        </form>
    </x-card>

    @if ($term === '')
        <x-card padded>
            <p class="py-8 text-center text-sm text-slate-500">Belum ada pencarian. Ketik NIM mahasiswa di kolom di atas.</p>
        </x-card>
    @elseif ($mahasiswa->isEmpty())
        <x-card padded>
            <p class="py-8 text-center text-sm text-slate-500">Mahasiswa dengan NIM/nama &ldquo;{{ $term }}&rdquo; tidak ditemukan.</p>
        </x-card>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($mahasiswa as $m)
                <x-card padded>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-serif text-lg font-semibold text-slate-900">{{ $m->nama }}</p>
                            <p class="mt-0.5 text-sm text-slate-500">
                                {{ $m->nim }} &middot; {{ $m->jurusan }} &middot; Kelas {{ $m->kelas->nama_kelas }}
                            </p>
                        </div>
                        <x-sanksi-badge :sanksi="$m->sanksi()" />
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-medium uppercase tracking-[0.15em] text-slate-500">Poin Kehadiran</p>
                            <p class="mt-1 text-2xl font-semibold tracking-tight">{{ $m->poin() }}</p>
                        </div>
                        <div class="rounded-2xl bg-rose-50/60 px-4 py-3">
                            <p class="text-[11px] font-medium uppercase tracking-[0.15em] text-rose-600">Poin Pelanggaran</p>
                            <p class="mt-1 text-2xl font-semibold tracking-tight text-rose-700">{{ $m->poinPelanggaran() }}</p>
                        </div>
                        <div class="rounded-2xl bg-gold-50 px-4 py-3">
                            <p class="text-[11px] font-medium uppercase tracking-[0.15em] text-gold-700">Total Poin</p>
                            <p class="mt-1 text-2xl font-semibold tracking-tight text-gold-800">{{ $m->totalPoin() }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('poin.store', $m) }}" class="mt-5 border-t border-slate-200 pt-4">
                        @csrf
                        <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Catat Pelanggaran</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_110px_auto]">
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
                            <div class="flex items-end">
                                <button type="submit" class="w-full rounded-full bg-gold-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700 sm:w-auto">
                                    Tambahkan
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Poin yang dimasukkan akan ditambahkan ke total poin mahasiswa ini.</p>
                    </form>
                </x-card>
            @endforeach
        </div>
    @endif

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
