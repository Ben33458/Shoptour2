@extends('admin.layout')

@section('title', $meta->name)

@section('actions')
    <a href="{{ route('admin.statistics.pos_top') }}" class="btn btn-outline btn-sm">← Top-Artikel</a>
    @if($meta->warengruppe)
        <a href="{{ route('admin.statistics.pos_top', ['warengruppe' => $meta->warengruppe]) }}" class="btn btn-outline btn-sm">{{ $meta->warengruppe }}</a>
    @endif
    @if($catalogProduct)
        <a href="{{ route('admin.products.show', $catalogProduct) }}" class="btn btn-outline btn-sm">Katalog-Seite →</a>
    @endif
@endsection

@section('content')

{{-- ── KPI row ── --}}
<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">

    <div class="card" style="flex:1;min-width:150px;padding:14px 20px;text-align:center">
        <div style="font-size:1.8rem;font-weight:700">{{ number_format($currentWeekMenge, 0, ',', '.') }}</div>
        <div style="font-size:.8em;color:var(--c-muted)">Menge KW {{ $currentWeek }}/{{ $currentYear }}</div>
        @if($rank)
            <div style="font-size:.75em;color:var(--c-muted);margin-top:2px">
                Rang #{{ $rank['rank'] }} / {{ $rank['total'] }} in {{ $meta->warengruppe }}
            </div>
        @endif
    </div>

    <div class="card" style="flex:1;min-width:150px;padding:14px 20px;text-align:center">
        <div style="font-size:1.8rem;font-weight:700">{{ number_format($avg4w, 1, ',', '.') }}</div>
        <div style="font-size:.8em;color:var(--c-muted)">Ø Menge / Woche (4W)</div>
    </div>

    <div class="card" style="flex:1;min-width:150px;padding:14px 20px;text-align:center">
        <div style="font-size:1.8rem;font-weight:700">{{ number_format($avg8w, 1, ',', '.') }}</div>
        <div style="font-size:.8em;color:var(--c-muted)">Ø Menge / Woche (8W)</div>
    </div>

    <div class="card" style="flex:1;min-width:150px;padding:14px 20px;text-align:center">
        <div style="font-size:1.8rem;font-weight:700">{{ number_format($avg13w, 1, ',', '.') }}</div>
        <div style="font-size:.8em;color:var(--c-muted)">Ø Menge / Woche (13W)</div>
    </div>

    <div class="card" style="flex:1;min-width:150px;padding:14px 20px;text-align:center;border-left:4px solid
        {{ $reichweite === null ? '#9ca3af' : ($reichweite < 1 ? '#dc2626' : ($reichweite < 2 ? '#d97706' : '#16a34a')) }}">
        <div style="font-size:1.8rem;font-weight:700;color:
            {{ $reichweite === null ? 'var(--c-muted)' : ($reichweite < 1 ? '#dc2626' : ($reichweite < 2 ? '#d97706' : '#16a34a')) }}">
            {{ $reichweite !== null ? number_format($reichweite, 1, ',', '.') . ' W' : '–' }}
        </div>
        <div style="font-size:.8em;color:var(--c-muted)">Reichweite</div>
        <div style="font-size:.75em;color:var(--c-muted)">Bestand: {{ number_format($bestand, 0, ',', '.') }} Stk</div>
    </div>

</div>

