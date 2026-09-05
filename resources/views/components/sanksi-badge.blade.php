@props(['sanksi'])

@php
    $classes = match ($sanksi['level']) {
        'ringan' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'sedang' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'berat' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'merah' => 'bg-rose-600 text-white ring-rose-700/30',
        default => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset $classes"]) }}>
    {{ $sanksi['label'] }}
</span>
