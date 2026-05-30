@extends('admin.layout')

@section('title', 'Veranstaltungskalender')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
        <a href="{{ route('admin.events.index') }}" style="color:var(--c-muted);font-size:.85rem">&larr; Veranstaltungen</a>
        <h1 style="margin:4px 0;font-size:1.3rem">Kalender — {{ $firstDay->locale('de')->isoFormat('MMMM YYYY') }}</h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <a href="{{ route('admin.events.calendar', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}" class="btn btn-outline">&laquo; {{ $prevMonth->locale('de')->isoFormat('MMM') }}</a>
        <a href="{{ route('admin.events.calendar') }}" class="btn btn-outline">Heute</a>
        <a href="{{ route('admin.events.calendar', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" class="btn btn-outline">{{ $nextMonth->locale('de')->isoFormat('MMM') }} &raquo;</a>
    </div>
</div>

{{-- ── Legende ── --}}
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;font-size:.78rem">
    <span style="display:flex;gap:4px;align-items:center"><span style="background:#94a3b8;width:12px;height:12px;border-radius:3px;display:inline-block"></span>Anfrage</span>
    <span style="display:flex;gap:4px;align-items:center"><span style="background:#3b82f6;width:12px;height:12px;border-radius:3px;display:inline-block"></span>Gesendet</span>
    <span style="display:flex;gap:4px;align-items:center"><span style="background:#22c55e;width:12px;height:12px;border-radius:3px;display:inline-block"></span>Angenommen</span>
    <span style="display:flex;gap:4px;align-items:center"><span style="background:#16a34a;width:12px;height:12px;border-radius:3px;display:inline-block"></span>Geliefert</span>
    <span style="display:flex;gap:4px;align-items:center"><span style="background:#f97316;width:12px;height:12px;border-radius:3px;display:inline-block"></span>Abholung offen</span>
    <span style="display:flex;gap:4px;align-items:center"><span style="background:#6b7280;width:12px;height:12px;border-radius:3px;display:inline-block"></span>Abgeschlossen</span>
    <span style="display:flex;gap:4px;align-items:center"><span style="background:#ef4444;width:12px;height:12px;border-radius:3px;display:inline-block"></span>Konflikt</span>
</div>

@php
    $eventColorMap = [
        'requested'          => '#94a3b8',
        'needs_clarification'=> '#f59e0b',
        'draft'              => '#cbd5e1',
        'sent'               => '#3b82f6',
        'accepted'           => '#22c55e',
        'delivered'          => '#16a34a',
        'pickup_planned'     => '#f97316',
        'closed'             => '#6b7280',
        'converted_to_order' => '#8b5cf6',
        'rejected'           => '#ef4444',
        'cancelled'          => '#94a3b8',
    ];

    // Group occurrences by day
    $eventsByDay = [];
    foreach ($occurrences as $occ) {
        $day = $occ->event_start_at?->day;
        if ($day) {
            $eventsByDay[$day][] = $occ;
        }
    }

    // Build calendar grid
    $startOfWeek = $firstDay->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $endOfWeek   = $lastDay->copy()->endOfWeek(\Carbon\Carbon::MONDAY);
    $weeks       = [];
    $current     = $startOfWeek->copy();
    while ($current <= $endOfWeek) {
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $week[] = $current->copy();
            $current->addDay();
        }
        $weeks[] = $week;
    }
@endphp

<div class="card">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr>
                @foreach(['Mo','Di','Mi','Do','Fr','Sa','So'] as $wd)
                    <th style="padding:8px;text-align:center;font-size:.8rem;border-bottom:1px solid var(--c-border);color:var(--c-muted)">{{ $wd }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($weeks as $week)
                <tr>
                    @foreach($week as $day)
                        @php
                            $isCurrentMonth = $day->month === $month;
                            $isToday        = $day->isToday();
                            $dayEvents      = $eventsByDay[$day->day] ?? [];
                        @endphp
                        <td style="
                            vertical-align:top;
                            padding:6px;
                            height:100px;
                            border:1px solid var(--c-border);
                            background:{{ $isToday ? 'var(--c-bg)' : 'var(--c-surface)' }};
                            opacity:{{ $isCurrentMonth ? '1' : '0.4' }};
                        ">
                            <div style="font-size:.8rem;font-weight:{{ $isToday ? '700' : '400' }};margin-bottom:4px;color:{{ $isToday ? 'var(--c-primary)' : 'var(--c-text)' }}">
                                {{ $day->day }}
                            </div>
                            @foreach($dayEvents as $occ)
                                @php
                                    $color = $eventColorMap[$occ->offer_status] ?? '#94a3b8';
                                @endphp
                                <a href="{{ route('admin.events.show', $occ) }}"
                                   style="display:block;background:{{ $color }};color:#fff;border-radius:4px;padding:1px 5px;font-size:.7rem;margin-bottom:2px;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                   title="{{ $occ->title }}">
                                    {{ $occ->title }}
                                </a>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
