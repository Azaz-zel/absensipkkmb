<x-layouts.app title="Sesi Absensi">
    <x-card padded class="mb-6">
        <h2 class="mb-3 font-serif text-lg font-semibold text-slate-700">Buat Sesi Baru</h2>
        <form method="POST" action="{{ route('sesi.store') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            @csrf
            <input type="text" name="nama_sesi" value="{{ old('nama_sesi') }}" placeholder="Nama Sesi (mis. Sesi 1)" required
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
            <input type="date" name="tanggal" value="{{ old('tanggal') }}" required
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
            <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Buat Sesi</button>
        </form>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 lg:px-6">Nama Sesi</th>
                        <th class="px-4 py-2.5 lg:px-6">Tanggal</th>
                        <th class="px-4 py-2.5 lg:px-6">Status</th>
                        <th class="px-4 py-2.5 text-right lg:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sesiList as $sesi)
                        <tr class="transition-colors hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-medium lg:px-6">{{ $sesi->nama_sesi }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $sesi->tanggal->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3 lg:px-6"><x-status-badge :status="$sesi->statusRingkas()" /></td>
                            <td class="px-4 py-3 text-right lg:px-6">
                                <a href="{{ route('sesi.show', $sesi) }}" class="mr-3 text-sm font-medium text-slate-700 hover:underline">Kelola</a>
                                <form method="POST" action="{{ route('sesi.destroy', $sesi) }}" class="inline"
                                      onsubmit="return confirm('Hapus sesi {{ $sesi->nama_sesi }}? Seluruh data absensi pada sesi ini juga akan terhapus.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">Hapus</button>
                                </form>
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

        <div class="border-t border-slate-200 px-4 py-3 lg:px-6">
            {{ $sesiList->links() }}
        </div>
    </x-card>
</x-layouts.app>
