@if ($paginator->hasPages())
<nav role="navigation" aria-label="Seitennavigation"
     style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">

    {{-- Previous --}}
    @if ($paginator->onFirstPage())
        <span style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid var(--pag-border,#d1d5db);border-radius:6px;color:var(--pag-muted,#9ca3af);background:var(--pag-bg,#f9fafb);cursor:not-allowed;font-size:14px;line-height:1;">&#8249;</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
           style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid var(--pag-border,#d1d5db);border-radius:6px;color:var(--pag-text,#374151);background:var(--pag-bg,#f9fafb);font-size:14px;text-decoration:none;line-height:1;transition:background .12s,border-color .12s;"
           onmouseover="this.style.background='var(--pag-hover,#eff6ff)';this.style.borderColor='var(--pag-hover-border,#93c5fd)'"
           onmouseout="this.style.background='var(--pag-bg,#f9fafb)';this.style.borderColor='var(--pag-border,#d1d5db)'"
           aria-label="{{ __('pagination.previous') }}">&#8249;</a>
    @endif

    {{-- Page numbers --}}
    @foreach ($elements as $element)
        @if (is_string($element))
            <span style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 6px;color:var(--pag-muted,#9ca3af);font-size:13px;">…</span>
        @endif

        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span aria-current="page"
                          style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border-radius:6px;background:var(--pag-active-bg,#6b7280);color:var(--pag-active-text,#fff);font-size:13px;font-weight:600;line-height:1;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}"
                       style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid var(--pag-border,#d1d5db);border-radius:6px;color:var(--pag-text,#374151);background:var(--pag-bg,#f9fafb);font-size:13px;text-decoration:none;line-height:1;transition:background .12s,border-color .12s;"
                       onmouseover="this.style.background='var(--pag-hover,#eff6ff)';this.style.borderColor='var(--pag-hover-border,#93c5fd)'"
                       onmouseout="this.style.background='var(--pag-bg,#f9fafb)';this.style.borderColor='var(--pag-border,#d1d5db)'"
                       aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Next --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next"
           style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid var(--pag-border,#d1d5db);border-radius:6px;color:var(--pag-text,#374151);background:var(--pag-bg,#f9fafb);font-size:14px;text-decoration:none;line-height:1;transition:background .12s,border-color .12s;"
           onmouseover="this.style.background='var(--pag-hover,#eff6ff)';this.style.borderColor='var(--pag-hover-border,#93c5fd)'"
           onmouseout="this.style.background='var(--pag-bg,#f9fafb)';this.style.borderColor='var(--pag-border,#d1d5db)'"
           aria-label="{{ __('pagination.next') }}">&#8250;</a>
    @else
        <span style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid var(--pag-border,#d1d5db);border-radius:6px;color:var(--pag-muted,#9ca3af);background:var(--pag-bg,#f9fafb);cursor:not-allowed;font-size:14px;line-height:1;">&#8250;</span>
    @endif

</nav>
@endif
