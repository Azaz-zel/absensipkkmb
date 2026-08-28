@props(['href', 'active' => false])

<a href="{{ $href }}"
   @class([
        'flex items-center gap-3 rounded-full px-3 py-2 text-sm font-medium transition-colors',
        'bg-gold-600 text-white' => $active,
        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $active,
   ])>
    {{ $slot }}
</a>
