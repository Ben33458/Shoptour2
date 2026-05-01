@extends('admin.layout')

@section('title', 'Primeur – Kasse Tagesübersicht ' . $year)

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem;">
    <div>
        <h1 style="margin:0;">Kassenumsatz – Tagesübersicht</h1>
        <p style="margin:.25rem 0 0;color:var(--c-muted);font-size:.9rem;">
            <a href="{{ route('admin.primeur.dashboard') }}">Primeur-Archiv</a> › Kasse › Tage
        </p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="{{ route('admin.primeur.cash.export.daily', ['jahr' => $year]) }}" class="btn btn-outline">CSV {{ $year }}</a>
        <a href="{{ route('admin.primeur.cash.export.monthly') }}" class="btn btn-outline">CSV alle Monate</a>
    </div>
</div>

{{-- ── Filter ───────────────────────────────────────────────────────────── --}}
<form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0;align-items:center;">
    <select name="jahr" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
        @foreach($years as $y)
        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
        @endforeach
    </select>
    <select name="monat" style="padding:.4rem .75rem;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;">
        <option value="">Alle Monate</option>
        @foreach(range(1,12) as $m)
        <option value="{{ $m }}" @selected($m == $month)>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }} – {{ \Carbon\Carbon::createFromDate($year, $m, 1)->translatedFormat('F') }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-primary">Anzeigen</button>
</form>

{{-- ── Monatssummen ─────────────────────────────────────────────────────── --}}
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;margin-bottom:1rem;">
    <h2 style="margin:0 0 1rem;font-size:1.05rem;">Monatssummen {{ $year }}</h2>
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.9rem;">
            <thead>
                <tr>
                    <th>Monat</th>
                    <th style="text-align:right;">Brutto-Umsatz</th>
                    <th style="text-align:right;">Storno</th>
                    <th style="text-align:right;">Netto-Umsatz</th>
                    <th style="text-align:right;">Karte</th>
                    <th style="text-align:right;">Bar</th>
                    <th style="text-align:right;">Belege</th>
                    <th style="text-align:right;">Tage</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthly as $m)
                <tr>
                    <td><a href="?jahr={{ $year }}&monat={{ (int)$m->m }}">{{ $m->monat }}</a></td>
                    <td style="text-align:right;">{{ number_format($m->umsatz_brutto, 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:var(--c-danger,#dc2626);">{{ number_format($m->storno, 2, ',', '.') }} €</td>
                    <td style="text-align:right;font-weight:600;color:var(--c-success,#16a34a);">{{ number_format($m->umsatz_netto, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($m->karte, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($m->bar, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($m->anzahl_belege) }}</td>
                    <td style="text-align:right;">{{ $m->anzahl_tage }}</td>
                </tr>
                @endforeach
            </tbody>
            @if($monthly->isNotEmpty())
            <tfoot>
                <tr style="font-weight:700;border-top:2px solid var(--c-border,#e2e8f0);">
                    <td>Gesamt {{ $year }}</td>
                    <td style="text-align:right;">{{ number_format($monthly->sum('umsatz_brutto'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:var(--c-danger,#dc2626);">{{ number_format($monthly->sum('storno'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:var(--c-success,#16a34a);">{{ number_format($monthly->sum('umsatz_netto'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($monthly->sum('karte'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($monthly->sum('bar'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($monthly->sum('anzahl_belege')) }}</td>
                    <td style="text-align:right;">{{ $monthly->sum('anzahl_tage') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- ── MwSt-Aufschlüsselung (Warenebene aus Belegpositionen) ───────────── --}}
@if($mwstMonthly->isNotEmpty())
@php
    $vollLabel = $mwstMonthly->whereNotNull('satz_voll')->first()?->satz_voll;
    $vollPct   = $vollLabel ? number_format($vollLabel * 100, 0) . ' %' : '19 %';
    $hasErm    = $mwstMonthly->sum('brutto_erm') > 0;
@endphp
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;margin-bottom:1.5rem;">
    <h2 style="margin:0 0 .4rem;font-size:1.05rem;">MwSt-Aufschlüsselung {{ $year }}</h2>
    <p style="margin:0 0 1rem;font-size:.8rem;color:var(--c-muted);">
        Warenebene aus Kassenbelegen (Summe der Belegpositionen, ohne Pfand). Die Beträge hier sind <strong>höher</strong> als der Brutto-Umsatz in der Tabelle oben, da die Tagessummen den Netto-Kassenstand abbilden (nach Wechselgeld, Pfandsaldo, Abschöpfungen), während hier der Warenwert zu Verkaufspreisen gezählt wird.
    </p>
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.88rem;">
            <thead>
                <tr>
                    <th rowspan="2" style="vertical-align:bottom;">Monat</th>
                    <th colspan="3" style="text-align:center;border-bottom:1px solid var(--c-border,#e2e8f0);padding-bottom:.2rem;">{{ $vollPct }} (Regelsteuersatz)</th>
                    @if($hasErm)
                    <th colspan="3" style="text-align:center;border-bottom:1px solid var(--c-border,#e2e8f0);padding-bottom:.2rem;">7 % (ermäßigt)</th>
                    @endif
                </tr>
                <tr>
                    <th style="text-align:right;color:var(--c-muted);">Brutto</th>
                    <th style="text-align:right;color:var(--c-muted);">MwSt</th>
                    <th style="text-align:right;color:var(--c-muted);">Netto</th>
                    @if($hasErm)
                    <th style="text-align:right;color:var(--c-muted);">Brutto</th>
                    <th style="text-align:right;color:var(--c-muted);">MwSt</th>
                    <th style="text-align:right;color:var(--c-muted);">Netto</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($monthly as $m)
                @php $mx = $mwstMonthly->get($m->monat); @endphp
                <tr>
                    <td>{{ $m->monat }}</td>
                    <td style="text-align:right;">{{ $mx ? number_format($mx->brutto_voll, 2, ',', '.') : '–' }} €</td>
                    <td style="text-align:right;color:var(--c-muted);">{{ $mx ? number_format($mx->mwst_voll, 2, ',', '.') : '–' }} €</td>
                    <td style="text-align:right;font-weight:600;">{{ $mx ? number_format($mx->brutto_voll - $mx->mwst_voll, 2, ',', '.') : '–' }} €</td>
                    @if($hasErm)
                    <td style="text-align:right;">{{ $mx ? number_format($mx->brutto_erm, 2, ',', '.') : '–' }} €</td>
                    <td style="text-align:right;color:var(--c-muted);">{{ $mx ? number_format($mx->mwst_erm, 2, ',', '.') : '–' }} €</td>
                    <td style="text-align:right;font-weight:600;">{{ $mx ? number_format($mx->brutto_erm - $mx->mwst_erm, 2, ',', '.') : '–' }} €</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:700;border-top:2px solid var(--c-border,#e2e8f0);">
                    <td>Gesamt {{ $year }}</td>
                    <td style="text-align:right;">{{ number_format($mwstMonthly->sum('brutto_voll'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:var(--c-muted);">{{ number_format($mwstMonthly->sum('mwst_voll'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($mwstMonthly->sum('brutto_voll') - $mwstMonthly->sum('mwst_voll'), 2, ',', '.') }} €</td>
                    @if($hasErm)
                    <td style="text-align:right;">{{ number_format($mwstMonthly->sum('brutto_erm'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:var(--c-muted);">{{ number_format($mwstMonthly->sum('mwst_erm'), 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($mwstMonthly->sum('brutto_erm') - $mwstMonthly->sum('mwst_erm'), 2, ',', '.') }} €</td>
                    @endif
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

{{-- ── Tagesdaten ───────────────────────────────────────────────────────── --}}
@if(!empty($month) || count($days) <= 50)
<div style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.25rem;">
    <h2 style="margin:0 0 1rem;font-size:1.05rem;">Tagesdaten {{ $month ? sprintf('%02d', $month) . '/' . $year : $year }}</h2>
    <div style="overflow-x:auto;">
        <table class="table" style="font-size:.85rem;">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Tag</th>
                    <th style="text-align:right;">Brutto</th>
                    <th style="text-align:right;">Storno</th>
                    <th style="text-align:right;">Netto</th>
                    <th style="text-align:right;">Karte</th>
                    <th style="text-align:right;">Bar</th>
                    <th style="text-align:right;">Belege</th>
                </tr>
            </thead>
            <tbody>
                @foreach($days as $d)
                <tr>
                    <td style="white-space:nowrap;">{{ $d->datum->format('d.m.Y') }}</td>
                    <td style="color:var(--c-muted);font-size:.8rem;">{{ $d->datum->translatedFormat('D') }}</td>
                    @php $brutto = ($d->barbetrag ?? 0) + ($d->kartenbetrag ?? 0); $storno = ($d->storno_belege ?? 0) + ($d->storno_karte ?? 0) + ($d->storno_scheck ?? 0); @endphp
                    <td style="text-align:right;">{{ number_format($brutto, 2, ',', '.') }} €</td>
                    <td style="text-align:right;color:{{ $storno > 0 ? 'var(--c-danger,#dc2626)' : 'inherit' }};">
                        {{ number_format($storno, 2, ',', '.') }} €
                    </td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($brutto - $storno, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($d->kartenbetrag, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">{{ number_format($d->barbetrag, 2, ',', '.') }} €</td>
                    <td style="text-align:right;">—</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<p style="color:var(--c-muted);font-size:.9rem;text-align:center;padding:1rem;">
    {{ count($days) }} Tage – bitte einen Monat filtern für Tagesdetails.
</p>
@endif
@endsection
