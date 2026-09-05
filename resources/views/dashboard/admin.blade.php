@php
    $statCards = [
        ['label' => 'Total Maba', 'value' => $stats['total_maba'], 'color' => 'bg-gold-600', 'note' => 'terdaftar'],
        ['label' => 'Kelas Pagi', 'value' => $stats['total_kelas_pagi'], 'color' => 'bg-amber-500', 'note' => 'kelas'],
        ['label' => 'Kelas Malam', 'value' => $stats['total_kelas_malam'], 'color' => 'bg-indigo-500', 'note' => 'kelas'],
        ['label' => 'Total Kehadiran', 'value' => $stats['total_kehadiran'], 'color' => 'bg-emerald-500', 'note' => 'tercatat hadir'],
        ['label' => 'Tidak Hadir', 'value' => $stats['tidak_hadir'], 'color' => 'bg-rose-500', 'note' => 'izin, sakit, alpa'],
        ['label' => 'Sesi Aktif', 'value' => $stats['sesi_aktif'], 'color' => 'bg-sky-500', 'note' => 'sedang dibuka'],
        ['label' => 'Kena Poin', 'value' => $stats['kena_poin'], 'color' => 'bg-orange-500', 'note' => 'perlu sanksi'],
        ['label' => 'Sanksi Berat', 'value' => $stats['perlu_sanksi_berat'], 'color' => 'bg-rose-600', 'note' => '10 poin ke atas'],
        ['label' => 'PJ Aktif', 'value' => $stats['total_pj'], 'color' => 'bg-slate-400', 'note' => 'akun penanggung jawab'],
        ['label' => 'Pelanggaran Hari Ini', 'value' => $stats['pelanggaran_hari_ini'], 'color' => 'bg-slate-400', 'note' => 'baru dicatat'],
    ];

    $rincianKehadiran = [
        ['key' => 'hadir', 'label' => 'Hadir', 'bar' => 'bg-emerald-500', 'teks' => 'text-emerald-700'],
        ['key' => 'izin', 'label' => 'Izin', 'bar' => 'bg-sky-500', 'teks' => 'text-sky-700'],
        ['key' => 'sakit', 'label' => 'Sakit', 'bar' => 'bg-amber-500', 'teks' => 'text-amber-700'],
        ['key' => 'alpa', 'label' => 'Alpa', 'bar' => 'bg-rose-500', 'teks' => 'text-rose-700'],
        ['key' => 'belum_diabsen', 'label' => 'Belum Diabsen', 'bar' => 'bg-slate-300', 'teks' => 'text-slate-500'],
    ];
@endphp

