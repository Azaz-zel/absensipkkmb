@props(['items'])

<x-card>
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3.5 lg:px-6">
        <h2 class="font-serif text-lg font-semibold">Poin Tertinggi</h2>
        <a href="{{ route('poin.index') }}" class="text-sm font-medium text-gold-700 hover:underline">Buka rekap poin &rarr;</a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                <tr>
                    <th class="px-4 py-2.5 lg:px-6">Nama</th>
                    <th class="px-4 py-2.5 lg:px-6">NIM</th>
                    <th class="px-4 py-2.5 lg:px-6">Kelas</th>
                    <th class="px-4 py-2.5 text-center lg:px-6">Absensi</th>
                    <th class="px-4 py-2.5 text-center lg:px-6">Pelanggaran</th>
                    <th class="px-4 py-2.5 text-center lg:px-6">Total</th>
                    <th class="px-4 py-2.5 lg:px-6">Sanksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $m)
                    <tr class="transition-colors hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-medium lg:px-6">{{ $m->nama }}</td>
                        <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->nim }}</td>
                        <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->kelas?->nama_kelas ?? '-' }}</td>
                        <td class="px-4 py-3 text-center text-slate-600 lg:px-6">{{ $m->poin() }}</td>
                        <td class="px-4 py-3 text-center text-rose-700 lg:px-6">{{ $m->poinPelanggaran() }}</td>
                        <td class="px-4 py-3 text-center text-base font-semibold lg:px-6">{{ $m->totalPoin() }}</td>
                        <td class="px-4 py-3 lg:px-6"><x-sanksi-badge :sanksi="$m->sanksi()" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                            Belum ada maba yang kena poin. Semua masih bersih.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
