@extends('admin.layout')

@section('title', 'Kunden importieren (CSV)')

@section('content')

{{-- ── Result after execute ── --}}
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

    <a href="{{ route('admin.imports.customers') }}" class="btn btn-outline">
        Neuen Import starten
    </a>
    <a href="{{ route('admin.orders.index') }}" class="btn btn-primary" style="margin-left:8px">
        Zu den Bestellungen
    </a>
    @php return @endphp
@endif

{{-- ── Upload form ── --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">CSV-Datei hochladen</div>
    <div class="card-body">
        <p class="text-muted" style="margin-top:0">
            Identifizierung über <code>customer_number</code> (Pflichtfeld).<br>
            Erlaubte Spalten: <code>customer_number, name, first_name, last_name, email,
            group, active, address_delivery, postal_code, city, delivery_note</code>
        </p>
        <form method="POST" action="{{ route('admin.imports.customers.upload') }}"
              enctype="multipart/form-data">
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

{{-- ── Preview & row_errors (shown after upload) ── --}}
@if(isset($preview))

    {{-- Header errors --}}
    @if(isset($row_errors) && isset($row_errors[1]))
        <div class="alert alert-error">
            @foreach($row_errors[1] as $e)
                {{ $e }}<br>
            @endforeach
        </div>
    @endif

    {{-- Per-row errors --}}
    @if(! empty(array_filter($row_errors ?? [])))
        <div class="card" style="margin-bottom:16px">
            <div class="card-header" style="color:var(--c-danger)">
                Validierungsfehler ({{ count(array_filter($row_errors ?? [])) }} Zeilen)
            </div>
            <div class="card-body error-list">
                <ul>
                @foreach($row_errors as $lineNo => $errs)
                    @if(! empty($errs))
                        @foreach($errs as $err)
                            <li><strong>Zeile {{ $lineNo }}:</strong> {{ $err }}</li>
                        @endforeach
                    @endif
                @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Preview table ── --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            Vorschau (erste {{ count($preview) }} Zeilen von {{ count($rows) }} gesamt)
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        @foreach($headers as $h)
                            <th>{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($preview as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Execute button ── --}}
    <form method="POST" action="{{ route('admin.imports.customers.execute') }}">
        @csrf
        <input type="hidden" name="tmp_path" value="{{ $tmp_path }}">
        <div class="flex gap-2 items-center">
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('{{ count($rows) }} Zeilen importieren?')">
                ✅ Import jetzt ausführen ({{ count($rows) }} Zeilen)
            </button>
            <a href="{{ route('admin.imports.customers') }}" class="btn btn-outline">Abbrechen</a>
        </div>
    </form>

@endif

@endsection
