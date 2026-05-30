@extends('mein.layout')

@section('title', 'Schichtberichte')

@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:8px;">
    <h1 style="font-size:1.3rem; font-weight:700; margin:0;">Schichtberichte</h1>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

{{-- Fehlende Schichtberichte --}}
@if($shiftsWithoutReport->isNotEmpty())
<div class="mein-card" style="border-left:3px solid var(--c-warning,#f59e0b); margin-bottom:1.5rem;">
    <div class="mein-card-title" style="margin-bottom:.75rem;">Fehlende Schichtberichte</div>
    <div style="display:flex; flex-wrap:wrap; gap:.5rem;">
        @foreach($shiftsWithoutReport as $shift)
        <a href="{{ route('mein.schicht.nachtrag', ['date' => $shift->planned_start->toDateString()]) }}"
           style="display:inline-flex; align-items:center; gap:.4rem; padding:.4rem .9rem; background:var(--c-bg); border:1px solid var(--c-warning,#f59e0b); border-radius:6px; text-decoration:none; font-size:.85rem; color:var(--c-text);">
            ＋ {{ $shift->planned_start->locale('de')->isoFormat('dd, D. MMM') }}
            @if($shift->shiftArea)
                <span style="color:var(--c-muted); font-size:.78rem;">· {{ $shift->shiftArea->name ?? '' }}</span>
            @endif
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- Vergangene Berichte --}}
<div class="mein-card" style="padding:0;">
    <div style="padding:.75rem 1rem; border-bottom:1px solid var(--c-border); display:flex; justify-content:space-between; align-items:center;">
        <strong style="font-size:.95rem;">Letzte 30 Berichte</strong>
        <a href="{{ route('mein.schicht.nachtrag', ['date' => today()->subDay()->toDateString()]) }}"
           style="font-size:.8rem; color:var(--c-primary); text-decoration:none;">
            + Bericht nachtragen
        </a>
    </div>
    @if($reports->isEmpty())
        <div style="padding:2rem; text-align:center; color:var(--c-muted);">Noch keine Schichtberichte vorhanden.</div>
    @else
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Kassendiff.</th>
                    <th>Vorfall</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($reports as $report)
                <tr>
                    <td>
                        <span style="font-weight:600; font-size:.9rem;">
                            {{ $report->report_date->locale('de')->isoFormat('dd, D. MMM YYYY') }}
                        </span>
                    </td>
                    <td style="font-size:.9rem;">
                        @if($report->cash_difference != 0)
                            <span style="color:{{ $report->cash_difference > 0 ? '#16a34a' : '#dc2626' }}; font-weight:600;">
                                {{ number_format($report->cash_difference, 2, ',', '.') }} €
                            </span>
                        @else
                            <span style="color:var(--c-muted);">±0</span>
                        @endif
                    </td>
                    <td>
                        @if($report->incident_level > 0)
                            <span style="color:#dc2626; font-size:.85rem; font-weight:600;">
                                Stufe {{ $report->incident_level }}
                            </span>
                        @else
                            <span style="color:var(--c-muted); font-size:.85rem;">—</span>
                        @endif
                    </td>
                    <td>
                        @if($report->is_submitted)
                            <span style="color:#16a34a; font-size:.8rem; font-weight:600;">✓ Abgeschlossen</span>
                        @else
                            <span style="color:#d97706; font-size:.8rem;">Entwurf</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('mein.schicht.nachtrag', ['date' => $report->report_date->toDateString()]) }}"
                           style="font-size:.8rem; color:var(--c-primary); text-decoration:none;">
                            Bearbeiten
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@endsection
