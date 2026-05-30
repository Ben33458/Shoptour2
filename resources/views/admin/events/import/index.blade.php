@extends('admin.layout')

@section('title', 'Veranstaltungs-Import')

@section('content')

<div style="margin-bottom:16px">
    <a href="{{ route('admin.events.index') }}" style="color:var(--c-muted);font-size:.85rem">&larr; Veranstaltungen</a>
    <h1 style="margin:4px 0;font-size:1.3rem">Daten-Import: Veranstaltungen</h1>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif

{{-- ── JTL Discovery Report ── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">JTL-Angebote — Discovery Report</div>
    <div style="padding:16px">
        @if($jtlReport['has_offer_table'])
            <div style="color:#16a34a;font-weight:600;margin-bottom:8px">JTL-Angebotstabelle gefunden</div>
        @else
            <div style="color:#dc2626;font-weight:600;margin-bottom:8px">Keine JTL-Angebotstabelle gefunden</div>
        @endif

        <p>{{ $jtlReport['note'] }}</p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px">
            <div>
                <strong>Tabellen vorhanden:</strong>
                <ul style="margin:4px 0;padding-left:20px">
                    @foreach($jtlReport['tables_found'] as $table)
                        <li style="color:#16a34a">{{ $table }}</li>
                    @endforeach
                    @if(empty($jtlReport['tables_found']))
                        <li style="color:var(--c-muted)">— keine —</li>
                    @endif
                </ul>
            </div>
            <div>
                <strong>Tabellen fehlend:</strong>
                <ul style="margin:4px 0;padding-left:20px">
                    @foreach($jtlReport['tables_missing'] as $table)
                        <li style="color:#dc2626">{{ $table }}</li>
                    @endforeach
                    @if(empty($jtlReport['tables_missing']))
                        <li style="color:#16a34a">— alle vorhanden —</li>
                    @endif
                </ul>
            </div>
        </div>

        <div style="margin-top:12px;font-size:.85rem;color:var(--c-muted)">
            JTL-Aufträge (nType=1): <strong>{{ number_format($jtlReport['wawi_auftraege_count']) }}</strong>
            &nbsp;·&nbsp;
            Angebote (nType=0): <strong>{{ $jtlReport['offer_count'] }}</strong>
        </div>

        @if($jtlReport['has_offer_table'] && $jtlReport['offer_count'] > 0)
            <div style="margin-top:14px">
                <a href="{{ route('admin.events.import.jtl') }}" class="btn btn-primary btn-sm">
                    JTL-Angebote zuordnen ({{ $jtlReport['offer_count'] }} verfügbar)
                </a>
            </div>
        @endif
    </div>
</div>

{{-- ── Ninox Kandidaten ── --}}
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Ninox Veranstaltungs-Kandidaten ({{ $candidates->count() }})</span>
        @if($candidates->where('already_imported', false)->isNotEmpty())
            <form method="POST" action="{{ route('admin.events.import.ninox') }}" onsubmit="return confirm('Alle nicht importierten Kandidaten importieren?')">
                @csrf
                <button type="submit" class="btn btn-primary" style="font-size:.8rem">Alle importieren</button>
            </form>
        @endif
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ninox ID</th>
                    <th>Status</th>
                    <th>Datum</th>
                    <th>Gäste</th>
                    <th>Preis</th>
                    <th>Veranstaltung</th>
                    <th>Importiert?</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($candidates as $c)
                    <tr @if($c->already_imported) style="opacity:.5" @endif>
                        <td>{{ $c->ninox_id }}</td>
                        <td>{{ $c->status ?? '—' }}</td>
                        <td>{{ $c->datum_der_veranstaltung ?? '—' }}</td>
                        <td>{{ $c->anzahl_gaeste ?? '—' }}</td>
                        <td>{{ $c->preis ? number_format((float)$c->preis, 2, ',', '.') . ' €' : '—' }}</td>
                        <td>{{ $c->gehoert_zu_veranstaltung ?? '—' }}</td>
                        <td>
                            @if($c->already_imported)
                                <span style="color:#16a34a;font-weight:600">Ja</span>
                            @else
                                <span style="color:var(--c-muted)">Nein</span>
                            @endif
                        </td>
                        <td style="display:flex;gap:4px">
                            <a href="{{ route('admin.events.import.ninox.preview', $c->ninox_id) }}"
                               class="btn btn-outline" style="padding:2px 8px;font-size:.75rem">Vorschau</a>
                            @if(!$c->already_imported)
                                <form method="POST" action="{{ route('admin.events.import.ninox') }}">
                                    @csrf
                                    <input type="hidden" name="ninox_id" value="{{ $c->ninox_id }}">
                                    <button type="submit" class="btn btn-primary" style="padding:2px 8px;font-size:.75rem">Importieren</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;color:var(--c-muted);padding:24px">
                            Keine Ninox-Kandidaten gefunden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
