@extends('admin.layout')

@section('title', 'Top-Artikel KW ' . $week . '/' . $year . ' (' . $kwFrom . '–' . $kwTo . ')')

@section('actions')
    <a href="{{ route('admin.statistics.purchase_planning') }}" class="btn btn-outline btn-sm">Einkaufsplanung</a>
    <a href="{{ route('admin.statistics.warengruppen') }}" class="btn btn-outline btn-sm">Warengruppen</a>
    <a href="{{ route('admin.statistics.pfand') }}" class="btn btn-outline btn-sm">Pfand</a>
@endsection

@section('content')

<div class="card" style="margin-bottom:16px">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.statistics.pos_top') }}"
              style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">

            <div class="form-group" style="margin:0">
                <label class="form-label">Jahr</label>
                <input type="number" name="year" value="{{ $year }}" min="2019" max="{{ $currentYear }}"
                       class="form-control" style="width:90px">
            </div>

            <div class="form-group" style="margin:0">
                <label class="form-label">Kalenderwoche</label>
                <input type="number" name="week" value="{{ $week }}" min="1" max="53"
                       class="form-control" style="width:80px">
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

            {{-- Week navigation --}}
            <a href="{{ route('admin.statistics.pos_top', ['year' => $prevYear, 'week' => $prevWeek, 'warengruppe' => $warengruppe]) }}"
               class="btn btn-outline btn-sm">← KW {{ $prevWeek }}/{{ $prevYear }}</a>

            @if($year < $currentYear || $week < $currentWeek)
                @php
                    [$nextYear, $nextWeek] = [
                        $week === 53 ? $year + 1 : $year,
                        $week === 53 ? 1 : $week + 1,
                    ];
                @endphp
                <a href="{{ route('admin.statistics.pos_top', ['year' => $nextYear, 'week' => $nextWeek, 'warengruppe' => $warengruppe]) }}"
                   class="btn btn-outline btn-sm">KW {{ $nextWeek }}/{{ $nextYear }} →</a>
            @endif

            {{-- Quick-period links --}}
            @php
                $periods = [];
                for ($i = 0; $i < 8; $i++) {
                    $d = now()->startOfWeek()->subWeeks($i);
                    $periods[] = [(int)$d->format('o'), (int)$d->format('W')];
                }
            @endphp
            <div style="margin-left:8px;display:flex;gap:4px;align-items:center;flex-wrap:wrap">
                <span style="font-size:.8em;color:var(--c-muted)">Schnell:</span>
                @foreach($periods as [$py, $pw])
                    @php $isCurrent = ($py === $year && $pw === $week); @endphp
                    <a href="{{ route('admin.statistics.pos_top', ['year' => $py, 'week' => $pw, 'warengruppe' => $warengruppe]) }}"
                       class="btn btn-sm {{ $isCurrent ? 'btn-primary' : 'btn-outline' }}"
                       style="font-size:.78em;padding:2px 7px">
                        KW {{ $pw }}{{ $py !== $currentYear ? '/'.$py : '' }}
                    </a>
                @endforeach
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>
            📊 Top-Artikel — KW {{ $week }}/{{ $year }}{{ $warengruppe ? " · $warengruppe" : '' }}
            <span style="font-size:.85em;font-weight:400;color:var(--c-muted);margin-left:6px">{{ $kwFrom }} – {{ $kwTo }}</span>
        </span>
        <span style="font-size:.85em;color:var(--c-muted)">
            Vergleich: KW {{ $prevWeek }}/{{ $prevYear }} (Vorwoche) | KW {{ $lyWeek }}/{{ $lyYear }} (Vorjahr)
        </span>
    </div>

    @if($rows->isEmpty())
        <div class="card-body">
            <p class="text-muted">Keine Kassendaten für KW {{ $week }}/{{ $year }} gefunden.</p>
        </div>
    @else
    <div class="card-body" style="padding:0;overflow-x:auto">
        <table class="table" style="font-size:.875em;min-width:780px">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Art.-Nr.</th>
                    <th>Artikel</th>
                    <th>Warengruppe</th>
                    <th class="text-right">Menge</th>
                    <th class="text-right" style="color:var(--c-muted)">Ø VW</th>
                    <th class="text-right" style="color:var(--c-muted)">Δ VW</th>
                    <th class="text-right" style="color:var(--c-muted)">Ø VJ</th>
                    <th class="text-right" style="color:var(--c-muted)">Δ VJ</th>
                    <th class="text-right">Umsatz</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $row)
                    <tr>
                        <td style="color:var(--c-muted)">{{ $i + 1 }}</td>
                        <td><code style="font-size:.85em">{{ $row->artnr }}</code></td>
                        <td>
                            <a href="{{ route('admin.statistics.artikel', ['artnr' => $row->artnr]) }}"
                               style="color:inherit;text-decoration:none" class="hover-underline">{{ $row->name }}</a>
                        </td>
                        <td style="color:var(--c-muted);font-size:.8em">{{ $row->warengruppe }}</td>
                        <td class="text-right"><strong>{{ number_format($row->menge, 0, ',', '.') }}</strong></td>
                        <td class="text-right" style="color:var(--c-muted)">{{ number_format($row->prev_menge, 0, ',', '.') }}</td>
                        <td class="text-right">
                            @if($row->diff_prev != 0)
                                <span style="color:{{ $row->diff_prev > 0 ? '#16a34a' : '#dc2626' }}">
                                    {{ $row->diff_prev > 0 ? '+' : '' }}{{ number_format($row->diff_prev, 0, ',', '.') }}
                                    @if($row->pct_prev !== null)
                                        <small>({{ $row->pct_prev > 0 ? '+' : '' }}{{ $row->pct_prev }}%)</small>
                                    @endif
                                </span>
                            @else
                                <span style="color:var(--c-muted)">–</span>
                            @endif
                        </td>
                        <td class="text-right" style="color:var(--c-muted)">{{ number_format($row->ly_menge, 0, ',', '.') }}</td>
                        <td class="text-right">
                            @if($row->diff_ly != 0)
                                <span style="color:{{ $row->diff_ly > 0 ? '#16a34a' : '#dc2626' }}">
                                    {{ $row->diff_ly > 0 ? '+' : '' }}{{ number_format($row->diff_ly, 0, ',', '.') }}
                                    @if($row->pct_ly !== null)
                                        <small>({{ $row->pct_ly > 0 ? '+' : '' }}{{ $row->pct_ly }}%)</small>
                                    @endif
                                </span>
                            @else
                                <span style="color:var(--c-muted)">–</span>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($row->umsatz, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:600;border-top:2px solid var(--c-border,#e5e7eb)">
                    <td colspan="4">Gesamt</td>
                    <td class="text-right">{{ number_format($rows->sum('menge'), 0, ',', '.') }}</td>
                    <td class="text-right" style="color:var(--c-muted)">{{ number_format($rows->sum('prev_menge'), 0, ',', '.') }}</td>
                    <td colspan="3"></td>
                    <td class="text-right">{{ number_format($rows->sum('umsatz'), 2, ',', '.') }} €</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>

@endsection
