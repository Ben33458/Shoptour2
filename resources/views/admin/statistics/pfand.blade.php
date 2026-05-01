@extends('admin.layout')

@section('title', 'Pfand-Statistik')

@section('actions')
    <a href="{{ route('admin.statistics.pos_top') }}" class="btn btn-outline btn-sm">Top-Artikel</a>
    <a href="{{ route('admin.statistics.purchase_planning') }}" class="btn btn-outline btn-sm">Einkaufsplanung</a>
    <a href="{{ route('admin.statistics.warengruppen') }}" class="btn btn-outline btn-sm">Warengruppen</a>
@endsection

@section('content')

{{-- ── Gesamtsaldo ── --}}
<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">
    <div class="card" style="flex:1;min-width:180px;padding:16px 24px;text-align:center;border-left:4px solid #d97706">
        <div style="font-size:2rem;font-weight:700;color:#92400e">
            {{ number_format($gesamtsaldo['saldo_stk'], 0, ',', '.') }}
        </div>
        <div style="font-size:.85em;color:var(--c-muted)">Stück im Umlauf (gesamt)</div>
        <div style="font-size:.75em;color:var(--c-muted)">Pfand ausgegeben − Leergut zurück</div>
    </div>
    <div class="card" style="flex:1;min-width:180px;padding:16px 24px;text-align:center;border-left:4px solid #16a34a">
        <div style="font-size:2rem;font-weight:700;color:#166534">
            {{ number_format($gesamtsaldo['saldo_eur'], 2, ',', '.') }} €
        </div>
        <div style="font-size:.85em;color:var(--c-muted)">Pfand-Saldo in Euro (gesamt)</div>
        <div style="font-size:.75em;color:var(--c-muted)">Ausstehende Pfandbeträge bei Kunden</div>
    </div>
</div>

{{-- ── Wochentrend ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <span>
            📈 Wochentrend — Pfand-Ausgabe vs. Leergut-Rücknahme
            <span style="font-size:.82em;font-weight:400;color:var(--c-muted);margin-left:6px">{{ $trendFrom }} – {{ $trendTo }}</span>
        </span>
        <form method="GET" action="{{ route('admin.statistics.pfand') }}"
              style="display:flex;gap:8px;align-items:center">
            <label class="form-label" style="margin:0;white-space:nowrap">Letzte</label>
            <select name="wochen" class="form-control" style="width:80px" onchange="this.form.submit()">
                @foreach([12, 24, 36, 52] as $opt)
                    <option value="{{ $opt }}" {{ $weeksBack == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
            <label class="form-label" style="margin:0">KW</label>
        </form>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.875em;white-space:nowrap">
            <thead>
                <tr>
                    <th>KW</th>
                    <th class="text-right" style="color:#16a34a">Pfand aus (Stk)</th>
                    <th class="text-right" style="color:#dc2626">Leergut zurück (Stk)</th>
                    <th class="text-right">KW-Netto (Stk)</th>
                    <th class="text-right">KW-Netto (€)</th>
                    <th class="text-right" style="color:#d97706">Kumulierter Saldo (Stk)</th>
                    <th class="text-right" style="color:#d97706">Kumulierter Saldo (€)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($trend['labels'] as $i => $label)
                    @php
                        $netto    = $trend['kwNetto'][$i];
                        $nettoEur = $trend['kwNettoEur'][$i];
                        $cumStk   = $trend['cumStk'][$i];
                        $cumEur   = $trend['cumEur'][$i];
                        $isCurrentWeek = $label === now()->format('Y') . '-KW' . now()->format('W');
                    @endphp
                    <tr {{ $isCurrentWeek ? 'style=font-weight:600;background:rgba(217,119,6,.06)' : '' }}>
                        <td>{{ $label }}{{ $isCurrentWeek ? ' ◀' : '' }}</td>
                        <td class="text-right" style="color:#16a34a">
                            {{ $trend['pfandOut'][$i] > 0 ? number_format($trend['pfandOut'][$i], 0, ',', '.') : '–' }}
                        </td>
                        <td class="text-right" style="color:#dc2626">
                            {{ $trend['leergutIn'][$i] > 0 ? number_format($trend['leergutIn'][$i], 0, ',', '.') : '–' }}
                        </td>
                        <td class="text-right">
                            @if($netto != 0)
                                <span style="color:{{ $netto > 0 ? '#16a34a' : '#dc2626' }}">
                                    {{ $netto > 0 ? '+' : '' }}{{ number_format($netto, 0, ',', '.') }}
                                </span>
                            @else
                                <span style="color:var(--c-muted)">0</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($nettoEur != 0)
                                <span style="color:{{ $nettoEur > 0 ? '#16a34a' : '#dc2626' }}">
                                    {{ $nettoEur > 0 ? '+' : '' }}{{ number_format($nettoEur, 2, ',', '.') }} €
                                </span>
                            @else
                                <span style="color:var(--c-muted)">–</span>
                            @endif
                        </td>
                        <td class="text-right" style="color:#92400e;font-weight:500">
                            {{ number_format($cumStk, 0, ',', '.') }}
                        </td>
                        <td class="text-right" style="color:#92400e;font-weight:500">
                            {{ number_format($cumEur, 2, ',', '.') }} €
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ── Saldo je Artikel ── --}}
<div class="card">
    <div class="card-header">🫙 Saldo je Pfandartikel (gesamt, alle Zeiten)</div>
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.875em">
            <thead>
                <tr>
                    <th>Artikel</th>
                    <th class="text-right">Pfandpreis</th>
                    <th class="text-right" style="color:#16a34a">Ausgegangen (Stk)</th>
                    <th class="text-right" style="color:#dc2626">Zurückgekommen (Stk)</th>
                    <th class="text-right">Saldo (Stk)</th>
                    <th class="text-right" style="color:#d97706">Saldo (€)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($saldoItems as $item)
                    @php $saldoColor = $item->saldo_stk > 0 ? '#92400e' : '#16a34a'; @endphp
                    <tr>
                        <td>
                            <div style="font-weight:500">{{ $item->pfand_label ?: ('Pfand ' . ucfirst($item->artikel_key)) }}</div>
                            <div style="font-size:.8em;color:var(--c-muted)">↩ {{ $item->leergut_label ?: ('Leergut ' . ucfirst($item->artikel_key)) }}</div>
                        </td>
                        <td class="text-right">{{ number_format($item->pfand_preis, 2, ',', '.') }} €</td>
                        <td class="text-right" style="color:#16a34a">
                            {{ number_format($item->pfand_out, 0, ',', '.') }}
                        </td>
                        <td class="text-right" style="color:#dc2626">
                            {{ number_format($item->leergut_in, 0, ',', '.') }}
                        </td>
                        <td class="text-right">
                            <strong style="color:{{ $saldoColor }}">
                                {{ $item->saldo_stk > 0 ? '+' : '' }}{{ number_format($item->saldo_stk, 0, ',', '.') }}
                            </strong>
                        </td>
                        <td class="text-right">
                            <strong style="color:{{ $saldoColor }}">
                                {{ $item->saldo_eur > 0 ? '+' : '' }}{{ number_format($item->saldo_eur, 2, ',', '.') }} €
                            </strong>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:700;border-top:2px solid var(--c-border,#e5e7eb)">
                    <td colspan="4">Gesamt</td>
                    <td class="text-right" style="color:#92400e">
                        {{ number_format($saldoItems->sum('saldo_stk'), 0, ',', '.') }}
                    </td>
                    <td class="text-right" style="color:#92400e">
                        {{ number_format($saldoItems->sum('saldo_eur'), 2, ',', '.') }} €
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@endsection
