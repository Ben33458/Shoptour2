@extends('admin.layout')

@section('title', 'Primeur – Kunde: ' . $customer->name1)

@section('content')
<div class="page-header">
    <p style="margin:0 0 .25rem;color:var(--c-muted);font-size:.9rem;">
        <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> ›
        <a href="{{ route('admin.primeur.customers.index') }}">Kunden</a> ›
        {{ $customer->name1 }}
    </p>
    <h1 style="margin:0;">{{ $customer->name1 }} {{ $customer->name2 }}</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin:1.5rem 0;">
    {{-- Adresse --}}
    <div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
        <h3 style="margin:0 0 1rem;font-size:1rem;">Stammdaten</h3>
        <table style="width:100%;font-size:.9rem;border-collapse:collapse;">
            <tr><td style="color:var(--c-muted);padding:.2rem 0;width:40%;">Kundennr.</td><td><strong>{{ $customer->kundennummer ?? '—' }}</strong></td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Name3</td><td>{{ $customer->name3 ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Adresse</td><td>{{ $customer->strasse ?? '' }} {{ $customer->hausnr ?? '' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">PLZ/Ort</td><td>{{ $customer->plz ?? '' }} {{ $customer->ort ?? '' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Telefon</td><td>{{ $customer->telefon ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Telefon2</td><td>{{ $customer->telefon2 ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Fax</td><td>{{ $customer->fax ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">E-Mail</td><td>{{ $customer->email ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Kundengruppe</td><td>{{ $customer->kundengruppe ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Preisgruppe</td><td>{{ $customer->preisgruppe ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Zahlungsart</td><td>{{ $customer->zahlungsart ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Angelegt</td><td>{{ $customer->anleg_time?->format('d.m.Y') ?? '—' }}</td></tr>
        </table>
    </div>

    {{-- Umsatz pro Jahr --}}
    <div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
        <h3 style="margin:0 0 1rem;font-size:1rem;">Umsatz nach Jahr (Aufträge)</h3>
        @if($orderStats->isEmpty())
            <p style="color:var(--c-muted);font-size:.9rem;">Keine Auftragsdaten.</p>
        @else
        <table class="table" style="font-size:.9rem;">
            <thead><tr><th>Jahr</th><th style="text-align:right;">Anzahl</th><th style="text-align:right;">Umsatz</th></tr></thead>
            <tbody>
                @foreach($orderStats as $s)
                <tr>
                    <td>{{ $s->jahr }}</td>
                    <td style="text-align:right;">{{ $s->anzahl }}</td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($s->umsatz, 2, ',', '.') }} €</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:700;">
                    <td>Gesamt</td>
                    <td style="text-align:right;">{{ $orderStats->sum('anzahl') }}</td>
                    <td style="text-align:right;">{{ number_format($orderStats->sum('umsatz'), 2, ',', '.') }} €</td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>
</div>

{{-- Letzte Aufträge --}}
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
    <h3 style="margin:0 0 1rem;font-size:1rem;">Letzte 20 Aufträge</h3>
    @if(count($recentOrders) === 0)
        <p style="color:var(--c-muted);font-size:.9rem;">Keine Aufträge gefunden.</p>
    @else
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.9rem;">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Beleg-Nr.</th>
                    <th>Art</th>
                    <th>Status</th>
                    <th style="text-align:right;">Betrag</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentOrders as $o)
                <tr @if($o->storno) style="opacity:.6;" @endif>
                    <td>{{ $o->belegdatum ? \Carbon\Carbon::parse($o->belegdatum)->format('d.m.Y') : '—' }}</td>
                    <td style="font-family:monospace;">{{ $o->beleg_nr ?? '—' }}</td>
                    <td>{{ $o->auftragsart ?? '—' }}</td>
                    <td>
                        @if($o->storno) <span style="color:var(--c-danger,#dc2626);">Storno</span>
                        @else <span style="color:var(--c-success,#16a34a);">{{ $o->status ?? 'OK' }}</span>
                        @endif
                    </td>
                    <td style="text-align:right;">{{ $o->endbetrag ? number_format($o->endbetrag, 2, ',', '.') . ' €' : '—' }}</td>
                    <td><a href="{{ route('admin.primeur.orders.show', $o->id) }}" class="btn btn-sm btn-outline">Detail</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
