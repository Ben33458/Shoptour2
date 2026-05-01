@extends('admin.layout')

@section('title', 'Schichtberichte')

@section('content')
<div class="page-header">
    <h1>Schichtberichte</h1>
</div>

@include('admin._partials.shifts-tabs')

{{-- Date range picker --}}
<form method="GET" action="{{ route('admin.shifts.reports.index') }}"
      style="margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
    <label style="font-weight:600;">Zeitraum:</label>
    <input type="date" name="from" value="{{ $from }}"
           style="border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.4rem .75rem;font-size:.9rem;background:var(--c-surface,#fff);color:inherit;">
    <span style="color:var(--c-muted);">bis</span>
    <input type="date" name="to" value="{{ $to }}"
           style="border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.4rem .75rem;font-size:.9rem;background:var(--c-surface,#fff);color:inherit;">
    <button type="submit" class="btn btn-secondary">Anzeigen</button>
    <a href="?from={{ now()->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-m-d') }}&to={{ now()->endOfWeek(\Carbon\Carbon::SUNDAY)->format('Y-m-d') }}"
       class="btn btn-secondary">Diese Woche</a>
    <a href="?from={{ now()->format('Y-m-d') }}&to={{ now()->format('Y-m-d') }}"
       class="btn btn-secondary">Heute</a>
</form>

{{-- Shifts without a report --}}
<div class="card" style="margin-bottom:2rem;">
    <div class="card-header">
        <h2 style="margin:0;font-size:1.1rem;">
            Schichten ohne Bericht —
            {{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}
            @if($from !== $to) – {{ \Carbon\Carbon::parse($to)->format('d.m.Y') }} @endif
            ({{ $shiftsWithoutReport->count() }})
        </h2>
    </div>
    <div class="card-body" style="padding:0;">
        @if($shiftsWithoutReport->isEmpty())
            <p style="padding:1rem;color:var(--c-muted,#64748b);">Alle Schichten haben bereits einen Bericht.</p>
        @else
        <table class="table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Zeit</th>
                    <th>Mitarbeiter</th>
                    <th>Bereich</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($shiftsWithoutReport as $shift)
                <tr>
                    <td>{{ $shift->planned_start->format('d.m.Y') }}</td>
                    <td>{{ $shift->planned_start->format('H:i') }} – {{ $shift->planned_end->format('H:i') }} Uhr</td>
                    <td>{{ $shift->employee->full_name }}</td>
                    <td>{{ $shift->shiftArea?->name ?? '—' }}</td>
                    <td>
                        @php $statusColors = ['planned'=>'secondary','active'=>'success','completed'=>'primary','cancelled'=>'danger']; @endphp
                        <span class="badge badge-{{ $statusColors[$shift->status] ?? 'secondary' }}">{{ ucfirst($shift->status) }}</span>
                    </td>
                    <td>
                        <a href="{{ route('admin.shifts.reports.create', $shift) }}" class="btn btn-primary" style="font-size:.8rem;padding:.35rem .75rem;">
                            Bericht erstellen
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- Reports of the range --}}
<div class="card">
    <div class="card-header">
        <h2 style="margin:0;font-size:1.1rem;">
            Berichte —
            {{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}
            @if($from !== $to) – {{ \Carbon\Carbon::parse($to)->format('d.m.Y') }} @endif
            ({{ $reports->count() }})
        </h2>
    </div>
    <div class="card-body" style="padding:0;">
        @if($reports->isEmpty())
            <p style="padding:1rem;color:var(--c-muted,#64748b);">Keine Berichte für diesen Zeitraum vorhanden.</p>
        @else
        <table class="table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Mitarbeiter</th>
                    <th>Bereich</th>
                    <th>Zeit</th>
                    <th>Kassendifferenz</th>
                    <th>Vorfall</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($reports as $report)
                <tr>
                    <td>{{ $report->shift->planned_start->format('d.m.Y') }}</td>
                    <td>{{ $report->shift->employee->full_name }}</td>
                    <td>{{ $report->shift->shiftArea?->name ?? '—' }}</td>
                    <td>{{ $report->shift->planned_start->format('H:i') }} – {{ $report->shift->planned_end->format('H:i') }}</td>
                    <td>
                        @if($report->cash_difference === null)
                            <span style="color:var(--c-muted,#64748b);">—</span>
                        @else
                            @php
                                $diff = (float) $report->cash_difference;
                                if ($diff == 0) {
                                    $diffColor = 'var(--c-success,#16a34a)';
                                } elseif (abs($diff) <= 5) {
                                    $diffColor = 'var(--c-warning,#d97706)';
                                } else {
                                    $diffColor = 'var(--c-danger,#dc2626)';
                                }
                            @endphp
                            <span style="color:{{ $diffColor }};font-weight:600;">
                                {{ $diff >= 0 ? '+' : '' }}{{ number_format($diff, 2, ',', '.') }} €
                            </span>
                        @endif
                    </td>
                    <td>
                        @if($report->incident_level === 'none')
                            <span class="badge badge-secondary">Kein</span>
                        @elseif($report->incident_level === 'minor')
                            <span class="badge badge-warning">Klein</span>
                        @else
                            <span class="badge badge-danger">Schwerwiegend</span>
                        @endif
                    </td>
                    <td>
                        @if($report->is_submitted)
                            <span class="badge badge-success">Abgeschlossen</span>
                        @else
                            <span class="badge badge-secondary">Offen</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.shifts.reports.edit', $report) }}" class="btn btn-secondary" style="font-size:.8rem;padding:.35rem .75rem;">
                            Bearbeiten
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
