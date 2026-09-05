@if ($paginator->hasPages())
    @php
        $pill = 'inline-flex h-9 min-w-9 items-center justify-center rounded-full px-3 text-sm font-medium transition';
        $pillIdle = $pill.' border border-slate-300 bg-white text-slate-700 hover:bg-slate-50';
        $pillActive = $pill.' bg-gold-600 text-white shadow-sm';
        $pillDisabled = $pill.' border border-slate-200 bg-white text-slate-400 cursor-not-allowed';
        $pillDots = $pill.' text-slate-400';
    @endphp

    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
         class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <p class="text-[11px] font-medium uppercase tracking-[0.15em] text-slate-500">
            Menampilkan
            @if ($paginator->firstItem())
                <span class="text-slate-800">{{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }}</span>
            @else
                <span class="text-slate-800">{{ $paginator->count() }}</span>
            @endif
            dari <span class="text-slate-800">{{ $paginator->total() }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="{{ $pillDisabled }}" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">&larr;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $pillIdle }}" aria-label="{{ __('pagination.previous') }}">&larr;</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $pillDots }}" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="{{ $pillActive }}" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $pillIdle }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $pillIdle }}" aria-label="{{ __('pagination.next') }}">&rarr;</a>
            @else
                <span class="{{ $pillDisabled }}" aria-disabled="true" aria-label="{{ __('pagination.next') }}">&rarr;</span>
            @endif
        </div>
    </nav>
@endif
