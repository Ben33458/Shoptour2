@extends('admin.layout')

@section('title', 'LMIV importieren')

@section('content')

@if(isset($result))
    {{-- ── Import result ── --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">✅ Import abgeschlossen</div>
        <div class="card-body">
            <table style="border-collapse:collapse;font-size:.95em">
                <tr>
                    <td style="padding:3px 24px 3px 0;color:var(--c-muted)">Aktualisiert</td>
                    <td><strong>{{ $result['updated'] }}</strong></td>
                </tr>
                <tr>
                    <td style="padding:3px 24px 3px 0;color:var(--c-muted)">Neue Versionen</td>
                    <td><strong>{{ $result['created'] }}</strong></td>
                </tr>
                <tr>
                    <td style="padding:3px 24px 3px 0;color:var(--c-muted)">Übersprungen</td>
                    <td><strong>{{ $result['skipped'] }}</strong></td>
                </tr>
            </table>

            @if(! empty($row_errors))
                <div style="margin-top:12px">
                    <strong>Fehler:</strong>
                    <ul style="margin:6px 0 0;padding-left:20px;font-size:.88em">
                        @foreach($row_errors as $row => $errs)
                            @foreach($errs as $err)
                                <li>Zeile {{ $row }}: {{ $err }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="margin-top:16px">
                <a href="{{ route('admin.imports.lmiv') }}" class="btn btn-outline btn-sm">
                    Erneut importieren
                </a>
                <a href="{{ route('admin.products.index') }}?only_base=1" class="btn btn-primary btn-sm">
                    Basis-Artikel anzeigen
                </a>
            </div>
        </div>
    </div>

@elseif(isset($preview))
    {{-- ── Preview ── --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">🔍 Vorschau (erste 20 Zeilen)</div>
        <div class="card-body" style="padding:0;overflow-x:auto">
            <table class="table" style="font-size:.82em;min-width:600px">
                <thead>
                    <tr>
                        @foreach($headers as $h)
                            <th>{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($preview as $i => $row)
                    @php $rowNum = $i + 2; @endphp
                    <tr {{ isset($row_errors[$rowNum]) ? 'style=background:#fff5f5' : '' }}>
                        @foreach($row as $cell)
                            <td>{{ $cell ?: '–' }}</td>
                        @endforeach
                    </tr>
                    @if(isset($row_errors[$rowNum]))
                        <tr style="background:#fff0f0">
                            <td colspan="{{ count($headers) }}" style="font-size:.85em;color:var(--c-danger)">
                                @foreach($row_errors[$rowNum] as $err)
                                    ⚠ {{ $err }}<br>
                                @endforeach
                            </td>
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if(empty($row_errors))
        <form method="POST" action="{{ route('admin.imports.lmiv.execute') }}">
            @csrf
            <input type="hidden" name="tmp_path" value="{{ $tmp_path }}">
            <div style="display:flex;gap:10px;align-items:center">
                <button type="submit" class="btn btn-primary">
                    Import ausführen ({{ count($rows) }} Zeilen)
                </button>
                <a href="{{ route('admin.imports.lmiv') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    @else
        <div class="alert alert-error">
            Import enthält Fehler – bitte CSV korrigieren und erneut hochladen.
        </div>
        <a href="{{ route('admin.imports.lmiv') }}" class="btn btn-outline btn-sm">← Zurück</a>
    @endif

@else
    {{-- ── Upload form ── --}}
    <div class="card">
        <div class="card-header">📥 LMIV-Daten aus CSV importieren</div>
        <div class="card-body">

            <p style="font-size:.9em;color:var(--c-muted);margin-bottom:16px">
                Laden Sie eine <strong>semikolon-getrennte CSV-Datei</strong> (UTF-8) hoch.
                Pflichtfeld: <code>artikelnummer</code>.
                Optionale Felder für LMIV-Daten:
            </p>

            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px;font-size:.83em">
                @foreach(\App\Services\Catalog\LmivCsvImporter::LMIV_COLUMNS as $col)
                    <code style="background:var(--c-bg-alt,#f4f6f9);padding:2px 6px;border-radius:3px">{{ $col }}</code>
                @endforeach
                <code style="background:var(--c-bg-alt,#f4f6f9);padding:2px 6px;border-radius:3px;border:1px dashed var(--c-border)">ean</code>
            </div>

            <p style="font-size:.85em;color:var(--c-muted);margin-bottom:16px">
                Wenn <code>ean</code> vorhanden und anders als die aktuelle aktive EAN,
                wird automatisch eine <strong>neue LMIV-Version</strong> erstellt.
                Produkte ohne <em>is_base_item</em> werden automatisch als Basis-Artikel markiert.
            </p>

            <form method="POST" action="{{ route('admin.imports.lmiv.upload') }}"
                  enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label class="form-label">CSV-Datei (max. 5 MB)</label>
                    <input type="file" name="csv_file" class="form-control"
                           accept=".csv,.txt" required>
                </div>
                <button type="submit" class="btn btn-primary">Vorschau anzeigen</button>
            </form>

        </div>
    </div>
@endif

@endsection
