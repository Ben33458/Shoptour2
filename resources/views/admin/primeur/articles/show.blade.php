@extends('admin.layout')

@section('title', 'Primeur – ' . $artikel->bezeichnung)

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem;">
    <div>
        <h1 style="margin:0;">{{ $artikel->bezeichnung }}@if($artikel->zusatz) <span style="font-size:.75em;color:var(--c-muted);">{{ $artikel->zusatz }}</span>@endif</h1>
        <p style="margin:.25rem 0 0;color:var(--c-muted);font-size:.9rem;">
            <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> ›
            <a href="{{ route('admin.primeur.articles.index') }}">Artikel</a> ›
            {{ $artikel->bezeichnung }}
        </p>
    </div>
    <span style="padding:.25rem .75rem;border-radius:4px;font-size:.85rem;font-weight:600;background:{{ $artikel->aktiv ? '#f0fdf4' : '#fef2f2' }};color:{{ $artikel->aktiv ? '#166534' : '#991b1b' }};">
        {{ $artikel->aktiv ? 'Aktiv' : 'Inaktiv' }}
    </span>
</div>

{{-- ── Stammdaten ───────────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">

    <div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
        <h2 style="margin:0 0 1rem;font-size:1rem;">Stammdaten</h2>
        <table style="font-size:.9rem;width:100%;border-collapse:collapse;">
            <tr><td style="color:var(--c-muted);padding:.2rem .5rem .2rem 0;width:40%;">Artikelnummer</td><td><strong>{{ $artikel->artikelnummer ?? '–' }}</strong></td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem .5rem .2rem 0;">Kurzbezeichnung</td><td>{{ $artikel->kurzbezeichnung ?? '–' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem .5rem .2rem 0;">Inhalt</td><td>
                @if($artikel->inhalt && $artikel->masseinheit)
                    {{ number_format($artikel->inhalt, 2, ',', '.') }} {{ $artikel->masseinheit }}
                @else – @endif
            </td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem .5rem .2rem 0;">VK-Bezug</td><td>{{ $artikel->vk_bezug ?? '–' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem .5rem .2rem 0;">Angelegt</td><td style="font-size:.85rem;">{{ $artikel->anleg_time ? \Carbon\Carbon::parse($artikel->anleg_time)->format('d.m.Y') : '–' }}</td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem .5rem .2rem 0;">Aktualisiert</td><td style="font-size:.85rem;">{{ $artikel->update_time ? \Carbon\Carbon::parse($artikel->update_time)->format('d.m.Y') : '–' }}</td></tr>
        </table>
    </div>

    <div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
        <h2 style="margin:0 0 1rem;font-size:1rem;">Klassifikation</h2>
        <table style="font-size:.9rem;width:100%;border-collapse:collapse;">
            <tr><td style="color:var(--c-muted);padding:.2rem .5rem .2rem 0;width:40%;">Warengruppe</td>
                <td><a href="{{ route('admin.primeur.articles.warengruppe', urlencode($artikel->warengruppe ?? '')) }}">{{ $artikel->warengruppe ?? '–' }}</a></td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem .5rem .2rem 0;">Untergruppe</td>
                <td>
                    @if($artikel->warengruppe && $artikel->warenuntergruppe)
                        <a href="{{ route('admin.primeur.articles.untergruppe', [urlencode($artikel->warengruppe), urlencode($artikel->warenuntergruppe)]) }}">{{ $artikel->warenuntergruppe }}</a>
                    @else – @endif
                </td></tr>
            <tr><td style="color:var(--c-muted);padding:.2rem .5rem .2rem 0;">Hersteller</td>
                <td>
                    @if($artikel->hersteller)
                        <a href="{{ route('admin.primeur.articles.hersteller', urlencode($artikel->hersteller)) }}">{{ $artikel->hersteller }}</a>
                    @else – @endif
                </td></tr>
            @if($statsByYear->isNotEmpty())
            <tr><td style="color:var(--c-muted);padding:.2rem .5rem .2rem 0;">MwSt-Satz</td>
                <td>{{ number_format($statsByYear->first()->mwst_satz * 100, 0) }} %</td></tr>
            @endif
        </table>
    </div>
</div>

{{-- ── VK-Preise ────────────────────────────────────────────────────────── --}}
@if(!empty($vkByPG))
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h2 style="margin:0 0 1rem;font-size:1rem;">VK-Preisliste <span style="font-weight:400;color:var(--c-muted);font-size:.85rem;">(aktive Preise aus IT-Drink)</span></h2>
    <table class="table" style="font-size:.9rem;max-width:600px;">
        <thead>
            <tr>
                <th>Preisgruppe</th>
                <th style="text-align:right;">Gebinde (H)</th>
                <th style="text-align:right;">Einzeln (V)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($vkByPG as $preisgruppe => $einheiten)
            @php
                $isIncl = str_contains($preisgruppe, 'incl.');
                $isZzgl = str_contains($preisgruppe, 'zzgl.');
            @endphp
            <tr>
                <td>
                    {{ $preisgruppe }}
                    @if($isIncl) <span style="font-size:.75rem;color:var(--c-muted);">inkl. MwSt</span>
                    @elseif($isZzgl) <span style="font-size:.75rem;color:var(--c-muted);">zzgl. MwSt</span>
                    @endif
                </td>
                <td style="text-align:right;font-weight:600;">
                    {{ isset($einheiten['H']) ? number_format($einheiten['H'], 2, ',', '.') . ' €' : '–' }}
                </td>
                <td style="text-align:right;">
                    {{ isset($einheiten['V']) ? number_format($einheiten['V'], 2, ',', '.') . ' €' : '–' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ── EK-Preise ────────────────────────────────────────────────────────── --}}
@if(!empty($ekRows))
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h2 style="margin:0 0 1rem;font-size:1rem;">EK-Preise / Lieferanten</h2>
    <table class="table" style="font-size:.9rem;max-width:700px;">
        <thead>
            <tr>
                <th>Lieferant</th>
                <th style="text-align:center;">Einheit</th>
                <th style="text-align:right;">Listen-EK</th>
                <th style="text-align:right;">Effektiver EK</th>
                <th style="text-align:right;">Spanne (Abholmarkt)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ekRows as $ek)
            @php
                $vkH = $vkByPG['Abholmarkt']['H'] ?? null;
                $spanne = ($ek['einheit'] === 'H' && $vkH) ? $vkH - $ek['effektiver_ek'] : null;
            @endphp
            <tr>
                <td>{{ $ek['lieferant_name'] }}</td>
                <td style="text-align:center;color:var(--c-muted);">{{ $ek['einheit'] }}</td>
                <td style="text-align:right;">{{ number_format($ek['listen_ek'], 2, ',', '.') }} €</td>
                <td style="text-align:right;font-weight:600;">{{ number_format($ek['effektiver_ek'], 2, ',', '.') }} €</td>
                <td style="text-align:right;color:{{ $spanne !== null && $spanne > 0 ? 'var(--c-success,#16a34a)' : 'var(--c-muted)' }};">
                    @if($spanne !== null)
                        {{ number_format($spanne, 2, ',', '.') }} €
                        <span style="font-size:.8rem;">({{ $ek['effektiver_ek'] > 0 ? number_format($spanne / $ek['effektiver_ek'] * 100, 1) : '–' }} %)</span>
                    @else –
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ── Verkaufsstatistik nach Jahr ──────────────────────────────────────── --}}
@if($statsByYear->isNotEmpty())
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h2 style="margin:0 0 1rem;font-size:1rem;">Verkaufsstatistik nach Jahr</h2>
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.9rem;">
            <thead>
                <tr>
                    <th>Jahr</th>
                    <th style="text-align:right;">Menge</th>
                    <th style="text-align:right;">Umsatz</th>
                    <th style="text-align:right;">Belege</th>
                    <th style="text-align:right;">Ø-Preis</th>
                    <th style="text-align:right;">Min-Preis</th>
                    <th style="text-align:right;">Max-Preis</th>
                    <th style="text-align:right;">MwSt</th>
                </tr>
            </thead>
            <tbody>
                @foreach($statsByYear as $s)
                <tr>
                    <td>{{ $s->jahr }}</td>
                    <td style="text-align:right;">{{ number_format($s->menge, 0, ',', '.') }}</td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($s->umsatz, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($s->belege) }}</td>
                    <td style="text-align:right;">{{ number_format($s->avg_preis, 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:var(--c-muted);">{{ number_format($s->min_preis, 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:var(--c-muted);">{{ number_format($s->max_preis, 2, ',', '.') }} €</td>
                    <td style="text-align:right;font-size:.82rem;">{{ number_format($s->mwst_satz * 100, 0) }} %</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:700;border-top:2px solid var(--c-border,#e2e8f0);">
                    <td>Gesamt</td>
                    <td style="text-align:right;">{{ number_format($statsByYear->sum('menge'), 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($statsByYear->sum('umsatz'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($statsByYear->sum('belege')) }}</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

{{-- ── Letzte Verkäufe ──────────────────────────────────────────────────── --}}
@if($recentSales->isNotEmpty())
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
    <h2 style="margin:0 0 1rem;font-size:1rem;">Letzte 25 Verkäufe</h2>
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.85rem;">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th style="text-align:right;">Menge</th>
                    <th style="text-align:right;">VK-Preis</th>
                    <th style="text-align:right;">Gesamt</th>
                    <th style="text-align:center;">Zahlung</th>
                    <th style="text-align:right;">Beleg-Nr.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentSales as $sale)
                <tr>
                    <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($sale->datum)->format('d.m.Y') }}</td>
                    <td style="text-align:right;">{{ number_format($sale->menge, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($sale->vk_preis_tatsaechlich, 2, ',', '.') }} €</td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($sale->menge * $sale->vk_preis_tatsaechlich, 2, ',', '.') }} €</td>
                    <td style="text-align:center;font-size:.8rem;">
                        @if($sale->kartenzahlung > 0) <span style="color:#2563eb;">Karte</span>
                        @elseif($sale->barbetrag > 0) Bar
                        @else – @endif
                    </td>
                    <td style="text-align:right;color:var(--c-muted);">{{ $sale->belegnummer }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if($statsByYear->isEmpty())
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:2rem;text-align:center;color:var(--c-muted);">
    Kein Verkauf für diesen Artikel in den Kassendaten gefunden.
</div>
@endif
@endsection
