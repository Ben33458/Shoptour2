@extends('admin.layout')

@section('title', 'Ninox-Import #' . $run->id)

@section('content')

<div style="margin-bottom:16px">
    <a href="{{ route('admin.ninox-import.index') }}" style="font-size:13px;color:var(--c-muted)">← Alle Importläufe</a>
</div>

@php
    $statusColor = match($run->status) {
        'completed' => '#10b981',
        'running'   => '#f59e0b',
        'failed'    => 'var(--c-danger)',
        default     => 'var(--c-muted)',
    };
@endphp

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
    @foreach([
        ['label' => 'Status',      'value' => ucfirst($run->status), 'color' => $statusColor],
        ['label' => 'Tabellen',    'value' => $run->tables_count,    'color' => 'var(--c-text)'],
        ['label' => 'Importiert',  'value' => number_format($run->records_imported), 'color' => '#10b981'],
        ['label' => 'Dauer',       'value' => $run->duration() ?? '—',  'color' => 'var(--c-text)'],
    ] as $stat)
        <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px;padding:16px">
            <div style="font-size:22px;font-weight:700;color:{{ $stat['color'] }}">{{ $stat['value'] }}</div>
            <div style="font-size:12px;color:var(--c-muted);margin-top:2px">{{ $stat['label'] }}</div>
        </div>
    @endforeach
</div>

@if($run->error_message)
    <div style="margin-bottom:16px;padding:12px 16px;background:color-mix(in srgb,var(--c-danger) 15%,var(--c-surface));border:1px solid var(--c-danger);border-radius:6px;color:var(--c-danger)">
        <strong>Fehler:</strong> {{ $run->error_message }}
    </div>
@endif

<div class="card">
    <div class="card-header">Importierte Tabellen ({{ $run->tables->count() }})</div>
    @if($run->tables->isEmpty())
        <div style="padding:30px;text-align:center;color:var(--c-muted)">Keine Tabellen gefunden.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tabellen-ID</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th style="text-align:center">Datensätze</th>
                        <th style="text-align:center">Importiert</th>
                        <th>Fehler</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($run->tables as $table)
                    @php
                        $sc = match($table->status) {
                            'completed' => '#10b981',
                            'importing' => '#f59e0b',
                            'failed'    => 'var(--c-danger)',
                            default     => 'var(--c-muted)',
                        };
                    @endphp
                    <tr>
                        <td style="font-family:monospace;font-size:12px">{{ $table->table_id }}</td>
                        <td style="font-weight:600">{{ $table->table_name }}</td>
                        <td style="color:{{ $sc }};font-size:13px;font-weight:600">{{ ucfirst($table->status) }}</td>
                        <td style="text-align:center">{{ number_format($table->records_count) }}</td>
                        <td style="text-align:center">{{ number_format($table->records_imported) }}</td>
                        <td style="font-size:12px;color:var(--c-danger)">{{ $table->error_message ? Str::limit($table->error_message, 80) : '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
