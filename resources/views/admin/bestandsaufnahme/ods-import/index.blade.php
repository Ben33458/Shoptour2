@extends('admin.layout')

@section('title', 'ODS-Import — Bestandsaufnahme')

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 420px;gap:24px;align-items:start">

{{-- Importläufe --}}
<div>
    <h2>Importläufe</h2>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Datei</th>
                <th>Status</th>
                <th>Blätter</th>
                <th>Zeilen</th>
                <th>Konflikte</th>
                <th>Importiert von</th>
                <th>Datum</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($laeufe as $lauf)
            <tr>
                <td>{{ $lauf->id }}</td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis">{{ $lauf->dateiname }}</td>
                <td>
                    @if($lauf->status === 'abgeschlossen') <span class="badge badge-success">Abgeschlossen</span>
                    @elseif($lauf->status === 'fehler')    <span class="badge badge-danger">Fehler</span>
                    @else                                  <span class="badge badge-warning">In Verarbeitung</span>
                    @endif
                </td>
                <td>{{ $lauf->anzahl_blaetter }}</td>
                <td>{{ $lauf->anzahl_rohzeilen }}</td>
                <td>
                    @if($lauf->anzahl_konflikte > 0)
                        <span style="color:#dc3545;font-weight:600">{{ $lauf->anzahl_konflikte }}</span>
                    @else
                        0
                    @endif
                </td>
                <td>{{ $lauf->importiertVon?->name ?? '—' }}</td>
                <td>{{ $lauf->created_at->format('d.m.Y H:i') }}</td>
                <td>
                    <a href="{{ route('admin.bestandsaufnahme.ods-import.lauf', $lauf) }}" class="btn btn-sm btn-primary">Details</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-muted text-center">Noch keine Importläufe.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $laeufe->links() }}

    {{-- Mapping-Übersicht --}}
    <h2 style="margin-top:24px">Konfigurierte Mappings</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Tabellenblatt</th>
                <th>Typ</th>
                <th>Lieferant</th>
                <th>Standard-Lager</th>
                <th>Kolabri ArtNr-Spalte</th>
            </tr>
        </thead>
        <tbody>
            @forelse($mappings as $m)
            <tr>
                <td>{{ $m->tabellenblatt }}</td>
                <td><span class="badge badge-secondary">{{ $m->blatt_typ }}</span></td>
                <td>{{ $m->lieferant?->name ?? '—' }}</td>
                <td>{{ $m->lagerStandard?->name ?? '—' }}</td>
                <td><code>{{ $m->spalte_kolabri_artnr ?? '—' }}</code></td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-muted text-center">Noch keine Mappings konfiguriert.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Upload + Mapping --}}
<div>
    <div class="card" style="padding:16px;margin-bottom:16px">
        <h3 style="margin-top:0">ODS-Datei hochladen</h3>
        <form method="POST" action="{{ route('admin.bestandsaufnahme.ods-import.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>Datei (ODS, XLSX, XLS)</label>
                <input type="file" name="ods_file" accept=".ods,.xlsx,.xls" required class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Importieren</button>
        </form>
    </div>

    <div class="card" style="padding:16px">
        <h3 style="margin-top:0">Mapping anlegen / aktualisieren</h3>
        <form method="POST" action="{{ route('admin.bestandsaufnahme.ods-import.store-mapping') }}">
            @csrf

            <div class="form-group">
                <label>Tabellenblatt <span class="text-danger">*</span></label>
                <input type="text" name="tabellenblatt" value="{{ old('tabellenblatt') }}" required class="form-control" placeholder="Winkels-GUT-Trinks">
            </div>

            <div class="form-group">
                <label>Typ</label>
                <select name="blatt_typ" class="form-control">
                    <option value="A">A – Einfache Liste</option>
                    <option value="B">B – Mit Kolabri ArtNr.</option>
                    <option value="C">C – Sonderblatt</option>
                    <option value="unbekannt">Unbekannt</option>
                </select>
            </div>

            <div class="form-group">
                <label>Lieferant</label>
                <select name="lieferant_id">
                    <option value="">— Keiner —</option>
                    @foreach($lieferanten as $l)
                        <option value="{{ $l->id }}" @selected(old('lieferant_id') == $l->id)>{{ $l->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Standard-Lager</label>
                <select name="lager_id_standard">
                    <option value="">— Keines —</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" @selected(old('lager_id_standard') == $w->id)>{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                @foreach(['spalte_kolabri_artnr' => 'Kolabri ArtNr.', 'spalte_lieferanten_artnr' => 'Lieferanten-ArtNr.', 'spalte_produktname' => 'Produktname', 'spalte_mindestbestand' => 'Mindestbestand', 'spalte_bestand' => 'Bestand', 'spalte_bestellmenge' => 'Bestellmenge', 'spalte_mhd' => 'MHD', 'spalte_bestellhinweis' => 'Bestellhinweis'] as $field => $label)
                <div class="form-group">
                    <label style="font-size:11px">{{ $label }}</label>
                    <input type="text" name="{{ $field }}" value="{{ old($field) }}" class="form-control" placeholder="Spaltenname/Buchstabe">
                </div>
                @endforeach
            </div>

            <div class="form-group">
                <label>Notiz</label>
                <textarea name="notiz" class="form-control" rows="2">{{ old('notiz') }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
    </div>
</div>

</div>

@endsection
