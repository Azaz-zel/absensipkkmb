@php
    $isAdmin = auth()->user()->isAdmin();
    $statusLabels = ['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'];
@endphp

<x-layouts.app :title="'Riwayat Kehadiran — '.$mahasiswa->nama">
    <a href="{{ route('rekap.mahasiswa') }}{{ request()->query() ? '?'.http_build_query(request()->query()) : '' }}"
       class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
        </svg>
        Kembali ke Rekap
    </a>

    <div class="mb-5">
        <h2 class="font-serif text-2xl font-semibold tracking-tight">{{ $mahasiswa->nama }}</h2>
        <p class="text-sm text-slate-500">{{ $mahasiswa->nim }} — {{ $mahasiswa->jurusan }} — Kelas {{ $mahasiswa->kelas->nama_kelas }}</p>
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 lg:px-6">Sesi</th>
                        <th class="px-4 py-2.5 lg:px-6">Tanggal</th>
                        <th class="px-4 py-2.5 lg:px-6">Status</th>
                        @if ($isAdmin)
                            <th class="px-4 py-2.5 text-right lg:px-6">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($riwayat as $r)
                        <tr class="transition-colors hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-medium lg:px-6">{{ $r->sesiAbsensi?->nama_sesi ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $r->sesiAbsensi?->tanggal?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 lg:px-6">
                                @unless ($isAdmin)
                                    <x-status-badge :status="$r->status" />
                                @endunless

                                @if ($isAdmin)
                                    <form method="POST" action="{{ route('absensi.record.update', $r) }}" class="inline-flex items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        <select name="status"
                                                class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                                            @foreach ($statusLabels as $value => $label)
                                                <option value="{{ $value }}" @selected($r->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded-full border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                                            Simpan
                                        </button>
                                    </form>
                                @endif
                            </td>
                            @if ($isAdmin)
                                <td class="px-4 py-3 text-right lg:px-6">
                                    <form method="POST" action="{{ route('absensi.record.destroy', $r) }}" class="inline"
                                          onsubmit="return confirm('Hapus data kehadiran {{ $mahasiswa->nama }} untuk {{ $r->sesiAbsensi?->nama_sesi }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">Hapus</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 4 : 3 }}" class="px-4 py-6 text-center text-slate-500">Belum ada data kehadiran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
