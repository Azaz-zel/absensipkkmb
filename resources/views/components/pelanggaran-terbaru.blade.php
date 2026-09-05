@props(['items'])

<x-card>
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3.5 lg:px-6">
        <h2 class="font-serif text-lg font-semibold">Pelanggaran Terbaru</h2>
        <a href="{{ route('poin.index') }}" class="text-sm font-medium text-gold-700 hover:underline">Semua &rarr;</a>
    </div>

    <div class="divide-y divide-slate-100">
        @forelse ($items as $p)
            <div class="flex items-start justify-between gap-3 px-4 py-3 lg:px-6">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-800">{{ $p->mahasiswa?->nama ?? 'Maba terhapus' }}</p>
                    <p class="truncate text-xs text-slate-500">
                        {{ $p->jenis }}
                        @if ($p->pencatat)
                            &middot; oleh {{ $p->pencatat->name }}
                        @endif
                    </p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-sm font-semibold text-rose-700">+{{ $p->poin }}</p>
                    <p class="text-[11px] text-slate-400">{{ $p->created_at?->diffForHumans(short: true) }}</p>
                </div>
            </div>
        @empty
            <div class="px-4 py-10 text-center lg:px-6">
                <p class="text-sm text-slate-500">Belum ada pelanggaran yang dicatat.</p>
                <p class="mt-1 text-xs text-slate-400">Poin pelanggaran muncul di sini begitu ada yang dicatat.</p>
            </div>
        @endforelse
    </div>
</x-card>
