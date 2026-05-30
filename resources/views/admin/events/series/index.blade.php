@extends('admin.layout')

@section('title', 'Veranstaltungsserien')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
        <a href="{{ route('admin.events.index') }}" style="color:var(--c-muted);font-size:.85rem">&larr; Veranstaltungen</a>
        <h1 style="margin:4px 0;font-size:1.3rem">Veranstaltungsserien</h1>
    </div>
    <a href="{{ route('admin.events.series.create') }}" class="btn btn-primary">+ Neue Serie</a>
</div>

<form method="GET" action="{{ route('admin.events.series.index') }}">
    <div class="filter-bar">
        <div class="form-group">
            <label>Suche</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Serienname…">
        </div>
        <div class="form-group">
            <label>Art</label>
            <select name="event_type">
                <option value="">— Alle —</option>
                @foreach($eventTypes as $type)
                    <option value="{{ $type }}" @selected(request('event_type') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0">
            <button type="submit" class="btn btn-primary">Filtern</button>
            <a href="{{ route('admin.events.series.index') }}" class="btn btn-outline">Zurücksetzen</a>
        </div>
    </div>
</form>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-header">Serien ({{ $series->total() }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Art</th>
                    <th>Kunde</th>
                    <th>Veranstaltungen</th>
                    <th>Aktiv</th>
                    <th>Dublette?</th>
                    <th>Quelle</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($series as $s)
                    <tr>
                        <td><a href="{{ route('admin.events.series.show', $s) }}">{{ $s->name }}</a></td>
                        <td>{{ $s->event_type }}</td>
                        <td>{{ $s->customer?->company_name ?? '—' }}</td>
                        <td>{{ $s->occurrences_count ?? '—' }}</td>
                        <td>{{ $s->is_active ? 'Ja' : 'Nein' }}</td>
                        <td>{{ $s->is_duplicate_suspect ? '⚠' : '' }}</td>
                        <td>{{ $s->source_system ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admin.events.series.edit', $s) }}" class="btn btn-outline" style="padding:2px 8px;font-size:.75rem">Bearbeiten</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;color:var(--c-muted);padding:24px">Keine Serien gefunden.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:16px">{{ $series->links() }}</div>

@endsection
