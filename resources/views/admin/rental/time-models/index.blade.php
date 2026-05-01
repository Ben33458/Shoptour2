@extends('admin.layout')

@section('title', 'Zeitmodelle')

@section('actions')
    <a href="{{ route('admin.rental.time-models.create') }}" class="btn btn-primary btn-sm">+ Neues Zeitmodell</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">Zeitmodelle ({{ count($models) }})</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Regel-Typ</th>
                    <th>Min. Stunden</th>
                    <th style="text-align:center">Standard für Events</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($models as $model)
                <tr>
                    <td><strong>{{ $model->name }}</strong></td>
                    <td><code>{{ $model->rule_type }}</code></td>
                    <td>{{ $model->min_duration_hours }}</td>
                    <td style="text-align:center">
                        @if($model->default_for_events)
                            <span class="badge badge-delivered">ja</span>
                        @else
                            <span style="color:var(--c-muted)">—</span>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.rental.time-models.edit', $model) }}" class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.rental.time-models.destroy', $model) }}"
                              style="display:inline"
                              onsubmit="return confirm('Zeitmodell \"{{ addslashes($model->name) }}\" löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--c-muted);padding:24px">
                        Noch keine Zeitmodelle angelegt.
                        <a href="{{ route('admin.rental.time-models.create') }}" class="btn btn-primary btn-sm" style="margin-left:12px">+ Erstes anlegen</a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
