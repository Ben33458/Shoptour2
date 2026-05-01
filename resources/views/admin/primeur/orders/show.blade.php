@extends('admin.layout')

@section('title', 'Primeur – Auftrag #' . $order->beleg_nr)

@section('content')
<div class="page-header">
    <p style="margin:0 0 .25rem;color:var(--c-muted);font-size:.9rem;">
        <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> ›
        <a href="{{ route('admin.primeur.orders.index') }}">Aufträge</a> ›
        #{{ $order->beleg_nr }}
    </p>
    <h1 style="margin:0;">{{ $order->auftragsart }} #{{ $order->beleg_nr }}
        @if($order->storno)<span style="color:var(--c-danger,#dc2626);font-size:.65em;"> [STORNO]</span>@endif
    </h1>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin:1.5rem 0;">
    <div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
        <h3 style="margin:0 0 1rem;font-size:1rem;">Belegdaten</h3>
        <table style="width:100%;font-size:.9rem;border-collapse:collapse;">
            <tr><td style="color:var(--c-muted);padding:.2rem 0;width:45%;">Beleg-Nr.</td><td><strong>{{ $order->beleg_nr ?? '—' }}</strong></td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Art</td><td>{{ $order->auftragsart ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Belegdatum</td><td>{{ $order->belegdatum ? \Carbon\Carbon::parse($order->belegdatum)->format('d.m.Y') : '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Lieferdatum</td><td>{{ $order->lieferdatum ? \Carbon\Carbon::parse($order->lieferdatum)->format('d.m.Y') : '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Rechnungsdatum</td><td>{{ $order->rechnungsdatum ? \Carbon\Carbon::parse($order->rechnungsdatum)->format('d.m.Y') : '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Tour</td><td>{{ $order->tour ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Sachbearbeiter</td><td>{{ $order->sachbearbeiter ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Zahlungsart</td><td>{{ $order->zahlungsart ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Status</td><td>{{ $order->status ?? '—' }}</td></tr>
        </table>
    </div>
    <div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
        <h3 style="margin:0 0 1rem;font-size:1rem;">Kunde & Beträge</h3>
        <table style="width:100%;font-size:.9rem;border-collapse:collapse;">
            @if($order->name1)
            <tr><td style="color:var(--c-muted);padding:.2rem 0;width:45%;">Kunde</td>
                <td><a href="{{ route('admin.primeur.customers.show', $order->kunden_id) }}">{{ $order->name1 }} {{ $order->name2 }}</a></td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Kundennr.</td><td>{{ $order->kundennummer ?? '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Adresse</td><td>{{ $order->strasse ?? '' }} {{ $order->hausnr ?? '' }}, {{ $order->plz ?? '' }} {{ $order->ort ?? '' }}</td></tr>
            @endif
            <tr><td style="color:var(--c-muted);padding:.2rem 0;padding-top:.75rem;">Warenwert</td><td>{{ $order->warenwert_gesamt ? number_format($order->warenwert_gesamt, 2, ',', '.') . ' €' : '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Netto</td><td>{{ $order->gesamt_netto ? number_format($order->gesamt_netto, 2, ',', '.') . ' €' : '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">MwSt.</td><td>{{ $order->mehrwertsteuer ? number_format($order->mehrwertsteuer, 2, ',', '.') . ' €' : '—' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem 0;">Skonto</td><td>{{ $order->skonto ? number_format($order->skonto, 2, ',', '.') . ' €' : '—' }}</td></tr>
            <tr style="border-top:2px solid var(--c-border,#e2e8f0);">
                <td style="padding:.5rem 0;font-weight:700;">Endbetrag</td>
                <td style="font-weight:700;font-size:1.1rem;">{{ $order->endbetrag ? number_format($order->endbetrag, 2, ',', '.') . ' €' : '—' }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- Positionen --}}
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
    <h3 style="margin:0 0 1rem;font-size:1rem;">Positionen ({{ count($items) }})</h3>
    @if($items->isEmpty())
        <p style="color:var(--c-muted);font-size:.9rem;">Keine Positionen.</p>
    @else
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.9rem;">
            <thead>
                <tr>
                    <th>Artikelnr.</th>
                    <th>Bezeichnung</th>
                    <th>Einheit</th>
                    <th style="text-align:right;">Bestell-Menge</th>
                    <th style="text-align:right;">Liefer-Menge</th>
                    <th style="text-align:right;">VK-Preis</th>
                    <th style="text-align:right;">Gesamt</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td style="font-family:monospace;font-size:.8rem;">{{ $item->artikelnummer ?? '—' }}</td>
                    <td>{{ $item->artikel_bezeichnung ?? '—' }}</td>
                    <td>{{ $item->artikeleinheit ?? '—' }}</td>
                    <td style="text-align:right;">{{ number_format($item->bestellmenge, 2, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($item->liefermenge, 2, ',', '.') }}</td>
                    <td style="text-align:right;">{{ $item->vk_preis_tatsaechlich ? number_format($item->vk_preis_tatsaechlich, 2, ',', '.') . ' €' : '—' }}</td>
                    <td style="text-align:right;font-weight:600;">
                        {{ $item->vk_preis_tatsaechlich && $item->liefermenge
                            ? number_format($item->vk_preis_tatsaechlich * $item->liefermenge, 2, ',', '.') . ' €'
                            : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
