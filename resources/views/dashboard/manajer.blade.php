@php
    $statCards = [
        ['label' => 'Total Maba', 'value' => $stats['total_maba'], 'color' => 'bg-gold-600', 'note' => 'terdaftar'],
        ['label' => 'Kena Poin', 'value' => $stats['kena_poin'], 'color' => 'bg-rose-500', 'note' => 'perlu sanksi'],
        ['label' => 'Masih Bersih', 'value' => $stats['aman'], 'color' => 'bg-emerald-500', 'note' => '0 poin'],
        ['label' => 'Sanksi Berat', 'value' => $stats['perlu_sanksi_berat'], 'color' => 'bg-orange-500', 'note' => '10 poin ke atas'],
        ['label' => 'Poin Pelanggaran', 'value' => $stats['total_poin_pelanggaran'], 'color' => 'bg-indigo-500', 'note' => 'total tercatat'],
        ['label' => 'Dicatat Hari Ini', 'value' => $stats['pelanggaran_hari_ini'], 'color' => 'bg-slate-400', 'note' => 'pelanggaran baru'],
    ];
@endphp

<x-layouts.app title="Dashboard">
    <div class="mb-6 rounded-2xl border border-gold-200 bg-linear-to-br from-gold-50 to-white p-5 shadow-sm lg:mb-8 lg:p-7">
        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-gold-700">Sie Kesekre</p>
        <h2 class="mt-1 font-serif text-2xl font-semibold text-slate-900 lg:text-3xl">Cari Maba, Catat Poin</h2>
        <p class="mt-1.5 text-sm text-slate-600">Ketik NIM atau nama mahasiswa untuk melihat poin dan mencatat pelanggaran.</p>

        <form method="GET" action="{{ route('poin.index') }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
            <input type="search" name="q" autocomplete="off" placeholder="Masukkan NIM atau nama maba"
                   class="w-full rounded-full border border-gold-300 bg-white px-5 py-3 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
            <button type="submit"
                    class="shrink-0 rounded-full bg-gold-600 px-6 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">
                Cari Maba
            </button>
        </form>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6 lg:gap-5">
        @foreach ($statCards as $s)
            <x-card padded>
                <p class="text-xs font-medium text-slate-500">{{ $s['label'] }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight">{{ $s['value'] }}</p>
                <p class="mt-1 text-[11px] text-slate-400">{{ $s['note'] }}</p>
                <div class="mt-3 h-1 w-8 rounded-full {{ $s['color'] }}"></div>
            </x-card>
        @endforeach
    </div>

    <div class="mt-6 grid grid-cols-1 gap-5 lg:mt-8 lg:grid-cols-2">
        <x-sebaran-sanksi :sebaran="$sebaran" :total="$stats['total_maba']" />
        <x-pelanggaran-terbaru :items="$pelanggaranTerbaru" />
    </div>

    <div class="mt-6 lg:mt-8">
        <x-tabel-poin-tertinggi :items="$topPoin" />
    </div>
</x-layouts.app>
