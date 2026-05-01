@extends('admin.layout')

@section('title', 'Mitarbeiter-Abgleich (Ninox)')

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

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px">
    @foreach([
        ['label' => 'Gesamt',            'value' => $stats['total'],     'color' => 'var(--c-text)'],
        ['label' => 'Nicht verknüpft',   'value' => $stats['unmatched'], 'color' => '#ef4444'],
        ['label' => 'Auto (unbestätigt)','value' => $stats['auto'],      'color' => '#f59e0b'],
        ['label' => 'Bestätigt',         'value' => $stats['confirmed'], 'color' => '#10b981'],
        ['label' => 'Ignoriert',         'value' => $stats['ignored'],   'color' => 'var(--c-muted)'],
    ] as $stat)
        <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px;padding:16px;text-align:center">
            <div style="font-size:28px;font-weight:700;color:{{ $stat['color'] }}">{{ $stat['value'] }}</div>
            <div style="font-size:12px;color:var(--c-muted);margin-top:4px">{{ $stat['label'] }}</div>
        </div>
    @endforeach
</div>

{{-- Auto-Match --}}
@if($stats['unmatched'] > 0)
<div class="card" style="margin-bottom:20px;border-color:#6366f1">
    <div style="padding:16px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="flex:1">
            <div style="font-weight:600;margin-bottom:4px">Auto-Abgleich</div>
            <div style="font-size:13px;color:var(--c-muted)">
                Verknüpft unzugeordnete Ninox-Mitarbeiter automatisch, wenn die Namensähnlichkeit
                den Mindestwert erreicht.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.reconcile.employees.auto-match') }}">
            @csrf
            <div style="display:flex;align-items:center;gap:8px">
                <label style="font-size:13px;white-space:nowrap">Mindestkonfidenz:</label>
                <select name="min_confidence" style="padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;font-size:13px">
                    <option value="100">100 % (nur exakt)</option>
                    <option value="90">90 %</option>
                    <option value="85" selected>85 % (Standard)</option>
                    <option value="75">75 %</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm" style="background:#6366f1;border-color:#6366f1">
                    Auto-Abgleich starten
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Confirm all auto --}}
@if($stats['auto'] > 0)
<div class="card" style="margin-bottom:20px;border-color:#16a34a">
    <div style="padding:16px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="flex:1">
            <div style="font-weight:600;margin-bottom:4px">Auto-Matches bestätigen</div>
            <div style="font-size:13px;color:var(--c-muted)">
                <strong>{{ $stats['auto'] }} Auto-Match{{ $stats['auto'] === 1 ? '' : 'es' }}</strong>
                warten auf Bestätigung. Dabei wird jeweils <code>ninox_source_id</code> auf dem
                Mitarbeiter-Datensatz gesetzt.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.reconcile.employees.confirm-all') }}">
            @csrf
            <button type="submit" class="btn btn-success btn-sm">
                ✓ Alle Auto-Matches bestätigen
            </button>
        </form>
    </div>
</div>
@endif

{{-- Filter tabs --}}
<div style="display:flex;gap:4px;margin-bottom:16px">
    @foreach([
        ['key' => 'unmatched', 'label' => 'Nicht verknüpft', 'count' => $stats['unmatched']],
        ['key' => 'auto',      'label' => 'Auto (prüfen)',    'count' => $stats['auto']],
        ['key' => 'confirmed', 'label' => 'Bestätigt',        'count' => $stats['confirmed']],
        ['key' => 'ignored',   'label' => 'Ignoriert',        'count' => $stats['ignored']],
        ['key' => 'all',       'label' => 'Alle',             'count' => $stats['total']],
    ] as $tab)
        <a href="{{ route('admin.reconcile.employees', ['filter' => $tab['key']]) }}"
           style="padding:6px 12px;border-radius:4px;font-size:13px;text-decoration:none;border:1px solid var(--c-border);
                  {{ $filter === $tab['key'] ? 'background:var(--c-primary,#2563eb);color:#fff;border-color:transparent' : 'background:var(--c-surface);color:var(--c-text)' }}">
            {{ $tab['label'] }}
            @if($tab['count'] > 0)
                <span style="display:inline-block;min-width:18px;height:18px;line-height:18px;text-align:center;
                             border-radius:9px;font-size:11px;margin-left:4px;
                             {{ $filter === $tab['key'] ? 'background:rgba(255,255,255,0.3)' : 'background:var(--c-bg)' }}">
                    {{ $tab['count'] }}
                </span>
            @endif
        </a>
    @endforeach
</div>

{{-- Search --}}
<form method="GET" action="{{ route('admin.reconcile.employees') }}" style="margin-bottom:12px;display:flex;gap:8px">
    <input type="hidden" name="filter" value="{{ $filter }}">
    <input type="text" name="search" value="{{ $search }}"
           placeholder="Name suchen …"
           style="flex:1;max-width:320px;padding:7px 12px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
    <button type="submit" class="btn btn-sm btn-primary">Suchen</button>
    @if($search !== '')
        <a href="{{ route('admin.reconcile.employees', ['filter' => $filter]) }}"
           class="btn btn-sm btn-outline">× Zurücksetzen</a>
    @endif
</form>

