@extends('admin.layout')

@section('title', 'Importlauf #' . $lauf->id)

@section('content')

<div style="display:flex;gap:16px;align-items:center;margin-bottom:16px;padding:12px;background:#f8f9fa;border-radius:6px;flex-wrap:wrap">
    <div><strong>Datei:</strong> {{ $lauf->dateiname }}</div>
    <div><strong>Status:</strong>
        @if($lauf->status === 'abgeschlossen') <span class="badge badge-success">Abgeschlossen</span>
        @elseif($lauf->status === 'fehler')    <span class="badge badge-danger">Fehler</span>
        @else                                  <span class="badge badge-warning">In Verarbeitung</span>
        @endif
    </div>
    <div><strong>Zeilen:</strong> {{ $lauf->anzahl_rohzeilen }}</div>
    <div><strong>Konflikte:</strong> <span @if($lauf->anzahl_konflikte > 0) style="color:#dc3545;font-weight:600" @endif>{{ $lauf->anzahl_konflikte }}</span></div>
    <div><strong>Importiert:</strong> {{ $lauf->created_at->format('d.m.Y H:i') }}</div>
</div>

@if($lauf->fehler_log)
    <div class="alert alert-danger"><strong>Fehlerprotokoll:</strong><br><pre style="margin:0;font-size:12px">{{ $lauf->fehler_log }}</pre></div>
@endif

{{-- Blatt-Filter --}}
<form method="GET" action="{{ route('admin.bestandsaufnahme.ods-import.lauf', $lauf) }}" style="margin-bottom:12px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group">
            <label>Tabellenblatt</label>
            <select name="blatt">
                <option value="">Alle Blätter</option>
                @foreach($blaetter as $b)
                    <option value="{{ $b }}" @selected($blatt === $b)>{{ $b }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="">Alle</option>
                <option value="pruefbeduertig" @selected(request('status') === 'pruefbeduertig')>Prüfbedürftig</option>
                <option value="konflikt" @selected(request('status') === 'konflikt')>Konflikt</option>
                <option value="gemappt" @selected(request('status') === 'gemappt')>Gemappt</option>
                <option value="uebernommen" @selected(request('status') === 'uebernommen')>Übernommen</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filtern</button>
    </div>
</form>

<table class="table" style="font-size:13px">
    <thead>
        <tr>
            <th>Blatt</th>
            <th>Zeile</th>
            <th>Status</th>
            <th>Produkt</th>
            <th>Hinweis</th>
            <th>Konflikte</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rohzeilen as $zeile)
        <tr>
            <td><span class="badge badge-secondary">{{ $zeile->tabellenblatt }}</span></td>
            <td>{{ $zeile->zeilennummer }}</td>
            <td>
                @php
                    $statusColors = [
                        'neu'              => 'secondary',
                        'gemappt'          => 'success',
                        'konflikt'         => 'danger',
                        'uebernommen'      => 'success',
                        'verworfen'        => 'secondary',
                        'manuell_zugeordnet' => 'info',
                        'pruefbeduertig'   => 'warning',
                    ];
                @endphp
                <span class="badge badge-{{ $statusColors[$zeile->erkannt_status] ?? 'secondary' }}">{{ $zeile->erkannt_status }}</span>
            </td>
            <td>
                @if($zeile->product)
                    <small>{{ $zeile->product->artikelnummer }}</small><br>{{ $zeile->product->produktname }}
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
            <td style="max-width:280px;font-size:12px;color:#6c757d">{{ $zeile->mapping_hinweis ?? '—' }}</td>
            <td>
                @foreach($zeile->konflikte as $k)
                    <div style="margin-bottom:4px;padding:4px 6px;background:#fff3cd;border-radius:3px;font-size:11px">
                        <strong>{{ $k->konflikt_typ }}</strong>
                        @if($k->feld): {{ $k->feld }}@endif
                        @if($k->ods_wert && $k->db_wert)
                            <br>ODS: <code>{{ $k->ods_wert }}</code> → DB: <code>{{ $k->db_wert }}</code>
                        @endif
                        @if($k->aktion === 'offen')
                        <form method="POST" action="{{ route('admin.bestandsaufnahme.ods-import.konflikt-aktion', $k) }}" style="display:inline;margin-left:4px">
                            @csrf
                            <select name="aktion" onchange="this.form.submit()" style="font-size:11px">
                                <option value="">— Aktion —</option>
                                <option value="uebernehmen">Übernehmen</option>
                                <option value="verwerfen">Verwerfen</option>
                                <option value="manuell">Manuell zuordnen</option>
                                <option value="referenz">Als Referenz speichern</option>
                            </select>
                        </form>
                        @else
                            <span class="badge badge-info">{{ $k->aktion }}</span>
                        @endif
                    </div>
                @endforeach
            </td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-muted text-center">Keine Zeilen.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $rohzeilen->links() }}

@endsection
