@props(['ringkasan', 'sesiTerpilih' => null])

@php
    $lengkap = $ringkasan['lengkap'];
    $belum = $ringkasan['belum_diabsen'];
@endphp

<x-card padded {{ $attributes }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium text-slate-500">Kelengkapan Absensi</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight {{ $lengkap ? 'text-emerald-700' : 'text-slate-900' }}">
                {{ $ringkasan['sudah_diabsen'] }}<span class="text-lg font-normal text-slate-400"> / {{ $ringkasan['seharusnya'] }}</span>
            </p>
            <p class="mt-1 text-xs text-slate-500">
                {{ $ringkasan['total_maba'] }} maba
                @if ($ringkasan['jumlah_sesi'] > 1)
                    &times; {{ $ringkasan['jumlah_sesi'] }} sesi
                @elseif ($sesiTerpilih)
                    &middot; {{ $sesiTerpilih->nama_sesi }}
                @endif
            </p>
        </div>

        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset {{ $lengkap ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-amber-50 text-amber-700 ring-amber-600/20' }}">
            @if ($lengkap)
                Semua sudah diabsen
            @else
                {{ $belum }} belum diabsen
            @endif
        </span>
    </div>

    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full {{ $lengkap ? 'bg-emerald-500' : 'bg-gold-600' }}"
             style="width: {{ min($ringkasan['persen'], 100) }}%"></div>
    </div>
    <p class="mt-1.5 text-[11px] font-medium uppercase tracking-[0.15em] text-slate-400">{{ $ringkasan['persen'] }}% terisi</p>
</x-card>
