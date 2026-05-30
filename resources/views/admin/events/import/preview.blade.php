@extends('admin.layout')

@section('title', 'Import-Vorschau: Ninox #' . $ninoxId)

@section('content')

<div style="margin-bottom:16px">
    <a href="{{ route('admin.events.import.index') }}" style="color:var(--c-muted);font-size:.85rem">&larr; Import</a>
    <h1 style="margin:4px 0;font-size:1.3rem">Import-Vorschau: Ninox Bestellannahme #{{ $ninoxId }}</h1>
</div>

@if($alreadyImported)
    <div class="alert alert-warning">Dieser Datensatz wurde bereits importiert.</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px">
    <div class="card">
        <div class="card-header">Ninox-Rohdaten</div>
        <div style="padding:16px;font-size:.85rem">
            <table style="width:100%">
                @foreach((array)$record as $key => $value)
                    @if(!in_array($key, ['ninox_sequence']))
                        <tr>
                            <td style="font-weight:600;padding:3px 8px 3px 0;color:var(--c-muted);white-space:nowrap">{{ $key }}</td>
                            <td style="padding:3px 0;word-break:break-all">{{ $value ?? '—' }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        </div>
    </div>

    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">Geplante Zuordnung</div>
            <div style="padding:16px;font-size:.85rem">
                <p><strong>Angebotsstatus:</strong> {{ $statusMap['offer_status'] }}</p>
                <p><strong>Review nötig:</strong> {{ $statusMap['needs_review'] ? 'Ja' : 'Nein' }}</p>
                <p><strong>Quelle:</strong> ninox / ninox_bestellannahme</p>
                <p><strong>Externes Angebotsnummer:</strong> #{{ $ninoxId }} (keine ST-A-Nr.)</p>
            </div>
        </div>

        @if($veranstaltung)
            <div class="card" style="margin-bottom:16px">
                <div class="card-header">Verknüpfte Ninox-Veranstaltung</div>
                <div style="padding:16px;font-size:.85rem">
                    <p><strong>ID:</strong> {{ $veranstaltung->ninox_id }}</p>
                    <p><strong>Name:</strong> {{ $veranstaltung->name ?? '—' }}</p>
                </div>
            </div>
        @endif

        @if(!$alreadyImported)
            <form method="POST" action="{{ route('admin.events.import.ninox') }}">
                @csrf
                <input type="hidden" name="ninox_id" value="{{ $ninoxId }}">
                <button type="submit" class="btn btn-primary">Jetzt importieren</button>
                <a href="{{ route('admin.events.import.index') }}" class="btn btn-outline" style="margin-left:8px">Abbrechen</a>
            </form>
        @else
            <a href="{{ route('admin.events.import.index') }}" class="btn btn-outline">Zurück</a>
        @endif
    </div>
</div>

@endsection
