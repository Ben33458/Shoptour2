@extends('admin.layout')

@section('title', 'Zeiterfassung')

@section('content')
<div class="page-header">
    <h1>Zeiterfassung</h1>
    <div class="page-actions">
        <a href="{{ route('admin.employees.dashboard') }}" class="btn btn-secondary">Dashboard</a>
        <a href="{{ route('timeclock.index') }}" class="btn btn-secondary" target="_blank">Stempeluhr öffnen</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{-- Date range picker --}}
<form method="GET" style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <label style="font-weight:600;">Zeitraum:</label>
    <input type="date" name="from" value="{{ $from }}"
           style="border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.4rem .75rem;font-size:.9rem;background:var(--c-surface,#fff);color:inherit;">
    <span style="color:var(--c-muted);">bis</span>
    <input type="date" name="to" value="{{ $to }}"
           style="border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.4rem .75rem;font-size:.9rem;background:var(--c-surface,#fff);color:inherit;">
    <button type="submit" class="btn btn-secondary">Anzeigen</button>
    <a href="?from={{ now()->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-m-d') }}&to={{ now()->endOfWeek(\Carbon\Carbon::SUNDAY)->format('Y-m-d') }}"
       class="btn btn-secondary">Diese Woche</a>
    <a href="?from={{ $today }}&to={{ $today }}" class="btn btn-secondary">Heute</a>
</form>

{{-- Open entries --}}
@if($open->count() > 0)
<div class="card" style="margin-bottom:2rem;border-left:4px solid var(--c-warning,#d97706);">
    <div class="card-header">
        <h2 style="margin:0;font-size:1.1rem;">Aktuell eingestempelt ({{ $open->count() }})</h2>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr><th>Mitarbeiter</th><th>Eingestempelt</th><th>Schicht</th><th>Dauer</th><th>Überschreitung</th></tr>
            </thead>
            <tbody>
                @foreach($open as $entry)
                @php
                    $overtimeMin = null;
                    if ($entry->shift && now()->gt($entry->shift->planned_end)) {
                        $overtimeMin = (int) $entry->shift->planned_end->diffInMinutes(now());
                    }
                @endphp
                <tr>
                    <td>{{ $entry->employee->full_name }}</td>
                    <td>{{ $entry->clocked_in_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $entry->shift ? $entry->shift->planned_start->format('H:i') . '–' . $entry->shift->planned_end->format('H:i') : 'Ad-hoc' }}</td>
                    <td>{{ $entry->clocked_in_at->diffForHumans(null, true) }}</td>
                    <td>
                        @if($overtimeMin !== null)
                            @php $oh = intdiv($overtimeMin,60); $om = str_pad($overtimeMin%60,2,'0',STR_PAD_LEFT); @endphp
                            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                                <span style="color:var(--c-danger,#dc2626);font-weight:600;">+{{ $oh }}:{{ $om }} h</span>
                                <details style="display:inline-block;">
                                    <summary class="btn btn-sm btn-warning" style="cursor:pointer;list-style:none;">Korrigieren</summary>
                                    <div style="position:absolute;z-index:10;background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.75rem;margin-top:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);min-width:280px;">
                                        <form method="POST" action="{{ route('admin.time.correct', $entry) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="clocked_in_at" value="{{ $entry->clocked_in_at->format('Y-m-d\TH:i') }}">
                                            <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Ausstempeln auf:</label>
                                            <div style="display:flex;gap:.4rem;align-items:center;">
                                                <input type="datetime-local" name="clocked_out_at"
                                                       value="{{ $entry->shift->planned_end->format('Y-m-d\TH:i') }}"
                                                       style="border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.35rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;">
                                                <button type="submit" class="btn btn-sm btn-primary">OK</button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        @else
                            <span style="color:var(--c-muted);">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Entries for selected range --}}
