@extends('admin.layout')

@section('title', 'Primeur – Hersteller ' . $decodedName)

@section('content')
<div class="page-header">
    <h1 style="margin:0;">{{ $decodedName }}</h1>
    <p style="margin:.25rem 0 0;color:var(--c-muted);font-size:.9rem;">
        <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> ›
        <a href="{{ route('admin.primeur.articles.index') }}">Artikel</a> › Hersteller
    </p>
</div>

{{-- ── KPI ─────────────────────────────────────────────────────────────── --}}
<div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem;">
    @foreach([
        ['Artikel gesamt', number_format($summary['artikel'])],
        ['davon mit Umsatz', number_format($summary['mit_umsatz'])],
        ['Warengruppen', number_format($warengruppen->count())],
        ['Gesamtumsatz', number_format($summary['umsatz'], 2, ',', '.') . ' €'],
    ] as [$label, $val])
    <div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:.75rem 1.25rem;min-width:140px;">
        <div style="font-size:.8rem;color:var(--c-muted);">{{ $label }}</div>
        <div style="font-size:1.25rem;font-weight:700;">{{ $val }}</div>
    </div>
    @endforeach
</div>

{{-- ── Umsatz nach Jahr ─────────────────────────────────────────────────── --}}
@if($statsByYear->isNotEmpty())
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h2 style="margin:0 0 1rem;font-size:1rem;">Umsatz nach Jahr</h2>
    <table class="table" style="font-size:.9rem;max-width:500px;">
        <thead>
            <tr>
                <th>Jahr</th>
                <th style="text-align:right;">Umsatz</th>
                <th style="text-align:right;">Menge</th>
                <th style="text-align:right;">Belege</th>
            </tr>
        </thead>
        <tbody>
            @foreach($statsByYear as $s)
            <tr>
                <td>{{ $s->jahr }}</td>
                <td style="text-align:right;font-weight:600;">{{ number_format($s->umsatz, 2, ',', '.') }} €</td>
                <td style="text-align:right;">{{ number_format($s->menge, 0, ',', '.') }}</td>
                <td style="text-align:right;">{{ number_format($s->belege) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight:700;border-top:2px solid var(--c-border,#e2e8f0);">
                <td>Gesamt</td>
                <td style="text-align:right;">{{ number_format($statsByYear->sum('umsatz'), 2, ',', '.') }} €</td>
                <td style="text-align:right;">{{ number_format($statsByYear->sum('menge'), 0, ',', '.') }}</td>
                <td style="text-align:right;">{{ number_format($statsByYear->sum('belege')) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@endif

{{-- ── Warengruppen-Links ───────────────────────────────────────────────── --}}
@if($warengruppen->isNotEmpty())
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1rem;margin-bottom:1.25rem;">
    <span style="font-size:.85rem;color:var(--c-muted);margin-right:.5rem;">Warengruppen:</span>
    @foreach($warengruppen as $wg)
    <a href="{{ route('admin.primeur.articles.warengruppe', urlencode($wg)) }}"
       style="display:inline-block;margin:.2rem .3rem;padding:.2rem .6rem;background:#eff6ff;color:#1d4ed8;border-radius:4px;font-size:.85rem;text-decoration:none;">
        {{ $wg }}
    </a>
    @endforeach
</div>
@endif

{{-- ── Artikel-Tabelle ─────────────────────────────────────────────────── --}}
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.88rem;">
            <thead>
                <tr>
                    <th>Artikel</th>
                    <th>Warengruppe</th>
                    <th>Untergruppe</th>
                    <th style="text-align:right;">Menge</th>
                    <th style="text-align:right;">Umsatz</th>
                    <th style="text-align:right;">Belege</th>
                    <th style="text-align:right;white-space:nowrap;">Letzter Verk.</th>
                    <th style="text-align:center;">Aktiv</th>
                </tr>
            </thead>
            <tbody>
                @forelse($articles as $a)
                <tr>
                    <td>
                        <a href="{{ route('admin.primeur.articles.show', $a->primeur_id) }}" style="font-weight:500;">{{ $a->bezeichnung }}</a>
                        @if($a->zusatz) <span style="color:var(--c-muted);font-size:.8rem;"> · {{ $a->zusatz }}</span> @endif
                        @if($a->inhalt && $a->masseinheit)
                        <span style="display:block;color:var(--c-muted);font-size:.78rem;">{{ number_format($a->inhalt, 2, ',', '.') }} {{ $a->masseinheit }}</span>
                        @endif
                    </td>
                    <td style="font-size:.82rem;">
                        @if($a->warengruppe)
                        <a href="{{ route('admin.primeur.articles.warengruppe', urlencode($a->warengruppe)) }}">{{ $a->warengruppe }}</a>
                        @else – @endif
                    </td>
                    <td style="font-size:.82rem;">
                        @if($a->warengruppe && $a->warenuntergruppe)
                        <a href="{{ route('admin.primeur.articles.untergruppe', [urlencode($a->warengruppe), urlencode($a->warenuntergruppe)]) }}">{{ $a->warenuntergruppe }}</a>
                        @else – @endif
                    </td>
                    <td style="text-align:right;">{{ $a->menge > 0 ? number_format($a->menge, 0, ',', '.') : '–' }}</td>
                    <td style="text-align:right;font-weight:{{ $a->umsatz > 0 ? '600' : 'normal' }};">
                        {{ $a->umsatz > 0 ? number_format($a->umsatz, 2, ',', '.') . ' €' : '–' }}
                    </td>
                    <td style="text-align:right;">{{ $a->belege > 0 ? number_format($a->belege) : '–' }}</td>
                    <td style="text-align:right;font-size:.8rem;color:var(--c-muted);white-space:nowrap;">
                        {{ $a->letzter_verkauf ? \Carbon\Carbon::parse($a->letzter_verkauf)->format('d.m.Y') : '–' }}
                    </td>
                    <td style="text-align:center;color:{{ $a->aktiv ? 'var(--c-success,#16a34a)' : 'var(--c-muted)' }};">
                        {{ $a->aktiv ? '✓' : '–' }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;color:var(--c-muted);padding:2rem;">Keine Artikel.</td></tr>
                @endforelse
            </tbody>
            @if($articles->isNotEmpty())
            <tfoot>
                <tr style="font-weight:700;border-top:2px solid var(--c-border,#e2e8f0);">
                    <td colspan="3">Gesamt</td>
                    <td style="text-align:right;">{{ number_format($articles->sum('menge'), 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($articles->sum('umsatz'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($articles->sum('belege')) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