{{-- ── 52-week trend table ── --}}
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <span>📈 Wochenabsatz — letzte 52 KW</span>
        <span style="font-size:.85em;color:var(--c-muted)">
            <span style="color:#6366f1">■</span> Aktuell &nbsp;
            <span style="color:#d1d5db">■</span> Vorjahr
        </span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.8em;white-space:nowrap">
            <thead>
                <tr>
                    <th>KW</th>
                    <th>Zeitraum</th>
                    <th class="text-right">Menge</th>
                    <th class="text-right" style="color:var(--c-muted)">Vorjahr</th>
                    <th class="text-right">Δ VJ</th>
                    <th class="text-right">Umsatz</th>
                </tr>
            </thead>
            <tbody>
                @php $totalMenge = 0; $totalUmsatz = 0; @endphp
                @foreach(array_reverse($trend) as $row)
                    @php
                        $lyKey  = sprintf('%04d-%02d', $row->year, $row->week);
                        $ly     = $lyMenge[$lyKey] ?? 0;
                        $diff   = $row->menge - $ly;
                        $pct    = $ly > 0 ? round(($diff / $ly) * 100, 1) : null;
                        $isCurr = $row->year === $currentYear && $row->week === $currentWeek;
                        $totalMenge  += $row->menge;
                        $totalUmsatz += $row->umsatz;
                    @endphp
                    <tr {{ $isCurr ? 'style=font-weight:600;background:rgba(99,102,241,.06)' : ($row->menge == 0 ? 'style=color:var(--c-muted)' : '') }}>
                        <td>
                            <strong>KW {{ $row->week }}</strong>/{{ $row->year }}
                            @if($isCurr) <span style="font-size:.75em;color:#6366f1">◀ aktuell</span> @endif
                        </td>
                        <td style="color:var(--c-muted)">{{ $row->mondayDate }} – {{ $row->sundayDate }}</td>
                        <td class="text-right">
                            @if($row->menge > 0)
                                <strong>{{ number_format($row->menge, 0, ',', '.') }}</strong>
                                @php
                                    $maxMenge = max(array_column($trend, 'menge'));
                                    $barWidth = $maxMenge > 0 ? round(($row->menge / $maxMenge) * 60) : 0;
                                @endphp
                                <span style="display:inline-block;width:{{ $barWidth }}px;height:6px;background:#6366f1;border-radius:2px;margin-left:4px;vertical-align:middle;opacity:.7"></span>
                            @else
                                <span style="color:var(--c-muted)">–</span>
                            @endif
                        </td>
                        <td class="text-right" style="color:var(--c-muted)">
                            {{ $ly > 0 ? number_format($ly, 0, ',', '.') : '–' }}
                        </td>
                        <td class="text-right">
                            @if($diff != 0 && $ly > 0)
                                <span style="color:{{ $diff > 0 ? '#16a34a' : '#dc2626' }}">
                                    {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 0, ',', '.') }}
                                    @if($pct !== null)
                                        <small>({{ $pct > 0 ? '+' : '' }}{{ $pct }}%)</small>
                                    @endif
                                </span>
                            @else
                                <span style="color:var(--c-muted)">–</span>
                            @endif
                        </td>
                        <td class="text-right">
                            {{ $row->umsatz > 0 ? number_format($row->umsatz, 2, ',', '.') . ' €' : '–' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:600;border-top:2px solid var(--c-border,#e5e7eb)">
                    <td colspan="2">Gesamt (52 KW)</td>
                    <td class="text-right">{{ number_format($totalMenge, 0, ',', '.') }}</td>
                    <td colspan="2"></td>
                    <td class="text-right">{{ number_format($totalUmsatz, 2, ',', '.') }} €</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- ── No catalog product info box ── --}}
@if(!$catalogProduct)
<div style="margin-top:20px;padding:14px 18px;background:rgba(99,102,241,.06);border-left:4px solid #6366f1;border-radius:4px;font-size:.9em;color:var(--c-muted)">
    ℹ️ Kein Katalogartikel mit Artikelnummer <code>{{ $artnr }}</code> verknüpft — MHD, Stammsortiment und Bestellungen nicht verfügbar.
</div>
@else

{{-- ── Sektion A: MHD-Chargen ── --}}
<div class="card" style="margin-top:20px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>📅 MHD-Chargen (aktive Bestände)</span>
        @if($mhdBatches->isNotEmpty())
            @php
                $kritisch = $mhdBatches->whereIn('mhd_risiko', ['abgelaufen','kritisch'])->count();
                $bald     = $mhdBatches->where('mhd_risiko', 'bald_ablaufend')->count();
            @endphp
            @if($kritisch > 0)
                <span style="background:#dc2626;color:#fff;border-radius:10px;padding:2px 10px;font-size:.78em">{{ $kritisch }} kritisch</span>
            @elseif($bald > 0)
                <span style="background:#d97706;color:#fff;border-radius:10px;padding:2px 10px;font-size:.78em">{{ $bald }} bald ablaufend</span>
            @else
                <span style="background:#16a34a;color:#fff;border-radius:10px;padding:2px 10px;font-size:.78em">alles ok</span>
            @endif
        @endif
    </div>
    @if($mhdBatches->isEmpty())
        <div class="card-body" style="color:var(--c-muted);font-size:.9em">Keine aktiven MHD-Chargen erfasst.</div>
    @else
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.875em">
            <thead>
                <tr>
                    <th>MHD</th>
                    <th>Risiko</th>
                    <th>Lager</th>
                    <th>Segment</th>
                    <th class="text-right">Menge</th>
                    <th>Lagerplatz</th>
                    <th>Notiz</th>
                </tr>
            </thead>
            <tbody>
                @foreach($mhdBatches as $batch)
                    @php
                        $rowBg = match($batch->mhd_risiko) {
                            'abgelaufen','kritisch' => 'background:rgba(220,38,38,.07)',
                            'bald_ablaufend'        => 'background:rgba(217,119,6,.07)',
                            default                 => '',
                        };
                        $risikoColor = match($batch->mhd_risiko) {
                            'abgelaufen','kritisch' => '#dc2626',
                            'bald_ablaufend'        => '#d97706',
                            default                 => '#16a34a',
                        };
                        $risikoLabel = match($batch->mhd_risiko) {
                            'abgelaufen'     => 'Abgelaufen',
                            'kritisch'       => 'Kritisch',
                            'bald_ablaufend' => 'Bald ablaufend',
                            default          => 'OK',
                        };
                    @endphp
                    <tr style="{{ $rowBg }}">
                        <td><strong>{{ \Carbon\Carbon::parse($batch->mhd)->format('d.m.Y') }}</strong></td>
                        <td>
                            <span style="color:{{ $risikoColor }};font-weight:500;font-size:.85em">{{ $risikoLabel }}</span>
                        </td>
                        <td style="color:var(--c-muted)">{{ $batch->warehouse?->name ?? '–' }}</td>
                        <td style="font-size:.85em;color:var(--c-muted)">{{ ucfirst($batch->segment) }}</td>
                        <td class="text-right"><strong>{{ number_format($batch->menge, 0, ',', '.') }}</strong></td>
                        <td style="font-size:.85em;color:var(--c-muted)">{{ $batch->lagerplatz ?? '–' }}</td>
                        <td style="font-size:.85em;color:var(--c-muted)">{{ $batch->notiz ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:600;border-top:2px solid var(--c-border,#e5e7eb)">
                    <td colspan="4">Gesamt</td>
                    <td class="text-right">{{ number_format($mhdBatches->sum('menge'), 0, ',', '.') }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>

{{-- ── Sektion B: Stammsortiment ── --}}
<div class="card" style="margin-top:20px">
    <div class="card-header">
        🛒 Stammsortiment ({{ $stammsortiment->count() }} {{ $stammsortiment->count() === 1 ? 'Kunde' : 'Kunden' }})
    </div>
    @if($stammsortiment->isEmpty())
        <div class="card-body" style="color:var(--c-muted);font-size:.9em">Kein Kunde hat diesen Artikel im Stammsortiment.</div>
    @else
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.875em">
            <thead>
                <tr>
                    <th>Kunde</th>
                    <th class="text-right">Soll-Bestand</th>
                    <th class="text-right">Ist-Bestand</th>
                    <th class="text-right">Differenz</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stammsortiment as $fav)
                    @php $diff = $fav->target_stock_units - $fav->actual_stock_units; @endphp
                    <tr>
                        <td>{{ $fav->customer?->displayName() ?? '–' }}</td>
                        <td class="text-right">{{ $fav->target_stock_units }}</td>
                        <td class="text-right">{{ $fav->actual_stock_units }}</td>
                        <td class="text-right">
                            @if($diff > 0)
                                <span style="color:#dc2626;font-weight:500">−{{ $diff }}</span>
                            @elseif($diff < 0)
                                <span style="color:#16a34a">+{{ abs($diff) }}</span>
                            @else
                                <span style="color:var(--c-muted)">0</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- ── Sektion C: Letzte Bestellungen ── --}}
<div class="card" style="margin-top:20px">
    <div class="card-header">📦 Letzte Bestellungen — echte Verkäufe (max. 20)</div>
    @if($recentOrders->isEmpty())
        <div class="card-body" style="color:var(--c-muted);font-size:.9em">Keine Bestellungen vorhanden.</div>
    @else
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.875em">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Bestellnr.</th>
                    <th>Kunde</th>
                    <th class="text-right">Menge</th>
                    <th class="text-right">EP netto</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentOrders as $item)
                    @php
                        $statusColor = match($item->order?->status) {
                            'delivered' => '#16a34a',
                            'confirmed' => '#2563eb',
                            'shipped'   => '#7c3aed',
                            'cancelled' => '#dc2626',
                            default     => '#9ca3af',
                        };
                        $statusLabel = match($item->order?->status) {
                            'pending'   => 'Offen',
                            'confirmed' => 'Bestätigt',
                            'shipped'   => 'Versendet',
                            'delivered' => 'Geliefert',
                            'cancelled' => 'Storniert',
                            default     => $item->order?->status ?? '–',
                        };
                        $priceEur = $item->unit_price_net_milli / 1_000_000;
                    @endphp
                    <tr>
                        <td style="color:var(--c-muted)">{{ $item->order?->created_at?->format('d.m.Y') ?? '–' }}</td>
                        <td>
                            @if($item->order)
                                <a href="{{ route('admin.orders.show', $item->order) }}" style="font-size:.85em">
                                    {{ $item->order->order_number ?? '#'.$item->order->id }}
                                </a>
                            @else –
                            @endif
                        </td>
                        <td>{{ $item->order?->customer?->displayName() ?? '–' }}</td>
                        <td class="text-right"><strong>{{ $item->qty }}</strong></td>
                        <td class="text-right">{{ number_format($priceEur, 2, ',', '.') }} €</td>
                        <td>
                            <span style="color:{{ $statusColor }};font-size:.82em;font-weight:500">{{ $statusLabel }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- ── Sektion D: MHD-Abschreibungen (POS Kunde K3475) ── --}}
@if($mhdWriteoffTotal > 0 || $mhdWriteoffLast52 > 0)
<div style="margin-top:16px;padding:12px 18px;background:rgba(217,119,6,.07);border-left:4px solid #d97706;border-radius:4px;font-size:.875em">
    <strong style="color:#92400e">⚠️ MHD-Abschreibungen (Kasse Kunde K3475 — kein Verkauf)</strong>
    <div style="margin-top:4px;display:flex;gap:24px;flex-wrap:wrap">
        <span>Letzte 52 KW: <strong>{{ number_format($mhdWriteoffLast52, 0, ',', '.') }} Stk</strong> ausgebucht</span>
        <span style="color:var(--c-muted)">Gesamt (alle Jahre): {{ number_format($mhdWriteoffTotal, 0, ',', '.') }} Stk</span>
    </div>
    <div style="margin-top:4px;font-size:.82em;color:var(--c-muted)">
        Kassenbuchungen über K3475 sind aus dem Absatz- und Umsatztrend oben herausgerechnet.
    </div>
</div>
@endif

@endif {{-- end $catalogProduct --}}

@endsection