<div class="card">
    <div class="card-header">
        <h2 style="margin:0;font-size:1.1rem;">
            Einträge
            {{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}
            @if($from !== $to) – {{ \Carbon\Carbon::parse($to)->format('d.m.Y') }} @endif
            ({{ $entries->count() }})
        </h2>
    </div>
    <div class="card-body" style="padding:0;">
        @if($entries->isEmpty())
            <p style="padding:1.5rem;color:var(--c-muted,#64748b);">Keine Einträge für diesen Zeitraum.</p>
        @else
        <table class="table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Mitarbeiter</th>
                    <th>Eingestempelt</th>
                    <th>Ausgestempelt</th>
                    <th>Pause (min)</th>
                    <th>Netto</th>
                    <th>Verspätung</th>
                    <th>Überschreitung</th>
                    <th>Compliance</th>
                    <th>Korrektur</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entries as $entry)
                @php
                    $lateMin = null;
                    if ($entry->shift && $entry->clocked_in_at->gt($entry->shift->planned_start->addMinute())) {
                        $lateMin = (int) $entry->shift->planned_start->diffInMinutes($entry->clocked_in_at);
                    }
                    $overMin = null;
                    if ($entry->shift && $entry->clocked_out_at && $entry->clocked_out_at->gt($entry->shift->planned_end->addMinute())) {
                        $overMin = (int) $entry->shift->planned_end->diffInMinutes($entry->clocked_out_at);
                    }
                    $fmtMin = fn(int $m) => intdiv($m,60).':'.str_pad($m%60,2,'0',STR_PAD_LEFT).' h';
                    $netLabel = $entry->net_minutes !== null ? $fmtMin((int)$entry->net_minutes) : '—';
                @endphp
                <tr>
                    <td>{{ $entry->clocked_in_at->format('d.m.Y') }}</td>
                    <td>{{ $entry->employee->full_name }}</td>
                    <td>{{ $entry->clocked_in_at->format('H:i') }}</td>
                    <td>{{ $entry->clocked_out_at?->format('H:i') ?? '—' }}</td>
                    <td>{{ $entry->break_minutes ?? '—' }}</td>
                    <td>{{ $netLabel }}</td>

                    {{-- Verspätung --}}
                    <td>
                        @if($lateMin !== null)
                            <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
                                <span style="color:var(--c-warning,#d97706);font-weight:600;">+{{ $fmtMin($lateMin) }}</span>
                                @if($entry->shift)
                                <details style="display:inline-block;">
                                    <summary class="btn btn-sm btn-secondary" style="cursor:pointer;list-style:none;font-size:.75rem;">Korr.</summary>
                                    <div style="position:absolute;z-index:10;background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.75rem;margin-top:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);min-width:280px;">
                                        <form method="POST" action="{{ route('admin.time.correct', $entry) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="clocked_out_at" value="{{ $entry->clocked_out_at?->format('Y-m-d\TH:i') }}">
                                            <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Einstempeln auf:</label>
                                            <div style="display:flex;gap:.4rem;align-items:center;">
                                                <input type="datetime-local" name="clocked_in_at"
                                                       value="{{ $entry->shift->planned_start->format('Y-m-d\TH:i') }}"
                                                       style="border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.35rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;">
                                                <button type="submit" class="btn btn-sm btn-primary">OK</button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                                @endif
                            </div>
                        @else
                            <span style="color:var(--c-muted);">—</span>
                        @endif
                    </td>

                    {{-- Überschreitung --}}
                    <td>
                        @if($overMin !== null)
                            <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
                                <span style="color:var(--c-danger,#dc2626);font-weight:600;">+{{ $fmtMin($overMin) }}</span>
                                @if($entry->shift)
                                <details style="display:inline-block;">
                                    <summary class="btn btn-sm btn-secondary" style="cursor:pointer;list-style:none;font-size:.75rem;">Korr.</summary>
                                    <div style="position:absolute;z-index:10;background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.75rem;margin-top:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);min-width:280px;">
                                        <form method="POST" action="{{ route('admin.time.correct', $entry) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="clocked_in_at" value="{{ $entry->clocked_in_at->format('Y-m-d\TH:i') }}">
                                            <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Ausstempeln auf:</label>
                                            <div style="display:flex;gap:.4rem;align-items:center;">
                                                <input type="datetime-local" name="clocked_out_at"
                                                       value="{{ $entry->shift->planned_end->format('Y-m-d\TH:i') }}"
                                                       style="border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.35rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;">
                                                <button type="submit" class="btn btn-sm btn-primary">OK</button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                                @endif
                            </div>
                        @else
                            <span style="color:var(--c-muted);">—</span>
                        @endif
                    </td>

                    {{-- Compliance --}}
                    <td>
                        @php
                            $complianceBadge = match($entry->compliance_status) {
                                'ok'      => 'success',
                                'warning' => 'warning',
                                'breach'  => 'danger',
                                default   => 'secondary',
                            };
                        @endphp
                        <span class="badge badge-{{ $complianceBadge }}">
                            {{ ucfirst($entry->compliance_status ?? 'unbekannt') }}
                        </span>
                        @if(!empty($entry->compliance_notes))
                            <details style="display:inline-block;margin-left:.5rem;">
                                <summary style="cursor:pointer;font-size:.8rem;">Details</summary>
                                <ul style="font-size:.8rem;margin:.25rem 0 0 1rem;">
                                    @foreach((array)$entry->compliance_notes as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </td>

                    {{-- Manuelle Korrektur --}}
                    <td>
                        <details>
                            <summary class="btn btn-sm btn-secondary" style="cursor:pointer;list-style:none;">Bearbeiten</summary>
                            <div style="position:absolute;z-index:10;background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.75rem;margin-top:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);min-width:320px;">
                                <form method="POST" action="{{ route('admin.time.correct', $entry) }}">
                                    @csrf @method('PATCH')
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.5rem;">
                                        <div>
                                            <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.2rem;">Eingestempelt</label>
                                            <input type="datetime-local" name="clocked_in_at" class="form-control"
                                                   value="{{ $entry->clocked_in_at->format('Y-m-d\TH:i') }}" required
                                                   style="font-size:.85rem;">
                                        </div>
                                        <div>
                                            <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.2rem;">Ausgestempelt</label>
                                            <input type="datetime-local" name="clocked_out_at" class="form-control"
                                                   value="{{ $entry->clocked_out_at?->format('Y-m-d\TH:i') }}"
                                                   style="font-size:.85rem;">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">Speichern</button>
                                </form>
                            </div>
                        </details>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
