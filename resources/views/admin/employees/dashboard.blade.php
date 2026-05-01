@extends('admin.layout')

@section('title', 'Mitarbeiter — Dashboard')

@section('content')
<div class="page-header">
    <h1>Mitarbeiter-Dashboard</h1>
    <div class="page-actions">
        <a href="{{ route('admin.employees.index') }}" class="btn btn-secondary">Mitarbeiterliste</a>
        <a href="{{ route('admin.shifts.index') }}" class="btn btn-secondary">Schichtplan</a>
        <a href="{{ route('admin.vacation.index') }}" class="btn btn-secondary">Urlaub</a>
        <a href="{{ route('admin.time.index') }}" class="btn btn-secondary">Zeiterfassung</a>
    </div>
</div>

{{-- Stat Cards --}}
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem;">
    <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--c-primary,#2563eb);">{{ $totalActive }}</div>
        <div style="color:var(--c-muted,#64748b);font-size:.9rem;margin-top:.25rem;">Aktive Mitarbeiter</div>
    </div>
    <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--c-success,#16a34a);">{{ $activeEntries->count() }}</div>
        <div style="color:var(--c-muted,#64748b);font-size:.9rem;margin-top:.25rem;">Aktuell eingestempelt</div>
    </div>
    <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:{{ $pendingVacations > 0 ? 'var(--c-warning,#d97706)' : 'var(--c-muted,#64748b)' }};">{{ $pendingVacations }}</div>
        <div style="color:var(--c-muted,#64748b);font-size:.9rem;margin-top:.25rem;">Offene Urlaubsanträge</div>
    </div>
    <div class="stat-card" style="background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:8px;padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:{{ $complianceWarnings > 0 ? 'var(--c-danger,#dc2626)' : 'var(--c-muted,#64748b)' }};">{{ $complianceWarnings }}</div>
        <div style="color:var(--c-muted,#64748b);font-size:.9rem;margin-top:.25rem;">Compliance-Warnungen (7 Tage)</div>
    </div>
</div>

{{-- Currently clocked in --}}
@if($activeEntries->count() > 0)
<div class="card" style="margin-bottom:2rem;">
    <div class="card-header"><h2 style="margin:0;font-size:1.1rem;">Aktuell eingestempelt</h2></div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Eingestempelt um</th>
                    <th>Dauer</th>
                    <th>Pausenstatus</th>
                </tr>
            </thead>
            <tbody>
                @foreach($activeEntries as $entry)
                <tr>
                    <td>{{ $entry->employee->full_name }}</td>
                    <td>{{ $entry->clocked_in_at->format('H:i') }} Uhr</td>
                    <td>{{ $entry->clocked_in_at->diffForHumans(null, true) }}</td>
                    <td>
                        @if($entry->breakSegments->where('ended_at', null)->count() > 0)
                            <span class="badge badge-warning">Pause aktiv</span>
                        @else
                            <span class="badge badge-success">Arbeitet</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Today's shifts --}}
<div class="card">
    <div class="card-header">
        <h2 style="margin:0;font-size:1.1rem;">Schichten heute — {{ now()->format('d.m.Y') }}</h2>
    </div>
    <div class="card-body" style="padding:0;">
        @if($todayShifts->isEmpty())
            <p style="padding:1rem;color:var(--c-muted,#64748b);">Keine Schichten für heute geplant.</p>
        @else
        <table class="table">
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Bereich</th>
                    <th>Geplant von</th>
                    <th>Geplant bis</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($todayShifts as $shift)
                <tr>
                    <td>{{ $shift->employee->full_name }}</td>
                    <td>{{ $shift->shiftArea?->name ?? '—' }}</td>
                    <td>{{ $shift->planned_start->format('H:i') }}</td>
                    <td>{{ $shift->planned_end->format('H:i') }}</td>
                    <td>
                        @php $statusColors = ['planned'=>'secondary','active'=>'success','completed'=>'primary','cancelled'=>'danger']; @endphp
                        <span class="badge badge-{{ $statusColors[$shift->status] ?? 'secondary' }}">{{ ucfirst($shift->status) }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
