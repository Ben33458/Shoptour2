@extends('admin.layout')

@section('title', 'Mitarbeiter-Aufgaben')

@section('content')
<div class="page-header">
    <h1>Mitarbeiter-Aufgaben</h1>
    <div class="page-actions">
        <a href="{{ route('admin.emp-tasks.create') }}" class="btn btn-primary">+ Neue Aufgabe</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Filter --}}
<form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;align-items:flex-end;">
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Status</label>
        <select name="status" class="form-control" style="min-width:130px;">
            <option value="">Alle</option>
            <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Offen</option>
            <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Arbeit</option>
            <option value="done" {{ request('status') === 'done' ? 'selected' : '' }}>Erledigt</option>
        </select>
    </div>
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Mitarbeiter</label>
        <select name="employee_id" class="form-control" style="min-width:160px;">
            <option value="">Alle</option>
            @foreach($employees as $emp)
                <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                    {{ $emp->full_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Priorität</label>
        <select name="priority" class="form-control" style="min-width:130px;">
            <option value="">Alle</option>
            <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Dringend</option>
            <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>Hoch</option>
            <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Mittel</option>
            <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Niedrig</option>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filtern</button>
    <a href="{{ route('admin.emp-tasks.index') }}" class="btn btn-secondary">Zurücksetzen</a>
</form>

<div class="card">
    <div class="card-body" style="padding:0;">
        @if($tasks->isEmpty())
            <p style="padding:1.5rem;color:var(--c-muted,#64748b);">Keine Aufgaben gefunden.</p>
        @else
        <table class="table">
            <thead>
                <tr>
                    <th>Aufgabe</th>
                    <th>Zugewiesen an</th>
                    <th>Priorität</th>
                    <th>Fälligkeit</th>
                    <th>Status</th>
                    <th>Unteraufgaben</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tasks as $task)
                @php
                    $isOverdue = $task->due_date && $task->due_date->lt(today()) && $task->status !== 'done';
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('admin.emp-tasks.show', $task) }}" style="font-weight:500;">
                            {{ $task->title }}
                        </a>
                        @if($task->description)
                            <div style="font-size:.8rem;color:var(--c-muted,#64748b);">{{ Str::limit($task->description, 80) }}</div>
                        @endif
                    </td>
                    <td>{{ $task->assignee?->full_name ?? '—' }}</td>
                    <td>
                        @php $prio = ['urgent'=>['Dringend','danger'], 'high'=>['Hoch','warning'], 'medium'=>['Mittel','info'], 'low'=>['Niedrig','secondary']][$task->priority] ?? ['?','secondary'] @endphp
                        <span class="badge badge-{{ $prio[1] }}">{{ $prio[0] }}</span>
                    </td>
                    <td style="{{ $isOverdue ? 'color:var(--c-danger,#dc2626);font-weight:600;' : '' }}">
                        {{ $task->due_date?->format('d.m.Y') ?? '—' }}
                        @if($isOverdue) <span style="font-size:.75rem;">(überfällig)</span> @endif
                    </td>
                    <td>
                        @php $s = ['open'=>['Offen','secondary'], 'in_progress'=>['In Arbeit','warning'], 'done'=>['Erledigt','success']][$task->status] ?? ['?','secondary'] @endphp
                        <span class="badge badge-{{ $s[1] }}">{{ $s[0] }}</span>
                    </td>
                    <td>
                        @if($task->subtasks->count() > 0)
                            <span style="font-size:.85rem;">{{ $task->subtasks->where('status','done')->count() }}/{{ $task->subtasks->count() }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="{{ route('admin.emp-tasks.show', $task) }}" class="btn btn-sm btn-secondary">Detail</a>
                        <a href="{{ route('admin.emp-tasks.edit', $task) }}" class="btn btn-sm btn-secondary">Bearbeiten</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{ $tasks->links() }}
@endsection
