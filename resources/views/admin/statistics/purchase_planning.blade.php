@extends('admin.layout')

@section('title', 'Einkaufsplanung / Lagerreichweite')

@section('actions')
    <a href="{{ route('admin.statistics.pos_top') }}" class="btn btn-outline btn-sm">Top-Artikel</a>
    <a href="{{ route('admin.statistics.warengruppen') }}" class="btn btn-outline btn-sm">Warengruppen</a>
    <a href="{{ route('admin.statistics.purchase_planning.export') }}" class="btn btn-success btn-sm">⬇ CSV</a>
@endsection

@section('content')

<div class="card" style="margin-bottom:16px">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.statistics.purchase_planning') }}"
              style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">

            <div class="form-group" style="margin:0">
                <label class="form-label">Warengruppe</label>
                <select name="warengruppe" class="form-control" style="min-width:180px">
                    <option value="">– Alle –</option>
                    @foreach($warengruppen as $wg)
                        <option value="{{ $wg }}" {{ $warengruppe === $wg ? 'selected' : '' }}>{{ $wg }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" style="margin:0">
                <label class="form-label">Ampel</label>
                <select name="ampel" class="form-control">
                    <option value="">– Alle –</option>
                    <option value="rot"   {{ $ampel === 'rot'   ? 'selected' : '' }}>🔴 Kritisch (< 1 Woche)</option>
                    <option value="gelb"  {{ $ampel === 'gelb'  ? 'selected' : '' }}>🟡 Bald bestellen (1–2 Wo.)</option>
                    <option value="grün"  {{ $ampel === 'grün'  ? 'selected' : '' }}>🟢 OK (> 2 Wochen)</option>
                    <option value="grau"  {{ $ampel === 'grau'  ? 'selected' : '' }}>⚪ Kein Absatz</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-sm">Filtern</button>
            @if($warengruppe || $ampel)
                <a href="{{ route('admin.statistics.purchase_planning') }}" class="btn btn-outline btn-sm">Zurücksetzen</a>
            @endif
        </form>
    </div>
</div>

{{-- Ampel-Zusammenfassung --}}
@php
    $redCount    = $dataset->where('ampel', 'rot')->count();
    $yellowCount = $dataset->where('ampel', 'gelb')->count();
    $greenCount  = $dataset->where('ampel', 'grün')->count();
    $greyCount   = $dataset->where('ampel', 'grau')->count();
@endphp
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
    <a href="{{ route('admin.statistics.purchase_planning', ['ampel' => 'rot']) }}"
       class="card" style="padding:12px 20px;text-decoration:none;flex:1;min-width:120px;text-align:center;border-left:4px solid #dc2626">
        <div style="font-size:1.6rem;font-weight:700;color:#dc2626">{{ $redCount }}</div>
        <div style="font-size:.8em;color:var(--c-muted)">🔴 Kritisch</div>
    </a>
    <a href="{{ route('admin.statistics.purchase_planning', ['ampel' => 'gelb']) }}"
       class="card" style="padding:12px 20px;text-decoration:none;flex:1;min-width:120px;text-align:center;border-left:4px solid #d97706">
        <div style="font-size:1.6rem;font-weight:700;color:#d97706">{{ $yellowCount }}</div>
        <div style="font-size:.8em;color:var(--c-muted)">🟡 Bald bestellen</div>
    </a>
    <a href="{{ route('admin.statistics.purchase_planning', ['ampel' => 'grün']) }}"
       class="card" style="padding:12px 20px;text-decoration:none;flex:1;min-width:120px;text-align:center;border-left:4px solid #16a34a">
        <div style="font-size:1.6rem;font-weight:700;color:#16a34a">{{ $greenCount }}</div>
        <div style="font-size:.8em;color:var(--c-muted)">🟢 OK</div>
    </a>
    <a href="{{ route('admin.statistics.purchase_planning', ['ampel' => 'grau']) }}"
       class="card" style="padding:12px 20px;text-decoration:none;flex:1;min-width:120px;text-align:center;border-left:4px solid #9ca3af">
        <div style="font-size:1.6rem;font-weight:700;color:#9ca3af">{{ $greyCount }}</div>
        <div style="font-size:.8em;color:var(--c-muted)">⚪ Kein Absatz</div>
    </a>
</div>

<div class="card">
    <div class="card-header">
        🛒 Einkaufsplanung — Lagerreichweite
        <span style="font-size:.8em;color:var(--c-muted);margin-left:8px">
            Ø Wochenabsatz POS · Sortierung: Reichweite aufsteigend
        </span>
    </div>
    @if($dataset->isEmpty())
        <div class="card-body"><p class="text-muted">Keine Daten gefunden.</p></div>
    @else
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.875em;min-width:700px">
            <thead>
                <tr>
                    <th></th>
                    <th>Art.-Nr.</th>
                    <th>Artikel</th>
                    <th>Warengruppe</th>
                    <th class="text-right">Bestand</th>
                    <th class="text-right">Ø/Woche (4W)</th>
                    <th class="text-right">Ø/Woche (8W)</th>
                    <th class="text-right">Reichweite</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dataset as $row)
                    @php
                        $bg = match($row->ampel) {
                            'rot'  => 'background:#fef2f2',
                            'gelb' => 'background:#fffbeb',
                            default => '',
                        };
                    @endphp
                    <tr style="{{ $bg }}">
                        <td style="width:20px">
                            @if($row->ampel === 'rot')   🔴
                            @elseif($row->ampel === 'gelb') 🟡
                            @elseif($row->ampel === 'grün') 🟢
                            @else ⚪
                            @endif
                        </td>
                        <td><code style="font-size:.85em">{{ $row->artnr }}</code></td>
                        <td>
                            <a href="{{ route('admin.statistics.artikel', ['artnr' => $row->artnr]) }}"
                               style="color:inherit;text-decoration:none" class="hover-underline">{{ $row->name }}</a>
                        </td>
                        <td style="color:var(--c-muted);font-size:.8em">{{ $row->warengruppe }}</td>
                        <td class="text-right">{{ number_format($row->bestand, 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($row->avg_4w, 1, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($row->avg_8w, 1, ',', '.') }}</td>
                        <td class="text-right">
                            @if($row->reichweite !== null)
                                <strong>{{ number_format($row->reichweite, 1, ',', '.') }} Wo.</strong>
                            @else
                                <span style="color:var(--c-muted)">–</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection
