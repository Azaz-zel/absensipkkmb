@php
    $statCards = [
        ['label' => 'Total Maba', 'value' => $stats['total_maba'], 'color' => 'bg-gold-600'],
        ['label' => 'Kelas Pagi', 'value' => $stats['total_kelas_pagi'], 'color' => 'bg-amber-500'],
        ['label' => 'Kelas Malam', 'value' => $stats['total_kelas_malam'], 'color' => 'bg-indigo-500'],
        ['label' => 'Total Kehadiran', 'value' => $stats['total_kehadiran'], 'color' => 'bg-emerald-500'],
        ['label' => 'Tidak Hadir', 'value' => $stats['tidak_hadir'], 'color' => 'bg-rose-500'],
        ['label' => 'Sesi Aktif', 'value' => $stats['sesi_aktif'], 'color' => 'bg-sky-500'],
    ];
@endphp

<x-layouts.app title="Dashboard">
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6 lg:gap-5">
        @foreach ($statCards as $s)
            <x-card padded>
                <p class="text-xs font-medium text-slate-500">{{ $s['label'] }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight">{{ $s['value'] }}</p>
                <div class="mt-3 h-1 w-8 rounded-full {{ $s['color'] }}"></div>
            </x-card>
        @endforeach
    </div>

    <div class="mt-6 flex flex-wrap gap-3 lg:mt-8">
        <a href="{{ route('absensi.index') }}" class="rounded-full bg-gold-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Mulai Absensi</a>
        <a href="{{ route('sesi.index') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Kelola Status Sesi</a>
        <a href="{{ route('import.create') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Import Excel</a>
        <a href="{{ route('rekap.mahasiswa') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Lihat Rekap</a>
    </div>

    <div class="mt-6 lg:mt-8">
        <x-card>
            <div class="border-b border-slate-200 px-4 py-3.5 lg:px-6">
                <h2 class="font-serif text-lg font-semibold">Sesi Terbaru</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 lg:px-6">Nama Sesi</th>
                            <th class="px-4 py-2.5 lg:px-6">Tanggal</th>
                            <th class="px-4 py-2.5 lg:px-6">Status</th>
                            <th class="px-4 py-2.5 lg:px-6"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($sesiTerbaru as $sesi)
                            <tr class="transition-colors hover:bg-slate-50/60">
                                <td class="px-4 py-3 font-medium lg:px-6">{{ $sesi->nama_sesi }}</td>
                                <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $sesi->tanggal->translatedFormat('d M Y') }}</td>
                                <td class="px-4 py-3 lg:px-6"><x-status-badge :status="$sesi->statusRingkas()" /></td>
                                <td class="px-4 py-3 text-right lg:px-6">
                                    <a href="{{ route('sesi.show', $sesi) }}" class="text-sm font-medium text-slate-700 hover:underline">Kelola</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-500">Belum ada sesi absensi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-layouts.app>
