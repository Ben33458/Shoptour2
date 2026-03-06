@extends('admin.layout')

@section('title', 'Marken importieren (CSV)')

@section('content')

@if(isset($result))
    <div class="alert alert-success">
        <strong>Import abgeschlossen:</strong>
        {{ $result['created'] }} erstellt &middot;
        {{ $result['updated'] }} aktualisiert &middot;
        {{ $result['skipped'] }} übersprungen
    </div>
    @if(! empty(array_filter($row_errors ?? [])))
        <div class="card" style="margin-bottom:20px">
            <div class="card-header" style="color:var(--c-danger)">Fehler beim Import</div>
            <div class="card-body">
                @foreach($row_errors as $lineNo => $errs)
                    @foreach($errs as $err)
                        <div class="alert alert-error" style="margin-bottom:6px;padding:6px 12px">
                            <strong>Zeile {{ $lineNo }}:</strong> {{ $err }}
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif
    <a href="{{ route('admin.imports.brands') }}" class="btn btn-outline">Neuen Import starten</a>
    <a href="{{ route('admin.brands.index') }}" class="btn btn-primary" style="margin-left:8px">Zu den Marken</a>
    @php return @endphp
@endif

<div class="card" style="margin-bottom:24px">
    <div class="card-header">CSV-Datei hochladen</div>
    <div class="card-body">
        <p class="text-muted" style="margin-top:0">
            Identifizierung über <code>name</code> (Pflichtfeld, Groß-/Kleinschreibung egal).<br>
            Erlaubte Spalten: <code>name</code>
        </p>
        <form method="POST" action="{{ route('admin.imports.brands.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-row" style="align-items:flex-end">
                <div class="form-group">
                    <label>CSV-Datei <span style="color:var(--c-danger)">*</span></label>
                    <input type="file" name="csv_file" accept=".csv,.txt" required>
                    <div class="hint">Max. 5 MB · UTF-8 · erste Zeile = Spaltennamen</div>
                </div>
                <div style="padding-bottom:14px">
                    <button type="submit" class="btn btn-primary">Hochladen & Vorschau</button>
                </div>
            </div>
        </form>
    </div>
</div>

@if(isset($preview))
    @if(isset($row_errors) && isset($row_errors[1]))
        <div class="alert alert-error">
            @foreach($row_errors[1] as $e) {{ $e }}<br> @endforeach
        </div>
    @endif
    @if(! empty(array_filter($row_errors ?? [])))
        <div class="card" style="margin-bottom:16px">
            <div class="card-header" style="color:var(--c-danger)">
                Validierungsfehler ({{ count(array_filter($row_errors ?? [])) }} Zeilen)
            </div>
            <div class="card-body error-list"><ul>
                @foreach($row_errors as $lineNo => $errs)
                    @if(! empty($errs))
                        @foreach($errs as $err)
                            <li><strong>Zeile {{ $lineNo }}:</strong> {{ $err }}</li>
                        @endforeach
                    @endif
                @endforeach
            </ul></div>
        </div>
    @endif
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Vorschau (erste {{ count($preview) }} Zeilen von {{ count($rows) }} gesamt)</div>
        <div class="table-wrap">
            <table>
                <thead><tr>@foreach($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach($preview as $row)
                        <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.imports.brands.execute') }}">
        @csrf
        <input type="hidden" name="tmp_path" value="{{ $tmp_path }}">
        <div class="flex gap-2 items-center">
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('{{ count($rows) }} Zeilen importieren?')">
                ✅ Import jetzt ausführen ({{ count($rows) }} Zeilen)
            </button>
            <a href="{{ route('admin.imports.brands') }}" class="btn btn-outline">Abbrechen</a>
        </div>
    </form>
@endif

@endsection
