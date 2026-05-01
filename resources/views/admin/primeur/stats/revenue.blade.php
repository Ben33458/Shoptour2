@extends('admin.layout')

@section('title', 'Primeur – Umsatzstatistik')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
    <div>
        <h1 style="margin:0;">Umsatzstatistik Primeur-Archiv</h1>
        <p style="margin:.25rem 0 0;color:var(--c-muted);font-size:.9rem;">
            <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> › Statistik › Umsatz
        </p>
    </div>
    <div style="display:flex;gap:.5rem;">
        <a href="{{ route('admin.primeur.cash.export.monthly') }}" class="btn btn-outline">CSV Kassenumsatz</a>
        <a href="{{ route('admin.primeur.stats.customers.export') }}" class="btn btn-outline">CSV Kundenumsatz</a>
    </div>
</div>

{{-- ── Kassenumsatz Jahresvergleich ──────────────────────────────────────── --}}
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;margin:1.5rem 0;">
    <h2 style="margin:0 0 1rem;font-size:1.1rem;">Kassenumsatz nach Jahr (Tagesabrechnung)</h2>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Jahr</th>
                    <th style="text-align:right;">Brutto</th>
                    <th style="text-align:right;">Storno</th>
                    <th style="text-align:right;">Netto</th>
                    <th style="text-align:right;">Kartenz.</th>
                    <th style="text-align:right;">Bar</th>
                    <th style="text-align:right;">Belege</th>
                    <th style="text-align:right;">Öffnungstage</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cashYearly as $row)
                <tr>
                    <td><strong>{{ $row->jahr }}</strong></td>
                    <td style="text-align:right;">{{ number_format($row->umsatz_brutto, 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:var(--c-danger,#dc2626);">{{ number_format($row->storno, 2, ',', '.') }} €</td>
                    <td style="text-align:right;font-weight:700;color:var(--c-success,#16a34a);">{{ number_format($row->umsatz_netto, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($row->kartenzahlung, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($row->barzahlung, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($row->anzahl_belege) }}</td>
                    <td style="text-align:right;">{{ $row->anzahl_tage }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ── Monatsumsätze nach Jahr (Kasse) ─────────────────────────────────── --}}
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;margin-bottom:1.5rem;">
    <h2 style="margin:0 0 1rem;font-size:1.1rem;">Monatlicher Netto-Kassenumsatz nach Jahr</h2>
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.85rem;">
            <thead>
                <tr>
                    <th>Monat</th>
                    @foreach($cashMonthly->keys() as $year)
                    <th style="text-align:right;">{{ $year }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach(range(1,12) as $m)
                <tr>
                    <td>{{ \Carbon\Carbon::createFromDate(2024, $m, 1)->translatedFormat('F') }}</td>
                    @foreach($cashMonthly->keys() as $year)
                        @php $monthRow = $cashMonthly[$year]->firstWhere('monat_nr', $m); @endphp
                        <td style="text-align:right;">{{ $monthRow ? number_format($monthRow->umsatz_netto, 2, ',', '.') . ' €' : '—' }}</td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:700;border-top:2px solid var(--c-border,#e2e8f0);">
                    <td>Gesamt</td>
                    @foreach($cashMonthly as $year => $months)
                    <td style="text-align:right;">{{ number_format($months->sum('umsatz_netto'), 2, ',', '.') }} €</td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- ── Auftrags-Jahresumsatz ────────────────────────────────────────────── --}}
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;">
    <h2 style="margin:0 0 1rem;font-size:1.1rem;">Auftrags-/Rechnungsumsatz nach Jahr (Tb_Auftr)</h2>
    <p style="color:var(--c-muted);font-size:.85rem;margin:0 0 1rem;">Lieferscheine + Rechnungen, ohne Stornos</p>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Jahr</th>
                    <th style="text-align:right;">Anzahl Aufträge</th>
                    <th style="text-align:right;">Umsatz</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orderYearly as $row)
                <tr>
                    <td>{{ $row->jahr }}</td>
                    <td style="text-align:right;">{{ number_format($row->anzahl) }}</td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($row->umsatz, 2, ',', '.') }} €</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
