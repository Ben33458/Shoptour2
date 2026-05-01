@extends('admin.layout')

@section('title', 'Datenabgleich')

@section('content')
<div class="page-header">
    <h1>Datenabgleich</h1>
</div>

<p style="color:var(--c-muted,#64748b);margin-bottom:2rem;">
    Hier werden externe Datensätze (Ninox, GetraenkeDB, Lieferanten) mit den lokalen Kolabri-Datensätzen abgeglichen.
</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;">

    {{-- Kunden --}}
    <div class="card">
        <div class="card-header"><strong>Kunden-Abgleich</strong></div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--c-muted,#64748b);margin-bottom:.75rem;">
                Kolabri-Kunden mit GetraenkeDB-Herstellern/Einkäufern verknüpfen.
            </p>
            @if(isset($stats['customers_unmatched']) && $stats['customers_unmatched'] > 0)
                <div style="font-size:.875rem;margin-bottom:.75rem;color:var(--c-warning,#d97706);">
                    ⚠ {{ $stats['customers_unmatched'] }} Kunden noch nicht zugeordnet
                </div>
            @endif
            <a href="{{ route('admin.reconcile.customers') }}" class="btn btn-secondary" style="width:100%;text-align:center;">Zum Kunden-Abgleich →</a>
        </div>
    </div>

    {{-- Lieferanten --}}
    <div class="card">
        <div class="card-header"><strong>Lieferanten-Abgleich</strong></div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--c-muted,#64748b);margin-bottom:.75rem;">
                Kolabri-Lieferanten mit GetraenkeDB-Herstellern verknüpfen.
            </p>
            @if(isset($stats['suppliers_unmatched']) && $stats['suppliers_unmatched'] > 0)
                <div style="font-size:.875rem;margin-bottom:.75rem;color:var(--c-warning,#d97706);">
                    ⚠ {{ $stats['suppliers_unmatched'] }} Lieferanten noch nicht zugeordnet
                </div>
            @endif
            <a href="{{ route('admin.reconcile.suppliers') }}" class="btn btn-secondary" style="width:100%;text-align:center;">Zum Lieferanten-Abgleich →</a>
        </div>
    </div>

    {{-- Produkte --}}
    <div class="card">
        <div class="card-header"><strong>Produkt-Abgleich</strong></div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--c-muted,#64748b);margin-bottom:.75rem;">
                Kolabri-Produkte mit GetraenkeDB-Produktfamilien verknüpfen.
            </p>
            @if(isset($stats['products_unmatched']) && $stats['products_unmatched'] > 0)
                <div style="font-size:.875rem;margin-bottom:.75rem;color:var(--c-warning,#d97706);">
                    ⚠ {{ $stats['products_unmatched'] }} Produkte noch nicht zugeordnet
                </div>
            @endif
            <a href="{{ route('admin.reconcile.products') }}" class="btn btn-secondary" style="width:100%;text-align:center;">Zum Produkt-Abgleich →</a>
        </div>
    </div>

    {{-- GetraenkeDB --}}
    <div class="card">
        <div class="card-header"><strong>GetraenkeDB-Sync</strong></div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--c-muted,#64748b);margin-bottom:.75rem;">
                Stammdaten (Hersteller, Marken, Produkte) mit der zentralen GetränkeDB synchronisieren.
            </p>
            <a href="{{ route('admin.reconcile.getraenkedb.index') }}" class="btn btn-secondary" style="width:100%;text-align:center;">Zum GetraenkeDB-Abgleich →</a>
        </div>
    </div>

    {{-- Mitarbeiter (Ninox) --}}
    <div class="card">
        <div class="card-header"><strong>Mitarbeiter-Abgleich (Ninox)</strong></div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--c-muted,#64748b);margin-bottom:.75rem;">
                Kolabri-Mitarbeiter mit Ninox-Datensätzen verknüpfen.
            </p>
            @if(isset($stats['employees_unmatched']) && $stats['employees_unmatched'] > 0)
                <div style="font-size:.875rem;margin-bottom:.75rem;color:var(--c-warning,#d97706);">
                    ⚠ {{ $stats['employees_unmatched'] }} aktive Mitarbeiter ohne Ninox-Verknüpfung
                </div>
            @endif
            @if($ninoxLastRun)
                <div style="font-size:.8rem;color:var(--c-muted,#64748b);margin-bottom:.75rem;">
                    Letzter Ninox-Import: {{ $ninoxLastRun->started_at->format('d.m.Y H:i') }} Uhr
                </div>
            @endif
            <a href="{{ route('admin.reconcile.employees') }}" class="btn btn-secondary" style="width:100%;text-align:center;">Zum Mitarbeiter-Abgleich →</a>
        </div>
    </div>

</div>
@endsection
