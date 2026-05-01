@extends('admin.layout')

@section('title', 'MHD-Abschreibungen')

@section('actions')
    <a href="{{ route('admin.statistics.pos_top') }}" class="btn btn-outline btn-sm">Top-Artikel</a>
    <a href="{{ route('admin.statistics.purchase_planning') }}" class="btn btn-outline btn-sm">Einkaufsplanung</a>
@endsection

@section('content')

{{-- ── Filter ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.statistics.mhd_abschreibungen') }}"
              style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">

            <div class="form-group" style="margin:0">
                <label class="form-label">Zeitraum</label>
                <select name="wochen" class="form-control" style="width:160px" onchange="this.form.submit()">
                    @foreach([4 => 'Letzte 4 KW', 8 => 'Letzte 8 KW', 12 => 'Letzte 12 KW', 26 => 'Letzte 26 KW', 52 => 'Letzte 52 KW', 104 => 'Letzte 2 Jahre'] as $val => $label)
                        <option value="{{ $val }}" {{ $weeksBack == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" style="margin:0">
                <label class="form-label">Warengruppe</label>
                <select name="warengruppe" class="form-control" style="min-width:180px">
                    <option value="">– Alle –</option>
                    @foreach($warengruppen as $wg)
                        <option value="{{ $wg }}" {{ $warengruppe === $wg ? 'selected' : '' }}>{{ $wg }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-sm">Anzeigen</button>
        </form>
    </div>
</div>

{{-- ── KPI-Kacheln ── --}}
<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">

    <div class="card" style="flex:1;min-width:160px;padding:14px 20px;text-align:center;border-left:4px solid #d97706">
        <div style="font-size:1.8rem;font-weight:700;color:#92400e">
            {{ number_format($totalWindow->menge ?? 0, 0, ',', '.') }}
        </div>
        <div style="font-size:.8em;color:var(--c-muted)">Stk ausgebucht ({{ $from }} – {{ $to }})</div>
    </div>

    <div class="card" style="flex:1;min-width:160px;padding:14px 20px;text-align:center;border-left:4px solid #dc2626">
        <div style="font-size:1.8rem;font-weight:700;color:#991b1b">
            {{ number_format($totalWindow->ek_warenwert ?? 0, 2, ',', '.') }} €
        </div>
        <div style="font-size:.8em;color:var(--c-muted)">EK-Warenwert ausgebucht</div>
    </div>

    <div class="card" style="flex:1;min-width:160px;padding:14px 20px;text-align:center">
        <div style="font-size:1.8rem;font-weight:700">
            {{ number_format($totalAll->menge ?? 0, 0, ',', '.') }}
        </div>
        <div style="font-size:.8em;color:var(--c-muted)">Stk gesamt (alle Jahre)</div>
    </div>

    <div class="card" style="flex:1;min-width:160px;padding:14px 20px;text-align:center">
        <div style="font-size:1.8rem;font-weight:700">
            {{ $totalAll->artikel_count ?? 0 }}
        </div>
        <div style="font-size:.8em;color:var(--c-muted)">Betroffene Artikel (alle Jahre)</div>
    </div>

</div>

{{-- ── KW-Trend ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>📅 Abschreibungen je Kalenderwoche</span>
        <span style="font-size:.82em;color:var(--c-muted)">{{ $from }} – {{ $to }}</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.8em;white-space:nowrap">
            <thead>
                <tr>
                    <th>KW</th>
                    <th>Zeitraum</th>
                    <th class="text-right">Menge (Stk)</th>
                    <th class="text-right">EK-Warenwert</th>
                    <th style="width:160px">Anteil</th>
                </tr>
            </thead>
            @php $maxMenge = max(array_map(fn($r) => $r->menge, $trend) ?: [1]); @endphp

            <tbody>
                @foreach(array_reverse($trend) as $row)
                    @php
                        [$isoY, $isoW] = [(int)now()->format('o'), (int)now()->format('W')];
                        $isCurrent = $row->yr === $isoY && $row->kw === $isoW;
                        $barWidth  = $maxMenge > 0 ? round(($row->menge / $maxMenge) * 120) : 0;
                    @endphp
                    <tr {{ $isCurrent ? 'style=font-weight:600;background:rgba(217,119,6,.06)' : ($row->menge == 0 ? 'style=color:var(--c-muted)' : '') }}>
                        <td>
                            <strong>KW {{ $row->kw }}</strong>/{{ $row->yr }}
                            @if($isCurrent) <span style="font-size:.75em;color:#d97706">◀</span> @endif
                        </td>
                        <td style="color:var(--c-muted)">{{ $row->kwFrom }} – {{ $row->kwTo }}</td>
                        <td class="text-right">
                            @if($row->menge > 0)
                                <strong style="color:#92400e">{{ number_format($row->menge, 0, ',', '.') }}</strong>
                            @else
                                <span style="color:var(--c-muted)">–</span>
                            @endif
                        </td>
                        <td class="text-right">
                            {{ $row->ek_warenwert > 0 ? number_format($row->ek_warenwert, 2, ',', '.') . ' €' : '–' }}
                        </td>
                        <td>
                            @if($row->menge > 0)
                                <div style="height:8px;background:rgba(220,38,38,.15);border-radius:2px">
                                    <div style="height:8px;width:{{ $barWidth }}px;max-width:120px;background:#dc2626;border-radius:2px;opacity:.7"></div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:600;border-top:2px solid var(--c-border,#e5e7eb)">
                    <td colspan="2">Gesamt (Fenster)</td>
                    <td class="text-right" style="color:#92400e">{{ number_format(array_sum(array_column($trend, 'menge')), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format(array_sum(array_column($trend, 'ek_warenwert')), 2, ',', '.') }} €</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

{{-- ── Nach Warengruppe ── --}}
<div class="card">
    <div class="card-header">📦 Nach Warengruppe</div>
    @if($byWarengruppe->isEmpty())
        <div class="card-body" style="color:var(--c-muted);font-size:.9em">Keine Daten im Zeitraum.</div>
    @else
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.875em">
            <thead>
                <tr>
                    <th>Warengruppe</th>
                    <th class="text-right">Artikel</th>
                    <th class="text-right">Menge</th>
                    <th class="text-right">EK-Warenwert</th>
                </tr>
            </thead>
            <tbody>
                @foreach($byWarengruppe as $row)
                    <tr>
                        <td>
                            <a href="{{ route('admin.statistics.mhd_abschreibungen', ['wochen' => $weeksBack, 'warengruppe' => $row->warengruppe]) }}"
                               style="color:inherit">{{ $row->warengruppe }}</a>
                        </td>
                        <td class="text-right" style="color:var(--c-muted)">{{ $row->artikel_count }}</td>
                        <td class="text-right"><strong style="color:#92400e">{{ number_format($row->menge, 0, ',', '.') }}</strong></td>
                        <td class="text-right">{{ number_format($row->ek_warenwert, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- ── Nach Artikel (Top 15) ── --}}
<div class="card">
    <div class="card-header">🏷️ Nach Artikel (Top 15 nach Menge)</div>
    @if($byArtikel->isEmpty())
        <div class="card-body" style="color:var(--c-muted);font-size:.9em">Keine Daten im Zeitraum.</div>
    @else
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.875em">
            <thead>
                <tr>
                    <th>Artikel</th>
                    <th class="text-right">Menge</th>
                    <th class="text-right" title="Einkaufspreis / Verkaufspreis je Einheit">EK / VK</th>
                    <th class="text-right">EK-Warenwert</th>
                    <th style="color:var(--c-muted);font-size:.85em">Zuletzt</th>
                </tr>
            </thead>
            <tbody>
                @foreach($byArtikel->take(15) as $row)
                    <tr>
                        <td>
                            <a href="{{ route('admin.statistics.artikel', ['artnr' => $row->artnr]) }}"
                               style="color:inherit;text-decoration:none" class="hover-underline">
                                {{ $row->name }}
                            </a>
                            @if($row->warengruppe)
                                <div style="font-size:.78em;color:var(--c-muted)">{{ $row->warengruppe }}</div>
                            @endif
                        </td>
                        <td class="text-right"><strong style="color:#92400e">{{ number_format($row->menge, 0, ',', '.') }}</strong></td>
                        <td class="text-right" style="white-space:nowrap;font-size:.82em">
                            @if($row->ek_preis !== null)
                                <span style="color:#92400e">{{ number_format($row->ek_preis, 2, ',', '.') }} €</span>
                                <span style="color:var(--c-muted)"> / </span>
                                <span>{{ $row->vk_preis !== null ? number_format($row->vk_preis, 2, ',', '.') . ' €' : '–' }}</span>
                            @else
                                <span style="color:var(--c-muted)">–</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($row->ek_warenwert > 0)
                                <strong>{{ number_format($row->ek_warenwert, 2, ',', '.') }} €</strong>
                            @else
                                <span style="color:var(--c-muted)">–</span>
                            @endif
                        </td>
                        <td style="font-size:.8em;color:var(--c-muted)">
                            {{ \Carbon\Carbon::parse($row->last_date)->format('d.m.Y') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

</div>

@endsection
