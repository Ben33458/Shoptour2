@if ($paginator->hasPages())
<nav role="navigation" aria-label="Seitennavigation" style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">

    {{-- Previous --}}
    @if ($paginator->onFirstPage())
        <span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid var(--pag-border,#e5e7eb);border-radius:8px;color:var(--pag-muted,#9ca3af);background:var(--pag-bg,#fff);cursor:not-allowed;font-size:13px;">&#8249;</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
           style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid var(--pag-border,#e5e7eb);border-radius:8px;color:var(--pag-text,#374151);background:var(--pag-bg,#fff);font-size:13px;text-decoration:none;transition:background .15s,border-color .15s;"
           onmouseover="this.style.background='var(--pag-hover,#eff6ff)';this.style.borderColor='var(--pag-hover-border,#93c5fd)'"
           onmouseout="this.style.background='var(--pag-bg,#fff)';this.style.borderColor='var(--pag-border,#e5e7eb)'"
           aria-label="{{ __('pagination.previous') }}">&#8249;</a>
    @endif

    {{-- Page numbers --}}
    @foreach ($elements as $element)
        @if (is_string($element))
            <span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;color:var(--pag-muted,#9ca3af);font-size:13px;">…</span>
        @endif

        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span aria-current="page"
                          style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:#f59e0b;color:#fff;font-size:13px;font-weight:600;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}"
                       style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid var(--pag-border,#e5e7eb);border-radius:8px;color:var(--pag-text,#374151);background:var(--pag-bg,#fff);font-size:13px;text-decoration:none;transition:background .15s,border-color .15s;"
                       onmouseover="this.style.background='var(--pag-hover,#eff6ff)';this.style.borderColor='var(--pag-hover-border,#93c5fd)'"
                       onmouseout="this.style.background='var(--pag-bg,#fff)';this.style.borderColor='var(--pag-border,#e5e7eb)'"
                       aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Next --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next"
           style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid var(--pag-border,#e5e7eb);border-radius:8px;color:var(--pag-text,#374151);background:var(--pag-bg,#fff);font-size:13px;text-decoration:none;transition:background .15s,border-color .15s;"
           onmouseover="this.style.background='var(--pag-hover,#eff6ff)';this.style.borderColor='var(--pag-hover-border,#93c5fd)'"
           onmouseout="this.style.background='var(--pag-bg,#fff)';this.style.borderColor='var(--pag-border,#e5e7eb)'"
           aria-label="{{ __('pagination.next') }}">&#8250;</a>
    @else
        <span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid var(--pag-border,#e5e7eb);border-radius:8px;color:var(--pag-muted,#9ca3af);background:var(--pag-bg,#fff);cursor:not-allowed;font-size:13px;">&#8250;</span>
    @endif

</nav>
@endif
