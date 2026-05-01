@extends('admin.layout')

@section('title', 'Ninox-Import')

@section('content')

@if(session('success'))
    <div style="margin-bottom:16px;padding:12px 16px;background:color-mix(in srgb,var(--c-success) 15%,var(--c-surface));border:1px solid var(--c-success);border-radius:6px;color:var(--c-success)">
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div style="margin-bottom:16px;padding:12px 16px;background:color-mix(in srgb,var(--c-danger) 15%,var(--c-surface));border:1px solid var(--c-danger);border-radius:6px;color:var(--c-danger)">
        {{ session('error') }}
    </div>
@endif

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:var(--c-text)">Ninox-Import</h1>
        <p style="font-size:13px;color:var(--c-muted);margin-top:4px">
            Importiert alle Tabellen und Datensätze aus der konfigurierten Ninox-Datenbank als Rohdaten.
        </p>
    </div>
    <form method="POST" action="{{ route('admin.ninox-import.run') }}">
        @csrf
        <button type="submit"
                style="padding:10px 20px;background:#7c3aed;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
            ▶ Import jetzt starten
        </button>
    </form>
</div>

@php $apiKey = config('services.ninox.api_key'); @endphp
@if(!$apiKey)
    <div style="margin-bottom:16px;padding:12px 16px;background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;color:#92400e">
        <strong>Hinweis:</strong> Kein NINOX_API_KEY konfiguriert. Bitte in der <code>.env</code> eintragen.
    </div>
@endif

<div class="card">
    <div class="card-header">Importläufe ({{ $runs->total() }})</div>
    @if($runs->isEmpty())
        <div style="padding:40px;text-align:center;color:var(--c-muted)">
            Noch kein Import gestartet. Klicke auf „Import jetzt starten".
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gestartet</th>
                        <th>Status</th>
                        <th>Tabellen</th>
                        <th>Datensätze</th>
                        <th>Dauer</th>
                        <th>Ausgeführt von</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($runs as $run)
                    @php
                        $statusColor = match($run->status) {
                            'completed' => 'color:#10b981',
                            'running'   => 'color:#f59e0b',
                            'failed'    => 'color:var(--c-danger)',
                            default     => 'color:var(--c-muted)',
                        };
                        $statusLabel = match($run->status) {
                            'completed' => '✓ Abgeschlossen',
                            'running'   => '⏳ Läuft …',
                            'failed'    => '✗ Fehler',
                            default     => $run->status,
                        };
                    @endphp
                    <tr>
                        <td style="font-family:monospace;font-size:12px">#{{ $run->id }}</td>
                        <td style="font-size:13px">{{ $run->started_at->format('d.m.Y H:i:s') }}</td>
                        <td style="{{ $statusColor }};font-weight:600;font-size:13px">{{ $statusLabel }}</td>
                        <td style="text-align:center">{{ $run->tables_count }}</td>
                        <td style="text-align:center">{{ number_format($run->records_imported) }}</td>
                        <td style="font-size:12px;color:var(--c-muted)">{{ $run->duration() ?? '—' }}</td>
                        <td style="font-size:13px;color:var(--c-muted)">{{ $run->createdBy?->name ?? 'System' }}</td>
                        <td>
                            <a href="{{ route('admin.ninox-import.show', $run) }}"
                               class="btn btn-sm btn-outline">Details</a>
                        </td>
                    </tr>
                    @if($run->status === 'failed' && $run->error_message)
                        <tr>
                            <td colspan="8" style="font-size:12px;color:var(--c-danger);padding:4px 14px 8px">
                                {{ $run->error_message }}
                            </td>
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:12px 16px">{{ $runs->links() }}</div>
    @endif
</div>

@endsection
