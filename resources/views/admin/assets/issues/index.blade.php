@extends('admin.layout')

@section('title', 'Mängel & Defekte')

@section('actions')
    <a href="{{ route('admin.assets.issues.create') }}" class="btn btn-primary btn-sm">+ Mangel erfassen</a>
@endsection

@section('content')

{{-- Filter --}}
<form method="GET" action="{{ route('admin.assets.issues.index') }}"
      style="display:flex;gap:12px;margin-bottom:16px;align-items:flex-end">
    <div class="form-group" style="margin:0">
        <label>Asset-Typ</label>
        <select name="asset_type" class="form-control" style="min-width:180px">
            <option value="">Alle Typen</option>
            <option value="vehicle" {{ request('asset_type') === 'vehicle' ? 'selected' : '' }}>Fahrzeuge</option>
            <option value="rental_inventory_unit" {{ request('asset_type') === 'rental_inventory_unit' ? 'selected' : '' }}>Einheiten</option>
        </select>
    </div>
    <div class="form-group" style="margin:0">
        <label>Status</label>
        <select name="status" class="form-control" style="min-width:160px">
            <option value="">Alle Status</option>
            <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Offen</option>
            <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>Geplant</option>
            <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Bearbeitung</option>
            <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Gelöst</option>
            <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Geschlossen</option>
        </select>
    </div>
    <button type="submit" class="btn btn-outline btn-sm">Filtern</button>
    @if(request('asset_type') || request('status'))
        <a href="{{ route('admin.assets.issues.index') }}" class="btn btn-outline btn-sm">Zurücksetzen</a>
    @endif
</form>

<div class="card">
    <div class="card-header">
        Mängel ({{ $issues->total() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Asset</th>
                    <th>Priorität</th>
                    <th>Titel</th>
                    <th>Status</th>
                    <th>Schwere</th>
                    <th style="text-align:center">Sperrt</th>
                    <th>Fällig</th>
                    <th>Zugewiesen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($issues as $issue)
                @php
                    $prioClass = match($issue->priority) {
                        'critical' => 'badge-cancelled',
                        'high'     => 'badge-cancelled',
                        'medium'   => 'badge-pending',
                        default    => 'badge-default',
                    };
                    $statusClass = match($issue->status) {
                        'open'        => 'badge-cancelled',
                        'scheduled'   => 'badge-pending',
                        'in_progress' => 'badge-pending',
                        'resolved'    => 'badge-delivered',
                        'closed'      => 'badge-default',
                        default       => 'badge-default',
                    };
                    $assetLabel = $issue->asset_type === 'vehicle'
                        ? 'Fahrzeug #' . $issue->asset_id
                        : 'Einheit #' . $issue->asset_id;
                @endphp
                <tr>
                    <td>{{ $assetLabel }}</td>
                    <td><span class="badge {{ $prioClass }}">{{ $issue->priority }}</span></td>
                    <td>{{ $issue->title }}</td>
                    <td><span class="badge {{ $statusClass }}">{{ $issue->status }}</span></td>
                    <td>{{ $issue->severity ?? '—' }}</td>
                    <td style="text-align:center">
                        @if($issue->blocks_usage)
                            <span title="Sperrt Nutzung" style="color:var(--c-danger)">&#x26D4;</span>
                        @endif
                        @if($issue->blocks_rental)
                            <span title="Sperrt Verleih" style="color:var(--c-danger)">&#x1F512;</span>
                        @endif
                        @if(!$issue->blocks_usage && !$issue->blocks_rental)
                            <span style="color:var(--c-muted)">—</span>
                        @endif
                    </td>
                    <td>{{ $issue->due_date?->format('d.m.Y') ?? '—' }}</td>
                    <td>{{ $issue->assignedTo?->name ?? '—' }}</td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.assets.issues.edit', $issue) }}"
                           class="btn btn-outline btn-sm">Bearbeiten</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;color:var(--c-muted);padding:24px">
                        Keine Mängel gefunden.
                        <a href="{{ route('admin.assets.issues.create') }}"
                           class="btn btn-primary btn-sm" style="margin-left:12px">
                            + Mangel erfassen
                        </a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $issues->appends(request()->query())->links() }}
@endsection