<x-layouts.app title="Dashboard">
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-5 lg:gap-5">
        @foreach ($statCards as $s)
            <x-card padded>
                <p class="text-xs font-medium text-slate-500">{{ $s['label'] }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight">{{ $s['value'] }}</p>
                <p class="mt-1 text-[11px] text-slate-400">{{ $s['note'] }}</p>
                <div class="mt-3 h-1 w-8 rounded-full {{ $s['color'] }}"></div>
            </x-card>
        @endforeach
    </div>

    <div class="mt-6 flex flex-wrap gap-3 lg:mt-8">
        <a href="{{ route('absensi.index') }}" class="rounded-full bg-gold-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Mulai Absensi</a>
        <a href="{{ route('sesi.index') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Kelola Status Sesi</a>
        <a href="{{ route('poin.index') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Poin Mahasiswa</a>
        <a href="{{ route('import.create') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Import Excel</a>
        <a href="{{ route('rekap.mahasiswa') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Lihat Rekap</a>
    </div>

    @if ($sesiSorotan && $progresSesi)
        @php $totalProgres = max($progresSesi['total_maba'], 1); @endphp

        <div class="mt-6 lg:mt-8">
            <x-card>
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3.5 lg:px-6">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="font-serif text-lg font-semibold">Progres {{ $sesiSorotan->nama_sesi }}</h2>
                        <x-status-badge :status="$sesiSorotan->statusRingkas()" />
                        <span class="text-sm text-slate-500">{{ $sesiSorotan->tanggal->translatedFormat('d M Y') }}</span>
                    </div>
                    <a href="{{ route('sesi.show', $sesiSorotan) }}" class="text-sm font-medium text-gold-700 hover:underline">Kelola sesi &rarr;</a>
                </div>

                <div class="px-4 py-4 lg:px-6 lg:py-5">
                    <div class="mb-4 flex flex-wrap items-baseline gap-x-6 gap-y-2">
                        <p class="text-sm text-slate-600">
                            Sudah diabsen
                            <span class="text-lg font-semibold text-slate-900">{{ $progresSesi['sudah_diabsen'] }}</span>
                            dari {{ $progresSesi['total_maba'] }} maba
                        </p>
                        <p class="text-sm text-slate-500">
                            Kelas dibuka <strong class="text-emerald-700">{{ $sesiSorotan->dibuka_count }}</strong> &middot;
                            ditutup <strong class="text-slate-700">{{ $sesiSorotan->ditutup_count }}</strong> &middot;
                            belum dibuka <strong class="text-slate-700">{{ $sesiSorotan->belum_dibuka_count }}</strong>
                        </p>
                    </div>

                    <div class="mb-4 flex h-3 overflow-hidden rounded-full bg-slate-100">
                        @foreach ($rincianKehadiran as $r)
                            @php $lebar = round($progresSesi[$r['key']] / $totalProgres * 100, 2); @endphp
                            @if ($lebar > 0)
                                <div class="h-full {{ $r['bar'] }}" style="width: {{ $lebar }}%" title="{{ $r['label'] }}: {{ $progresSesi[$r['key']] }}"></div>
                            @endif
                        @endforeach
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        @foreach ($rincianKehadiran as $r)
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full {{ $r['bar'] }}"></span>
                                    <p class="text-xs font-medium {{ $r['teks'] }}">{{ $r['label'] }}</p>
                                </div>
                                <p class="mt-1 text-xl font-semibold tracking-tight text-slate-900">{{ $progresSesi[$r['key']] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-card>
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-5 lg:mt-8 lg:grid-cols-2">
        <x-sebaran-sanksi :sebaran="$sebaran" :total="$stats['total_maba']" />
        <x-pelanggaran-terbaru :items="$pelanggaranTerbaru" />
    </div>

    <div class="mt-6 lg:mt-8">
        <x-tabel-poin-tertinggi :items="$topPoin" />
    </div>

    <div class="mt-6 lg:mt-8">
        <x-card>
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3.5 lg:px-6">
                <h2 class="font-serif text-lg font-semibold">Sesi Terbaru</h2>
                <a href="{{ route('sesi.index') }}" class="text-sm font-medium text-gold-700 hover:underline">Semua sesi &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 lg:px-6">Nama Sesi</th>
                            <th class="px-4 py-2.5 lg:px-6">Tanggal</th>
                            <th class="px-4 py-2.5 lg:px-6">Status</th>
                            <th class="px-4 py-2.5 text-center lg:px-6">Dibuka</th>
                            <th class="px-4 py-2.5 text-center lg:px-6">Ditutup</th>
                            <th class="px-4 py-2.5 text-center lg:px-6">Belum</th>
                            <th class="px-4 py-2.5 lg:px-6"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($sesiTerbaru as $sesi)
                            <tr class="transition-colors hover:bg-slate-50/60">
                                <td class="px-4 py-3 font-medium lg:px-6">{{ $sesi->nama_sesi }}</td>
                                <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $sesi->tanggal->translatedFormat('d M Y') }}</td>
                                <td class="px-4 py-3 lg:px-6"><x-status-badge :status="$sesi->statusRingkas()" /></td>
                                <td class="px-4 py-3 text-center text-emerald-700 lg:px-6">{{ $sesi->dibuka_count }}</td>
                                <td class="px-4 py-3 text-center text-slate-600 lg:px-6">{{ $sesi->ditutup_count }}</td>
                                <td class="px-4 py-3 text-center text-slate-400 lg:px-6">{{ $sesi->belum_dibuka_count }}</td>
                                <td class="px-4 py-3 text-right lg:px-6">
                                    <a href="{{ route('sesi.show', $sesi) }}" class="text-sm font-medium text-slate-700 hover:underline">Kelola</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada sesi absensi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-layouts.app>
