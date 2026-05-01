@extends('admin.layout')

@section('title', 'Schichtplan')

@section('content')
<div class="page-header">
    <h1>Schichtplan</h1>
</div>

@include('admin._partials.shifts-tabs')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{-- Week navigation --}}
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
    @php
        $prevWeek = $monday->copy()->subWeek()->format('Y-\WW');
        $nextWeek = $monday->copy()->addWeek()->format('Y-\WW');
    @endphp
    <a href="?week={{ $prevWeek }}" class="btn btn-secondary">&larr; Vorwoche</a>
    <strong>KW {{ $monday->weekOfYear }} / {{ $monday->year }} — {{ $monday->format('d.m.') }} bis {{ $monday->copy()->addDays(6)->format('d.m.Y') }}</strong>
    <a href="?week={{ $nextWeek }}" class="btn btn-secondary">Nächste Woche &rarr;</a>
    <a href="?week={{ now()->format('Y-\WW') }}" class="btn btn-secondary">Heute</a>
</div>

{{-- Shifts table grouped by day --}}
<div class="card" style="margin-bottom:2rem;">
    <div class="card-body" style="padding:0;">
        @php
            $byDay = $shifts->groupBy(fn($s) => $s->planned_start->format('Y-m-d'));
        @endphp
        @if($shifts->isEmpty())
            <p style="padding:1.5rem;color:var(--c-muted,#64748b);">Keine Schichten in dieser Woche.</p>
        @else
        <table class="table">
            <thead>
                <tr>
                    <th>Tag</th>
                    <th>Mitarbeiter</th>
                    <th>Bereich</th>
                    <th>Geplant von</th>
                    <th>Bis</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @for($d = 0; $d < 7; $d++)
                    @php $day = $monday->copy()->addDays($d); $key = $day->format('Y-m-d'); @endphp
                    @if(isset($byDay[$key]))
                        @foreach($byDay[$key] as $shift)
                        <tr>
                            <td>
                                <strong>{{ $day->isoFormat('ddd DD.MM.') }}</strong>
                            </td>
                            <td>{{ $shift->employee->full_name }}</td>
                            <td>{{ $shift->shiftArea?->name ?? '—' }}</td>
                            <td>{{ $shift->planned_start->format('H:i') }}</td>
                            <td>{{ $shift->planned_end->format('H:i') }}</td>
                            <td>
                                @php $statusColors = ['planned'=>'secondary','active'=>'success','completed'=>'primary','cancelled'=>'danger']; @endphp
                                <span class="badge badge-{{ $statusColors[$shift->status] ?? 'secondary' }}">{{ ucfirst($shift->status) }}</span>
                            </td>
                            <td>
                                @if($shift->status === 'planned')
                                <form method="POST" action="{{ route('admin.shifts.destroy', $shift) }}" style="display:inline;"
                                      onsubmit="return confirm('Schicht löschen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Löschen</button>
                                </form>
                                @else
                                    <span style="color:var(--c-muted,#64748b);font-size:.85rem;">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @endif
                @endfor
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- Add new shift form --}}
<div class="card">
    <div class="card-header"><h2 style="margin:0;font-size:1.1rem;">Neue Schicht anlegen</h2></div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul style="margin:0;padding-left:1.2rem;">
                    @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('admin.shifts.store') }}">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;align-items:end;">
                <div class="form-group">
                    <label for="employee_id">Mitarbeiter <span style="color:red">*</span></label>
                    <select name="employee_id" id="employee_id" class="form-control" required>
                        <option value="">— wählen —</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" {{ old('employee_id') == $emp->id ? 'selected' : '' }}>
                                {{ $emp->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="shift_area_id">Bereich</label>
                    <select name="shift_area_id" id="shift_area_id" class="form-control">
                        <option value="">— kein Bereich —</option>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}" {{ old('shift_area_id') == $area->id ? 'selected' : '' }}>
                                {{ $area->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="planned_start">Beginn <span style="color:red">*</span></label>
                    <input type="datetime-local" name="planned_start" id="planned_start" class="form-control"
                           value="{{ old('planned_start') }}" required>
                </div>

                <div class="form-group">
                    <label for="planned_end">Ende <span style="color:red">*</span></label>
                    <input type="datetime-local" name="planned_end" id="planned_end" class="form-control"
                           value="{{ old('planned_end') }}" required>
                </div>

                <div class="form-group">
                    <label for="notes">Notiz</label>
                    <input type="text" name="notes" id="notes" class="form-control"
                           value="{{ old('notes') }}" maxlength="500" placeholder="Optional">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width:100%;">Schicht anlegen</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
