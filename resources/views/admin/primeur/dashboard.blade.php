@extends('admin.layout')

@section('title', 'Primeur-Archiv')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
    <div>
        <h1 style="margin:0;">Primeur-Archiv <span style="font-size:.7em;color:var(--c-muted,#64748b);font-weight:400;">IT-Drink Altdaten 2015–2024</span></h1>
        <p style="color:var(--c-muted,#64748b);margin:.25rem 0 0;">Archivierte Kassendaten, Kunden und Aufträge aus dem IT-Drink-System. Read-only.</p>
    </div>
</div>

{{-- ── Quick Stats ──────────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin:1.5rem 0;">
    <a href="{{ route('admin.primeur.customers.index') }}" style="text-decoration:none;">
        <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
            <div style="font-size:1.8rem;font-weight:700;color:var(--c-primary,#2563eb);">{{ number_format($stats['customers']) }}</div>
            <div style="color:var(--c-muted);font-size:.85rem;margin-top:.25rem;">Kunden</div>
        </div>
    </a>
    <a href="{{ route('admin.primeur.orders.index') }}" style="text-decoration:none;">
        <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
            <div style="font-size:1.8rem;font-weight:700;color:var(--c-primary,#2563eb);">{{ number_format($stats['orders']) }}</div>
            <div style="color:var(--c-muted);font-size:.85rem;margin-top:.25rem;">Aufträge</div>
        </div>
    </a>
    <a href="{{ route('admin.primeur.cash.receipts') }}" style="text-decoration:none;">
        <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
            <div style="font-size:1.8rem;font-weight:700;color:var(--c-primary,#2563eb);">{{ number_format($stats['receipts']) }}</div>
            <div style="color:var(--c-muted);font-size:.85rem;margin-top:.25rem;">Kassenbelege</div>
        </div>
    </a>
    <a href="{{ route('admin.primeur.cash.daily') }}" style="text-decoration:none;">
        <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
            <div style="font-size:1.8rem;font-weight:700;color:var(--c-primary,#2563eb);">{{ number_format($stats['cash_days']) }}</div>
            <div style="color:var(--c-muted);font-size:.85rem;margin-top:.25rem;">Umsatztage</div>
        </div>
    </a>
</div>

{{-- ── Navigation ───────────────────────────────────────────────────────── --}}
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <a href="{{ route('admin.primeur.cash.monthly') }}" class="btn btn-outline">Monatsstatistik Kasse</a>
    <a href="{{ route('admin.primeur.stats.revenue') }}" class="btn btn-outline">Umsatzstatistik</a>
    <a href="{{ route('admin.primeur.stats.customers') }}" class="btn btn-outline">Kundenumsatz</a>
    <a href="{{ route('admin.primeur.articles.index') }}" class="btn btn-outline">Artikel-Übersicht</a>
    <a href="{{ route('admin.primeur.stats.articles') }}" class="btn btn-outline">Artikel-Ranking</a>
</div>

{{-- ── Jahresumsätze (Kasse) ────────────────────────────────────────────── --}}
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;margin-bottom:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h2 style="margin:0;font-size:1.1rem;">Kassenumsatz nach Jahr</h2>
        <a href="{{ route('admin.primeur.cash.export.monthly') }}" class="btn btn-sm btn-outline">CSV alle Jahre</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Jahr</th>
                    <th style="text-align:right;">Brutto-Umsatz</th>
                    <th style="text-align:right;">Storno</th>
                    <th style="text-align:right;">Netto-Umsatz</th>
                    <th style="text-align:right;">Anzahl Belege</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($yearlyTurnover as $row)
                <tr>
                    <td><strong>{{ $row->jahr }}</strong></td>
                    <td style="text-align:right;">{{ number_format($row->umsatz_brutto, 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:var(--c-danger,#dc2626);">{{ number_format($row->storno_summe, 2, ',', '.') }} €</td>
                    <td style="text-align:right;font-weight:600;color:var(--c-success,#16a34a);">{{ number_format($row->umsatz_netto, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($row->anzahl_belege) }}</td>
                    <td>
                        <a href="{{ route('admin.primeur.cash.daily', ['jahr' => $row->jahr]) }}" style="font-size:.8rem;">Details</a>
                        &nbsp;
                        <a href="{{ route('admin.primeur.cash.export.daily', ['jahr' => $row->jahr]) }}" style="font-size:.8rem;">CSV</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ── Monatsumsätze 2024 ───────────────────────────────────────────────── --}}
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;margin-bottom:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h2 style="margin:0;font-size:1.1rem;">Kassenumsatz 2024 – Monatsübersicht</h2>
        <a href="{{ route('admin.primeur.cash.export.daily', ['jahr' => 2024]) }}" class="btn btn-sm btn-outline">CSV 2024</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Monat</th>
                    <th style="text-align:right;">Umsatz</th>
                    <th style="text-align:right;">Storno</th>
                    <th style="text-align:right;">Netto</th>
                    <th style="text-align:right;">Karte</th>
                    <th style="text-align:right;">Bar</th>
                    <th style="text-align:right;">Belege</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthlyTurnover2024 as $row)
                <tr>
                    <td>{{ $row->monat }}</td>
                    <td style="text-align:right;">{{ number_format($row->umsatz, 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:var(--c-danger,#dc2626);">{{ number_format($row->storno, 2, ',', '.') }} €</td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($row->umsatz - $row->storno, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($row->kartenzahlung, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($row->bar, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($row->anzahl_belege) }}</td>
                </tr>
                @endforeach
            </tbody>
            @if($monthlyTurnover2024->isNotEmpty())
            <tfoot>
                <tr style="font-weight:700;border-top:2px solid var(--c-border,#e2e8f0);">
                    <td>Gesamt 2024</td>
                    <td style="text-align:right;">{{ number_format($monthlyTurnover2024->sum('umsatz'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:var(--c-danger,#dc2626);">{{ number_format($monthlyTurnover2024->sum('storno'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($monthlyTurnover2024->sum('umsatz') - $monthlyTurnover2024->sum('storno'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($monthlyTurnover2024->sum('kartenzahlung'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($monthlyTurnover2024->sum('bar'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($monthlyTurnover2024->sum('anzahl_belege')) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- ── Import-Info ──────────────────────────────────────────────────────── --}}
@if($lastRun)
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1rem 1.5rem;font-size:.85rem;color:var(--c-muted);">
    Letzter Import: <strong>{{ $lastRun->phase }}</strong> –
    Status: <strong>{{ $lastRun->status }}</strong> –
    {{ $lastRun->finished_at?->format('d.m.Y H:i') }} –
    {{ number_format($lastRun->records_imported) }} Datensätze
    @if($lastRun->notes)
        – {{ $lastRun->notes }}
    @endif
</div>
@endif
@endsection
