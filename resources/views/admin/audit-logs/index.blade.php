@extends('admin.layout')

@section('title', 'Systemprotokoll')

@section('content')
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Systemprotokoll</span>
        <span style="font-size:.85em;color:var(--c-muted)">{{ $logs->total() }} Einträge</span>
    </div>
    <div class="card-body">

        {{-- Filter bar --}}
        <form method="GET" action="{{ route('admin.audit-logs.index') }}"
              style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;align-items:flex-end">

            <div class="form-group" style="margin:0;min-width:140px">
                <label style="font-size:.8em;margin-bottom:2px;display:block">Level</label>
                <select name="level" class="form-control" style="height:36px;padding:4px 8px">
                    <option value="">Alle</option>
                    <option value="info"    {{ ($filters['level'] ?? '') === 'info'    ? 'selected' : '' }}>Info</option>
                    <option value="warning" {{ ($filters['level'] ?? '') === 'warning' ? 'selected' : '' }}>Warning</option>
                    <option value="error"   {{ ($filters['level'] ?? '') === 'error'   ? 'selected' : '' }}>Error</option>
                </select>
            </div>

            <div class="form-group" style="margin:0;min-width:200px;flex:1">
                <label style="font-size:.8em;margin-bottom:2px;display:block">Aktion</label>
                <input type="text" name="action" class="form-control" style="height:36px"
                       placeholder="z.B. password_reset"
                       value="{{ $filters['action'] ?? '' }}">
            </div>

            <div class="form-group" style="margin:0">
                <label style="font-size:.8em;margin-bottom:2px;display:block">Von</label>
                <input type="date" name="date_from" class="form-control" style="height:36px;padding:4px 8px"
                       value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div class="form-group" style="margin:0">
                <label style="font-size:.8em;margin-bottom:2px;display:block">Bis</label>
                <input type="date" name="date_until" class="form-control" style="height:36px;padding:4px 8px"
                       value="{{ $filters['date_until'] ?? '' }}">
            </div>

            <button type="submit" class="btn btn-primary" style="height:36px">Filtern</button>
            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline" style="height:36px;line-height:24px">Zurücksetzen</a>
        </form>

        @if($logs->isEmpty())
            <p style="color:var(--c-muted);text-align:center;padding:32px 0">Keine Einträge gefunden.</p>
        @else
        <div style="overflow-x:auto">
            <table class="table" style="font-size:.88em">
                <thead>
                    <tr>
                        <th style="white-space:nowrap">Zeitpunkt</th>
                        <th>Level</th>
                        <th>Aktion</th>
                        <th>Benutzer</th>
                        <th>Subjekt</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr x-data="{ open: false }">
                        <td style="white-space:nowrap;color:var(--c-muted)">
                            {{ $log->created_at->format('d.m.Y H:i:s') }}
                        </td>
                        <td>
                            @if($log->level === 'error')
                                <span class="badge" style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5">error</span>
                            @elseif($log->level === 'warning')
                                <span class="badge" style="background:#fef9c3;color:#92400e;border:1px solid #fde68a">warning</span>
                            @else
                                <span class="badge" style="background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe">info</span>
                            @endif
                        </td>
                        <td style="font-family:monospace;font-size:.85em">{{ $log->action }}</td>
                        <td style="white-space:nowrap">
                            @if($log->user)
                                {{ $log->user->first_name }} {{ $log->user->last_name }}
                            @elseif($log->user_id)
                                <span style="color:var(--c-muted)">#{{ $log->user_id }}</span>
                            @else
                                <span style="color:var(--c-muted)">—</span>
                            @endif
                        </td>
                        <td style="font-size:.85em">
                            @if($log->subject_type)
                                <span style="color:var(--c-muted)">{{ $log->subject_type }}</span>
                                @if($log->subject_id)
                                    <span style="color:var(--c-muted)">#{{ $log->subject_id }}</span>
                                @endif
                            @else
                                <span style="color:var(--c-muted)">—</span>
                            @endif
                        </td>
                        <td>
                            @if($log->meta_json)
                                <button type="button"
                                        @click="open = !open"
                                        style="background:none;border:none;cursor:pointer;font-size:.8em;color:var(--c-primary);padding:0">
                                    <span x-text="open ? 'Einklappen ▲' : 'Details ▼'"></span>
                                </button>
                                <div x-show="open" x-cloak
                                     style="margin-top:4px;background:var(--c-bg-alt,#f9fafb);border:1px solid var(--c-border);border-radius:4px;padding:8px;font-family:monospace;font-size:.78em;white-space:pre-wrap;max-width:400px;word-break:break-all">{{ json_encode($log->meta_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</div>
                            @else
                                <span style="color:var(--c-muted)">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
