@extends('admin.layout')

@section('title', 'Nachkalkulation offen')

@section('content')

<div style="margin-bottom:16px">
    <a href="{{ route('admin.events.index') }}" style="color:var(--c-muted);font-size:.85rem">&larr; Veranstaltungen</a>
    <h1 style="margin:4px 0;font-size:1.3rem">Nachkalkulation offen</h1>
</div>

<div class="card">
    <div class="card-header">Veranstaltungen mit offener Nachkalkulation ({{ $occurrences->total() }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Titel</th>
                    <th>Kunde</th>
                    <th>Gäste</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($occurrences as $occ)
                    <tr>
                        <td>{{ $occ->event_start_at?->format('d.m.Y') ?? '—' }}</td>
                        <td><a href="{{ route('admin.events.show', ['occurrence' => $occ, 'tab' => 'post-calc']) }}">{{ $occ->title }}</a></td>
                        <td>{{ $occ->customer?->company_name ?? '—' }}</td>
                        <td>{{ $occ->actual_guests ?? $occ->expected_guests ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admin.events.show', ['occurrence' => $occ, 'tab' => 'post-calc']) }}"
                               class="btn btn-primary" style="padding:2px 8px;font-size:.75rem">Nachkalk. öffnen</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--c-muted);padding:24px">Keine offenen Nachkalkulationen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:16px">{{ $occurrences->links() }}</div>

@endsection
