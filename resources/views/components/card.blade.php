@props(['padded' => false])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white shadow-sm '.($padded ? 'p-4 lg:p-6' : '')]) }}>
    {{ $slot }}
</div>
