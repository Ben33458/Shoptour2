@extends('admin.layout')

@section('title', 'Bestandsaufnahme')

@section('actions')
    <a href="{{ route('admin.bestandsaufnahme.create') }}" class="btn btn-primary">+ Neue Session</a>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="GET" action="{{ route('admin.bestandsaufnahme.index') }}">
    <div class="filter-bar">
        <div class="form-group">
            <label>Lager</label>
            <select name="warehouse_id">
                <option value="">Alle Lager</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}" @selected(request('warehouse_id') == $w->id)>{{ $w->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="">Alle</option>
                <option value="offen" @selected(request('status') === 'offen')>Offen</option>
                <option value="pausiert" @selected(request('status') === 'pausiert')>Pausiert</option>
                <option value="abgeschlossen" @selected(request('status') === 'abgeschlossen')>Abgeschlossen</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filtern</button>
    </div>
</form>

<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Lager</th>
            <th>Titel</th>
            <th>Status</th>
            <th>Gestartet von</th>
            <th>Gestartet am</th>
            <th>Abgeschlossen</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse($sessions as $session)
        <tr>
            <td>{{ $session->id }}</td>
            <td>{{ $session->warehouse->name }}</td>
            <td>{{ $session->titel ?? '—' }}</td>
            <td>
                @if($session->status === 'offen')
                    <span class="badge badge-success">Offen</span>
                @elseif($session->status === 'pausiert')
                    <span class="badge badge-warning">Pausiert</span>
                @else
                    <span class="badge badge-secondary">Abgeschlossen</span>
                @endif
            </td>
            <td>{{ $session->gestartetVon->name ?? '—' }}</td>
            <td>{{ $session->gestartet_am->format('d.m.Y H:i') }}</td>
            <td>{{ $session->abgeschlossen_am?->format('d.m.Y H:i') ?? '—' }}</td>
            <td>
                <a href="{{ route('admin.bestandsaufnahme.show', $session) }}" class="btn btn-sm btn-primary">
                    {{ $session->isAbgeschlossen() ? 'Ansehen' : 'Öffnen' }}
                </a>
            </td>
        </tr>
        @empty
        <tr><td colspan="8" class="text-center text-muted">Keine Sessions gefunden.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $sessions->links() }}

@endsection
