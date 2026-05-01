@extends('admin.layout')

@section('title', 'Warengruppen-Analyse (letzte 12 KW)')

@section('actions')
    <a href="{{ route('admin.statistics.pos_top') }}" class="btn btn-outline btn-sm">Top-Artikel</a>
    <a href="{{ route('admin.statistics.purchase_planning') }}" class="btn btn-outline btn-sm">Einkaufsplanung</a>
@endsection

@section('content')

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <span>📦 Warengruppen — Umsatz je KW (letzte 12 Wochen)</span>
        <span style="font-size:.85em;color:var(--c-muted)">{{ $firstFrom }} – {{ $lastTo }}</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.8em;white-space:nowrap">
            <thead>
                <tr>
                    <th style="min-width:160px">Warengruppe</th>
                    @foreach($weekLabels as $label)
                        <th class="text-right" style="min-width:80px">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($sorted as $wg)
                    @php $kwData = $trend->get($wg, []); @endphp
                    <tr>
                        <td><strong>{{ $wg }}</strong></td>
                        @foreach($weekLabels as $i => $label)
                            @php
                                $cur  = $kwData[$label] ?? null;
                                $prev = $i > 0 ? ($kwData[$weekLabels[$i-1]] ?? null) : null;
                                $umsatz = $cur ? $cur['umsatz'] : 0;
                                $prevUmsatz = $prev ? $prev['umsatz'] : 0;
                                $diff = $umsatz - $prevUmsatz;
                                $bg = '';
                                if ($cur && $prevUmsatz > 0) {
                                    $bg = $diff > 0 ? 'background:rgba(22,163,74,.08)' : ($diff < 0 ? 'background:rgba(220,38,38,.08)' : '');
                                }
                            @endphp
                            <td class="text-right" style="{{ $bg }}">
                                @if($cur)
                                    {{ number_format($cur['umsatz'], 0, ',', '.') }} €
                                    @if($diff != 0 && $prevUmsatz > 0)
                                        <br><small style="color:{{ $diff > 0 ? '#16a34a' : '#dc2626' }}">
                                            {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 0, ',', '.') }}
                                        </small>
                                    @endif
                                @else
                                    <span style="color:var(--c-muted)">–</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            {{-- Gesamt-Zeile --}}
            <tfoot>
                <tr style="font-weight:600;border-top:2px solid var(--c-border,#e5e7eb)">
                    <td>Gesamt</td>
                    @foreach($weekLabels as $label)
                        @php
                            $total = $trend->sum(fn ($kwData) => $kwData[$label]['umsatz'] ?? 0);
                        @endphp
                        <td class="text-right">{{ number_format($total, 0, ',', '.') }} €</td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Menge-Tabelle --}}
<div class="card" style="margin-top:20px">
    <div class="card-header">
        📦 Warengruppen — Menge je KW (letzte 12 Wochen)
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.8em;white-space:nowrap">
            <thead>
                <tr>
                    <th style="min-width:160px">Warengruppe</th>
                    @foreach($weekLabels as $label)
                        <th class="text-right" style="min-width:70px">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($sorted as $wg)
                    @php $kwData = $trend->get($wg, []); @endphp
                    <tr>
                        <td><strong>{{ $wg }}</strong></td>
                        @foreach($weekLabels as $i => $label)
                            @php
                                $cur  = $kwData[$label] ?? null;
                                $prev = $i > 0 ? ($kwData[$weekLabels[$i-1]] ?? null) : null;
                                $menge = $cur ? $cur['menge'] : 0;
                                $prevMenge = $prev ? $prev['menge'] : 0;
                                $diff = $menge - $prevMenge;
                                $bg = '';
                                if ($cur && $prevMenge > 0) {
                                    $bg = $diff > 0 ? 'background:rgba(22,163,74,.08)' : ($diff < 0 ? 'background:rgba(220,38,38,.08)' : '');
                                }
                            @endphp
                            <td class="text-right" style="{{ $bg }}">
                                @if($cur)
                                    {{ number_format($cur['menge'], 0, ',', '.') }}
                                @else
                                    <span style="color:var(--c-muted)">–</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
