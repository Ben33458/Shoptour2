@extends('admin.layout')

@section('title', 'Urlaubsantrag')

@section('content')
<div class="page-header">
    <h1>Urlaubsantrag stellen</h1>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0;padding-left:1.2rem;">
            @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    </div>
@endif

{{-- Employee selection --}}
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-body">
        <form method="GET" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;min-width:250px;">
                <label for="employee_id"><strong>Mitarbeiter auswählen:</strong></label>
                <select name="employee_id" id="employee_id" class="form-control" onchange="this.form.submit()">
                    <option value="">— Mitarbeiter wählen —</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ (isset($employee) && $employee?->id == $emp->id) ? 'selected' : '' }}>
                            {{ $emp->full_name }} ({{ $emp->employee_number }})
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

@if($employee)

{{-- Vacation balance --}}
@if($balance)
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><h2 style="margin:0;font-size:1rem;">Urlaubskonto {{ now()->year }} — {{ $employee->full_name }}</h2></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;text-align:center;">
            <div>
                <div style="font-size:1.8rem;font-weight:700;color:var(--c-primary,#2563eb);">{{ $balance->total_days + $balance->carried_over }}</div>
                <div style="color:var(--c-muted,#64748b);font-size:.85rem;">Gesamt verfügbar</div>
            </div>
            <div>
                <div style="font-size:1.8rem;font-weight:700;color:var(--c-success,#16a34a);">{{ $balance->used_days }}</div>
                <div style="color:var(--c-muted,#64748b);font-size:.85rem;">Genommen</div>
            </div>
            <div>
                <div style="font-size:1.8rem;font-weight:700;color:var(--c-warning,#d97706);">{{ ($balance->total_days + $balance->carried_over) - $balance->used_days }}</div>
                <div style="color:var(--c-muted,#64748b);font-size:.85rem;">Verbleibend</div>
            </div>
            @if($balance->carried_over > 0)
            <div>
                <div style="font-size:1.8rem;font-weight:700;color:var(--c-muted,#64748b);">{{ $balance->carried_over }}</div>
                <div style="color:var(--c-muted,#64748b);font-size:.85rem;">Übertrag</div>
            </div>
            @endif
        </div>
    </div>
</div>
@endif

{{-- New request form --}}
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><h2 style="margin:0;font-size:1rem;">Neuer Urlaubsantrag</h2></div>
    <div class="card-body">
        <form method="POST" action="{{ route('vacation.store') }}">
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:end;">
                <div class="form-group">
                    <label for="start_date">Von <span style="color:red">*</span></label>
                    <input type="date" name="start_date" id="start_date" class="form-control"
                           value="{{ old('start_date') }}" required min="{{ today()->format('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label for="end_date">Bis <span style="color:red">*</span></label>
                    <input type="date" name="end_date" id="end_date" class="form-control"
                           value="{{ old('end_date') }}" required min="{{ today()->format('Y-m-d') }}">
                </div>
                <div class="form-group" style="grid-column:span 2;">
                    <label for="notes">Anmerkung</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2"
                              maxlength="500" placeholder="Optional">{{ old('notes') }}</textarea>
                </div>
                <div style="grid-column:span 2;">
                    <button type="submit" class="btn btn-primary">Urlaubsantrag einreichen</button>
                    <small style="color:var(--c-muted,#64748b);margin-left:1rem;">
                        Arbeitstage werden automatisch berechnet (ohne Wochenenden und Feiertage).
                    </small>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Past requests --}}
<div class="card">
    <div class="card-header"><h2 style="margin:0;font-size:1rem;">Bisherige Anträge</h2></div>
    <div class="card-body" style="padding:0;">
        @if($requests->isEmpty())
            <p style="padding:1.5rem;color:var(--c-muted,#64748b);">Noch keine Urlaubsanträge gestellt.</p>
        @else
        <table class="table">
            <thead>
                <tr>
                    <th>Von</th>
                    <th>Bis</th>
                    <th>Tage</th>
                    <th>Status</th>
                    <th>Eingereicht</th>
                    <th>Anmerkung</th>
                </tr>
            </thead>
            <tbody>
                @foreach($requests as $req)
                <tr>
                    <td>{{ $req->start_date->format('d.m.Y') }}</td>
                    <td>{{ $req->end_date->format('d.m.Y') }}</td>
                    <td>{{ $req->days_requested }}</td>
                    <td>
                        @php
                            $badge = match($req->status) {
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default    => 'warning',
                            };
                            $label = match($req->status) {
                                'approved' => 'Genehmigt',
                                'rejected' => 'Abgelehnt',
                                default    => 'Ausstehend',
                            };
                        @endphp
                        <span class="badge badge-{{ $badge }}">{{ $label }}</span>
                    </td>
                    <td>{{ $req->created_at->format('d.m.Y') }}</td>
                    <td>{{ $req->notes ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

@else
    <div style="text-align:center;padding:3rem;color:var(--c-muted,#64748b);">
        Bitte einen Mitarbeiter auswählen, um Urlaubsanträge anzuzeigen.
    </div>
@endif
@endsection
