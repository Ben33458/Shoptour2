{{--
    Tab-Leiste für Schichten-Bereich.
    Verwendung: @include('admin._partials.shifts-tabs')
--}}
<div style="display:flex;gap:.25rem;margin-bottom:1.5rem;border-bottom:2px solid var(--c-border,#e2e8f0);padding-bottom:0;">
    <a href="{{ route('admin.shifts.index') }}"
       style="padding:.5rem 1rem;border-radius:6px 6px 0 0;font-size:.9rem;font-weight:500;text-decoration:none;
              {{ request()->routeIs('admin.shifts.index') || request()->routeIs('admin.shifts.create') || request()->routeIs('admin.shifts.edit')
                 ? 'background:var(--c-primary,#2563eb);color:#fff;'
                 : 'color:var(--c-muted,#64748b);background:transparent;' }}">
        Schichtplanung
    </a>
    <a href="{{ route('admin.shifts.reports.index') }}"
       style="padding:.5rem 1rem;border-radius:6px 6px 0 0;font-size:.9rem;font-weight:500;text-decoration:none;
              {{ request()->routeIs('admin.shifts.reports.*')
                 ? 'background:var(--c-primary,#2563eb);color:#fff;'
                 : 'color:var(--c-muted,#64748b);background:transparent;' }}">
        Schichtberichte
    </a>
</div>
