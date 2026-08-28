@props(['status'])

@php
    $label = match ($status) {
        'hadir' => 'Hadir',
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'alpa' => 'Alpa',
        'belum_dibuka' => 'Belum Dibuka',
        'dibuka' => 'Dibuka',
        'ditutup' => 'Ditutup',
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };

    $classes = match ($status) {
        'hadir', 'dibuka', 'aktif' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'izin' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'sakit' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'alpa', 'ditutup', 'nonaktif' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'belum_dibuka' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        default => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset $classes"]) }}>
    {{ $label }}
</span>
