@extends('admin.layout')

@section('title', 'Lieferanten-Abgleich')

@section('content')

@if(session('success'))
    <div style="margin-bottom:16px;padding:12px 16px;background:#d1fae5;border:1px solid #10b981;border-radius:6px;color:#065f46">
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div style="margin-bottom:16px;padding:12px 16px;background:#fee2e2;border:1px solid #ef4444;border-radius:6px;color:#991b1b">
        {{ session('error') }}
    </div>
@endif

@php
    $sortLink = fn(string $col) => route('admin.reconcile.suppliers', [
        'search' => $search, 'sort' => $col,
        'dir' => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
    ]);
    $sortIcon = fn(string $col) => $sort === $col ? ($dir === 'asc' ? ' ↑' : ' ↓') : ' ↕';
    $thLink   = 'color:inherit;text-decoration:none;white-space:nowrap';
@endphp

<form method="GET" action="{{ route('admin.reconcile.suppliers') }}" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="dir"  value="{{ $dir }}">
    <input type="text" name="search" value="{{ $search }}"
           placeholder="Suche nach Name, E-Mail …"
           style="flex:1;min-width:220px;padding:7px 12px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
    <button type="submit" class="btn btn-sm btn-primary">Suchen</button>
    @if($search !== '')
        <a href="{{ route('admin.reconcile.suppliers', ['sort'=>$sort,'dir'=>$dir]) }}"
           class="btn btn-sm btn-outline">× Zurücksetzen</a>
    @endif
</form>

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Lieferanten-Abgleich: Ninox ({{ count($proposals) }} Datensätze)</span>
    </div>

    @if(empty($proposals))
        <div style="padding:32px;text-align:center;color:var(--c-muted)">
            @if($search !== '')
                Keine Ergebnisse für „{{ $search }}".
            @else
                Keine Lieferanten in Ninox gefunden.
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ninox-ID</th>
                        <th><a href="{{ $sortLink('name') }}" style="{{ $thLink }}">Name{{ $sortIcon('name') }}</a></th>
                        <th><a href="{{ $sortLink('email') }}" style="{{ $thLink }}">E-Mail{{ $sortIcon('email') }}</a></th>
                        <th><a href="{{ $sortLink('confidence') }}" style="{{ $thLink }}">Konfidenz{{ $sortIcon('confidence') }}</a></th>
                        <th>Lokaler Lieferant</th>
                        <th>Status</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($proposals as $p)
                    @php
                        $d        = $p['source_data'];
                        $name     = $d['name'] ?? '—';
                        $email    = $d['kontakt_e_mail'] ?? '—';
                        $match    = $p['match'];
                        $existing = $p['existing_match'];
                        $conf     = $p['confidence'];
                        $hasDiff  = count($p['diff']) > 0;

                        if ($existing?->status === 'ignored') {
                            $statusLabel = '—';
                            $statusStyle = 'color:var(--c-muted)';
                        } elseif ($existing?->status === 'confirmed') {
                            $statusLabel = $hasDiff ? '⚠ Diff' : '✓ OK';
                            $statusStyle = $hasDiff ? 'color:#f59e0b;font-weight:600' : 'color:#10b981;font-weight:600';
                        } elseif ($match) {
                            $statusLabel = '? Vorschlag';
                            $statusStyle = 'color:#6366f1';
                        } else {
                            $statusLabel = '— kein Match';
                            $statusStyle = 'color:var(--c-muted)';
                        }
                    @endphp
                    <tr>
                        <td style="font-family:monospace;font-size:12px">{{ $p['source_id'] }}</td>
                        <td>{{ $name }}</td>
                        <td style="font-size:13px;color:var(--c-muted)">{{ $email }}</td>

                        <td>
                            @if($conf > 0)
                                <div style="display:flex;align-items:center;gap:6px">
                                    <div style="width:60px;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                                        <div style="width:{{ $conf }}%;height:100%;background:{{ $conf >= 90 ? '#10b981' : ($conf >= 70 ? '#f59e0b' : '#ef4444') }};border-radius:3px"></div>
                                    </div>
                                    <span style="font-size:12px">{{ $conf }}%</span>
                                </div>
                                <div style="font-size:11px;color:var(--c-muted)">{{ $p['match_method'] }}</div>
                            @else
                                <span style="color:var(--c-muted)">—</span>
                            @endif
                        </td>

                        <td>
                            @if($existing?->status === 'confirmed' && $match)
                                <a href="{{ route('admin.suppliers.edit', $match) }}" style="font-weight:600">
                                    {{ $match->name }}
                                </a>
                                @if($hasDiff)
                                    <div style="font-size:11px;color:#f59e0b;margin-top:4px">
                                        Abweichungen: {{ implode(', ', array_keys($p['diff'])) }}
                                    </div>
                                @endif
                            @elseif($match)
                                {{ $match->name }}
                            @else
                                <span style="color:var(--c-muted)">—</span>
                            @endif
                        </td>

                        <td style="{{ $statusStyle }}">{{ $statusLabel }}</td>

                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                @if($existing?->status !== 'confirmed' && $existing?->status !== 'ignored')
                                    @if($match)
                                        <form method="POST" action="{{ route('admin.reconcile.suppliers.confirm') }}">
                                            @csrf
                                            <input type="hidden" name="source_id" value="{{ $p['source_id'] }}">
                                            <input type="hidden" name="supplier_id" value="{{ $match->id }}">
                                            <button type="submit" class="btn btn-sm btn-primary">Bestätigen</button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('admin.reconcile.suppliers.create-from') }}">
                                        @csrf
                                        <input type="hidden" name="source_id" value="{{ $p['source_id'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline">Neu anlegen</button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.reconcile.suppliers.ignore') }}">
                                        @csrf
                                        <input type="hidden" name="source_id" value="{{ $p['source_id'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline" style="color:var(--c-danger);border-color:var(--c-danger)">
                                            Ablehnen
                                        </button>
                                    </form>
                                @elseif($existing?->status === 'confirmed')
                                    @if($match)
                                        <a href="{{ route('admin.suppliers.edit', $match) }}"
                                           class="btn btn-sm btn-outline">Details</a>
                                    @endif
                                @elseif($existing?->status === 'ignored')
                                    <span style="font-size:12px;color:var(--c-muted)">abgelehnt</span>
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
