@php
    $countSemua = $kelasList->count();
@endphp

<x-layouts.app :title="$sesi->nama_sesi">
    <div class="-mt-2 mb-5 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('sesi.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
                </svg>
                Kembali
            </a>
            <span class="text-slate-300">|</span>
            <p class="text-sm text-slate-500">{{ $sesi->tanggal->translatedFormat('d M Y') }}</p>
        </div>

        <form method="POST" action="{{ route('sesi.destroy', $sesi) }}"
              onsubmit="return confirm('Hapus sesi {{ $sesi->nama_sesi }}? Seluruh data absensi pada sesi ini juga akan terhapus.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">Hapus Sesi Ini</button>
        </form>
    </div>

    <x-card padded class="mb-6">
        <p class="mb-4 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Aksi Massal</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-8 4h8a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-emerald-700">Buka Sesi</p>
                </div>
                <form method="POST" action="{{ route('sesi.bulk', $sesi) }}"
                      onsubmit="return confirm('Buka Semua Kelas ({{ $countSemua }} kelas)?');">
                    @csrf
                    <input type="hidden" name="action" value="buka">
                    <input type="hidden" name="scope" value="semua">
                    <button type="submit" @disabled($countSemua === 0)
                            class="w-full rounded-full bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                        Buka Semua Kelas ({{ $countSemua }})
                    </button>
                </form>
            </div>

            <div class="rounded-2xl border border-rose-200 bg-rose-50/40 p-4">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M6 11h12a2 2 0 012 2v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-rose-700">Tutup Sesi</p>
                </div>
                <form method="POST" action="{{ route('sesi.bulk', $sesi) }}"
                      onsubmit="return confirm('Tutup Semua Kelas ({{ $countSemua }} kelas)?');">
                    @csrf
                    <input type="hidden" name="action" value="tutup">
                    <input type="hidden" name="scope" value="semua">
                    <button type="submit" @disabled($countSemua === 0)
                            class="w-full rounded-full bg-rose-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50">
                        Tutup Semua Kelas ({{ $countSemua }})
                    </button>
                </form>
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 lg:px-6">Kelas</th>
                        <th class="px-4 py-2.5 lg:px-6">Kategori</th>
                        <th class="px-4 py-2.5 lg:px-6">Jumlah Maba</th>
                        <th class="px-4 py-2.5 lg:px-6">Status</th>
                        <th class="px-4 py-2.5 text-right lg:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($kelasList as $kelas)
                        @php $status = $kelas->sesiKelas->first()->status ?? 'belum_dibuka'; @endphp
                        <tr class="transition-colors hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-medium lg:px-6">{{ $kelas->nama_kelas }}</td>
                            <td class="px-4 py-3 text-slate-600 capitalize lg:px-6">{{ $kelas->kategori }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $kelas->mahasiswa_count }}</td>
                            <td class="px-4 py-3 lg:px-6"><x-status-badge :status="$status" /></td>
                            <td class="px-4 py-3 text-right lg:px-6">
                                @if ($status === 'belum_dibuka')
                                    <form method="POST" action="{{ route('sesi.openClass', [$sesi, $kelas]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-sm font-medium text-emerald-700 hover:underline">Buka</button>
                                    </form>
                                @elseif ($status === 'dibuka')
                                    <form method="POST" action="{{ route('sesi.closeClass', [$sesi, $kelas]) }}"
                                          onsubmit="return confirm('Tutup absensi kelas {{ $kelas->nama_kelas }}?');" class="inline">
                                        @csrf
                                        <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">Tutup</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('sesi.reopen', [$sesi, $kelas]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-sm font-medium text-slate-700 hover:underline">Buka Kembali</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
