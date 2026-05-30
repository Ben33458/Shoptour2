@extends('admin.layout')

@section('title', 'Offene Anfragen')

@section('content')

<div style="margin-bottom:16px">
    <a href="{{ route('admin.events.index') }}" style="color:var(--c-muted);font-size:.85rem">&larr; Veranstaltungen</a>
    <h1 style="margin:4px 0;font-size:1.3rem">Offene Anfragen</h1>
</div>

<div class="card">
    <div class="card-header">Anfragen ({{ $occurrences->total() }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Titel</th>
                    <th>Kunde</th>
                    <th>Status</th>
                    <th>Eingang</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($occurrences as $occ)
                    <tr>
                        <td>{{ $occ->event_start_at?->format('d.m.Y') ?? '—' }}</td>
                        <td><a href="{{ route('admin.events.show', $occ) }}">{{ $occ->title }}</a></td>
                        <td>{{ $occ->customer?->company_name ?? '—' }}</td>
                        <td>@include('admin.events._partials.offer-status-badge', ['status' => $occ->offer_status])</td>
                        <td>{{ $occ->created_at->format('d.m.Y H:i') }}</td>
                        <td><a href="{{ route('admin.events.show', $occ) }}" class="btn btn-outline" style="padding:2px 8px;font-size:.75rem">Öffnen</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--c-muted);padding:24px">Keine offenen Anfragen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:16px">{{ $occurrences->links() }}</div>

@endsection
