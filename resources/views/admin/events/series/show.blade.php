@extends('admin.layout')

@section('title', $series->name)

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
        <a href="{{ route('admin.events.series.index') }}" style="color:var(--c-muted);font-size:.85rem">&larr; Serien</a>
        <h1 style="margin:4px 0;font-size:1.3rem">{{ $series->name }}</h1>
        <span style="font-size:.85rem;color:var(--c-muted)">{{ $series->event_type }}</span>
    </div>
    <a href="{{ route('admin.events.series.edit', $series) }}" class="btn btn-outline">Bearbeiten</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:800px">
    <div class="card">
        <div class="card-header">Details</div>
        <div style="padding:16px">
            <p><strong>Kunde:</strong> {{ $series->customer?->company_name ?? '—' }}</p>
            <p><strong>Standardort:</strong> {{ $series->default_location_name ?? '—' }}</p>
            <p><strong>Aktiv:</strong> {{ $series->is_active ? 'Ja' : 'Nein' }}</p>
            <p><strong>Dublettenverdacht:</strong> {{ $series->is_duplicate_suspect ? 'Ja' : 'Nein' }}</p>
            <p><strong>Quelle:</strong> {{ $series->source_system ?? '—' }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Veranstaltungen dieser Serie</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Jahr</th><th>Titel</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse($series->occurrences as $occ)
                        <tr>
                            <td>{{ $occ->event_year ?? '—' }}</td>
                            <td><a href="{{ route('admin.events.show', $occ) }}">{{ $occ->title }}</a></td>
                            <td>@include('admin.events._partials.event-status-badge', ['status' => $occ->event_status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="text-align:center;color:var(--c-muted);padding:12px">Keine Veranstaltungen</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