<div class="card">
    <div class="card-header">
        Ninox-Mitarbeiter —
        {{ match($filter) {
            'unmatched' => 'Noch nicht verknüpft',
            'auto'      => 'Automatisch verknüpft (zur Bestätigung)',
            'confirmed' => 'Bestätigt',
            'ignored'   => 'Ignoriert',
            default     => 'Alle',
        } }}
        ({{ count($proposals) }})
    </div>

    @if(empty($proposals))
        <div style="padding:40px;text-align:center;color:var(--c-muted)">
            @if($filter === 'unmatched')
                Alle Ninox-Mitarbeiter sind bereits verknüpft oder ignoriert.
            @elseif($search !== '')
                Keine Ergebnisse für „{{ $search }}".
            @else
                Keine Datensätze in dieser Kategorie.
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px">Ninox-ID</th>
                        <th>Name (Ninox)</th>
                        <th style="width:80px">Status (Ninox)</th>
                        <th style="width:110px">Konfidenz</th>
                        <th>Lokaler Mitarbeiter</th>
                        <th style="width:100px">Status</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($proposals as $p)
                    @php
                        $d         = $p['source_data'];
                        $fullName  = trim(($d['vorname'] ?? '') . ' ' . ($d['nachname'] ?? ''));
                        $spitz     = $d['spitzname'] ?? '';
                        $nStatus   = $d['status'] ?? '';
                        $conf      = $p['confidence'];
                        $candidate = $p['candidate'];
                        $status    = $p['status'];
                        $rule      = $p['rule'] ?? null;

                        $statusLabel = match($status) {
                            'confirmed' => '✓ Bestätigt',
                            'auto'      => '~ Auto',
                            'ignored'   => '— Ignoriert',
                            default     => $candidate ? '? Vorschlag' : '— kein Match',
                        };
                        $statusColor = match($status) {
                            'confirmed' => 'color:#10b981;font-weight:600',
                            'auto'      => 'color:#6366f1',
                            'ignored'   => 'color:var(--c-muted)',
                            default     => $candidate ? 'color:#6366f1' : 'color:var(--c-muted)',
                        };
                    @endphp
                    <tr>
                        <td style="font-family:monospace;font-size:12px">{{ $d['ninox_id'] ?? $p['ninox_id'] }}</td>

                        <td>
                            <div style="font-weight:500">{{ $fullName ?: '—' }}</div>
                            @if($spitz)
                                <div style="font-size:11px;color:var(--c-muted)">"{{ $spitz }}"</div>
                            @endif
                        </td>

                        <td>
                            <span style="font-size:12px;
                                {{ $nStatus === 'Aktiv' ? 'color:#10b981' : 'color:var(--c-muted)' }}">
                                {{ $nStatus ?: '—' }}
                            </span>
                        </td>

                        <td>
                            @if($conf > 0)
                                <div style="display:flex;align-items:center;gap:5px">
                                    <div style="width:50px;height:5px;background:var(--c-border);border-radius:3px;overflow:hidden;flex-shrink:0">
                                        <div style="width:{{ $conf }}%;height:100%;border-radius:3px;
                                                    background:{{ $conf >= 90 ? '#10b981' : ($conf >= 75 ? '#f59e0b' : '#ef4444') }}"></div>
                                    </div>
                                    <span style="font-size:12px">{{ $conf }}%</span>
                                </div>
                                @if($rule)
                                    <div style="font-size:11px;color:var(--c-muted)">{{ $rule }}</div>
                                @endif
                            @else
                                <span style="color:var(--c-muted);font-size:12px">—</span>
                            @endif
                        </td>

                        <td>
                            @if($candidate)
                                <a href="{{ route('admin.employees.edit', $candidate) }}"
                                   style="font-weight:600;color:var(--c-text);text-decoration:underline;text-decoration-color:var(--c-border)">
                                    {{ $candidate->full_name }}
                                </a>
                                @if($candidate->ninox_source_id)
                                    <div style="font-size:11px;color:#10b981">Ninox #{{ $candidate->ninox_source_id }} ✓</div>
                                @endif
                            @else
                                <span style="color:var(--c-muted);font-size:12px">Kein lokaler Treffer</span>
                            @endif
                        </td>

                        <td style="{{ $statusColor }};font-size:13px">{{ $statusLabel }}</td>

                        <td>
                            <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center">
                                @if(!in_array($status, ['confirmed', 'ignored']))
                                    @if($candidate)
                                        <form method="POST" action="{{ route('admin.reconcile.employees.confirm') }}">
                                            @csrf
                                            <input type="hidden" name="ninox_id"    value="{{ $p['ninox_id'] }}">
                                            <input type="hidden" name="employee_id" value="{{ $candidate->id }}">
                                            <button type="submit" class="btn btn-sm btn-primary">Bestätigen</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.reconcile.employees.create-from') }}">
                                        @csrf
                                        <input type="hidden" name="ninox_id" value="{{ $p['ninox_id'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline">Neu anlegen</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.reconcile.employees.ignore') }}">
                                        @csrf
                                        <input type="hidden" name="ninox_id" value="{{ $p['ninox_id'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline"
                                                style="color:var(--c-danger);border-color:var(--c-danger)">
                                            Ignorieren
                                        </button>
                                    </form>
                                @elseif($status === 'auto' && $candidate)
                                    <form method="POST" action="{{ route('admin.reconcile.employees.confirm') }}">
                                        @csrf
                                        <input type="hidden" name="ninox_id"    value="{{ $p['ninox_id'] }}">
                                        <input type="hidden" name="employee_id" value="{{ $candidate->id }}">
                                        <button type="submit" class="btn btn-sm btn-success">✓ Bestätigen</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.reconcile.employees.ignore') }}">
                                        @csrf
                                        <input type="hidden" name="ninox_id" value="{{ $p['ninox_id'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline"
                                                style="color:var(--c-danger);border-color:var(--c-danger)">
                                            Ablehnen
                                        </button>
                                    </form>
                                @else
                                    <span style="color:var(--c-muted);font-size:12px">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
