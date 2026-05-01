@extends('admin.layout')

@section('title', 'Primeur – Kassenumsatz Monatsübersicht')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
    <div>
        <h1 style="margin:0;">Kassenumsatz – Alle Monate 2015–2024</h1>
        <p style="margin:.25rem 0 0;color:var(--c-muted);font-size:.9rem;">
            <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> › Kasse › Monatsübersicht
        </p>
    </div>
    <div style="display:flex;gap:.5rem;">
        <a href="{{ route('admin.primeur.cash.export.monthly') }}" class="btn btn-outline">CSV alle Monate</a>
        <a href="{{ route('admin.primeur.stats.revenue') }}" class="btn btn-outline">Jahresvergleich</a>
    </div>
</div>

<p style="color:var(--c-warning,#d97706);font-size:.85rem;background:var(--c-warning-bg,#fef9c3);border:1px solid var(--c-warning,#d97706);border-radius:6px;padding:.6rem 1rem;margin:1rem 0;">
    <strong>Hinweis:</strong> Die Storno-Spalte kann anomale Werte enthalten (besonders 2019). Bitte individuelle Tages- und Monatswerte gegen die Originalbelege prüfen.
</p>

<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.85rem;">
            <thead>
                <tr>
                    <th>Monat</th>
                    <th>Jahr</th>
                    <th style="text-align:right;">Brutto-Umsatz</th>
                    <th style="text-align:right;">Storno</th>
                    <th style="text-align:right;">Netto-Umsatz</th>
                    <th style="text-align:right;">Karte</th>
                    <th style="text-align:right;">Bar</th>
                    <th style="text-align:right;">Belege</th>
                    <th style="text-align:right;">Ø Bon</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @php $currentYear = null; $yearTotal = 0; $rows = $monthly->all(); @endphp
                @foreach($rows as $i => $m)
                    @if($currentYear && $currentYear !== $m->jahr)
                    <tr style="background:var(--c-bg,#f8fafc);font-weight:700;font-size:.9rem;">
                        <td colspan="2" style="color:var(--c-muted);">Summe {{ $currentYear }}</td>
                        @php
                            $yearRows = $monthly->where('jahr', $currentYear);
                        @endphp
                        <td style="text-align:right;">{{ number_format($yearRows->sum('umsatz_brutto'), 2, ',', '.') }} €</td>
                        <td style="text-align:right;">{{ number_format($yearRows->sum('storno'), 2, ',', '.') }} €</td>
                        <td style="text-align:right;color:var(--c-success,#16a34a);">{{ number_format($yearRows->sum('umsatz_netto'), 2, ',', '.') }} €</td>
                        <td style="text-align:right;">{{ number_format($yearRows->sum('karte'), 2, ',', '.') }} €</td>
                        <td style="text-align:right;">{{ number_format($yearRows->sum('bar'), 2, ',', '.') }} €</td>
                        <td style="text-align:right;">{{ number_format($yearRows->sum('anzahl_belege')) }}</td>
                        <td colspan="2"></td>
                    </tr>
                    @endif
                    @php $currentYear = $m->jahr; @endphp
                <tr>
                    <td>{{ $m->monat }}</td>
                    <td style="color:var(--c-muted);">{{ $m->jahr }}</td>
                    <td style="text-align:right;">{{ number_format($m->umsatz_brutto, 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:{{ abs($m->storno) > 5000 ? 'var(--c-danger,#dc2626)' : 'inherit' }};">{{ number_format($m->storno, 2, ',', '.') }} €</td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($m->umsatz_netto, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($m->karte, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($m->bar, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($m->anzahl_belege) }}</td>
                    <td style="text-align:right;">{{ $m->avg_bon ? number_format($m->avg_bon, 2, ',', '.') . ' €' : '—' }}</td>
                    <td><a href="{{ route('admin.primeur.cash.daily', ['jahr' => $m->jahr, 'monat' => $m->monat_nr]) }}" style="font-size:.8rem;">Detail</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
