@extends('admin.layout')

@section('title', 'Kunden-Abgleich')

@section('actions')
    <a href="{{ route('admin.reconcile.customers', ['source' => 'ninox', 'filter' => $filter]) }}"
       class="btn btn-sm {{ $source === 'ninox' ? 'btn-primary' : 'btn-outline' }}">
        Ninox ({{ $source === 'ninox' ? $stats['total'] : '…' }})
    </a>
    <a href="{{ route('admin.reconcile.customers', ['source' => 'wawi', 'filter' => $filter]) }}"
       class="btn btn-sm {{ $source === 'wawi' ? 'btn-primary' : 'btn-outline' }}">
        JTL-WaWi ({{ $source === 'wawi' ? $stats['total'] : '…' }})
    </a>
    <a href="{{ route('admin.reconcile.customers', ['source' => 'lexoffice', 'filter' => $filter]) }}"
       class="btn btn-sm {{ $source === 'lexoffice' ? 'btn-primary' : 'btn-outline' }}">
        Lexoffice ({{ $source === 'lexoffice' ? $stats['total'] : '…' }})
    </a>
@endsection

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

{{-- ── Stats Dashboard ── --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px">
    @foreach([
        ['label' => 'Gesamt', 'value' => $stats['total'], 'color' => 'var(--c-text)'],
        ['label' => 'Nicht verknüpft', 'value' => $stats['unmatched'], 'color' => '#ef4444'],
        ['label' => 'Auto (unbestätigt)', 'value' => $stats['auto'], 'color' => '#f59e0b'],
        ['label' => 'Bestätigt', 'value' => $stats['confirmed'], 'color' => '#10b981'],
        ['label' => 'Ignoriert', 'value' => $stats['ignored'], 'color' => 'var(--c-muted)'],
    ] as $stat)
        <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px;padding:16px;text-align:center">
            <div style="font-size:28px;font-weight:700;color:{{ $stat['color'] }}">{{ number_format($stat['value']) }}</div>
            <div style="font-size:12px;color:var(--c-muted);margin-top:4px">{{ $stat['label'] }}</div>
        </div>
    @endforeach
</div>

{{-- ── Datensync ── --}}
<div class="card" style="margin-bottom:20px;border-color:#0ea5e9">
    <div style="padding:16px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="flex:1">
            <div style="font-weight:600;margin-bottom:4px">Kundendaten synchronisieren</div>
            <div style="font-size:13px;color:var(--c-muted)">
                Überträgt Name, E-Mail und Telefon aus den verknüpften Import-Tabellen (Ninox, WaWi, Lexoffice)
                in die Kunden-Tabelle. <strong>Neuester Wert gewinnt</strong> pro Feld.
                Betrifft alle Kunden mit mindestens einer aktiven Quellverknüpfung.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.reconcile.customers.sync-all') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary" style="background:#0ea5e9;border-color:#0ea5e9"
                    onclick="return confirm('Alle verknüpften Kundendaten jetzt synchronisieren?')">
                Alle synchronisieren
            </button>
        </form>
    </div>
</div>

{{-- ── Auto-Match ── --}}
@if($stats['unmatched'] > 0)
<div class="card" style="margin-bottom:20px;border-color:#6366f1">
    <div style="padding:16px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="flex:1">
            <div style="font-weight:600;margin-bottom:4px">Auto-Abgleich starten</div>
            <div style="font-size:13px;color:var(--c-muted)">
                Verknüpft alle noch nicht zugeordneten Datensätze automatisch, wenn die Konfidenz
                den Mindestwert erreicht. Kundennummer- und E-Mail-Treffer sind immer ≥ 90 %.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.reconcile.customers.auto-match') }}">
            @csrf
            <input type="hidden" name="source" value="{{ $source }}">
            <div style="display:flex;align-items:center;gap:8px">
                <label style="font-size:13px;white-space:nowrap">Mindestkonfidenz:</label>
                <select name="min_confidence" style="padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;font-size:13px">
                    <option value="100">100 % (nur exakt)</option>
                    <option value="95">95 %</option>
                    <option value="90" selected>90 % (empfohlen)</option>
                    <option value="80">80 %</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm" style="background:#6366f1;border-color:#6366f1">
                    Auto-Abgleich starten
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ── Sammelbestätigung ── --}}
@if($stats['auto'] > 0)
<div class="card" style="margin-bottom:20px;border-color:#16a34a">
    <div style="padding:16px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="flex:1">
            <div style="font-weight:600;margin-bottom:4px">Auto-Matches bestätigen</div>
            <div style="font-size:13px;color:var(--c-muted)">
                Bestätigt alle automatisch verknüpften Datensätze, deren aktuelle Konfidenz
                den gewählten Schwellenwert erreicht oder überschreitet.
                <strong>{{ $stats['auto'] }} Auto-Match{{ $stats['auto'] === 1 ? '' : 'es' }}</strong> warten auf Bestätigung.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.reconcile.customers.confirm-all') }}">
            @csrf
            <input type="hidden" name="source" value="{{ $source }}">
            <div style="display:flex;align-items:center;gap:8px">
                <label style="font-size:13px;white-space:nowrap">Mindestkonfidenz:</label>
                <select name="min_confidence" style="padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;font-size:13px">
                    <option value="100">100 % (nur exakt)</option>
                    <option value="95" selected>95 % (Standard)</option>
                    <option value="90">90 %</option>
                    <option value="80">80 %</option>
                    <option value="70">70 %</option>
                </select>
                <button type="submit" class="btn btn-success btn-sm">
                    ✓ Alle bestätigen
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ── Filter-Tabs ── --}}
<div style="display:flex;gap:4px;margin-bottom:16px">
    @foreach([
        ['key' => 'unmatched', 'label' => 'Nicht verknüpft', 'count' => $stats['unmatched']],
        ['key' => 'auto',      'label' => 'Auto (prüfen)',    'count' => $stats['auto']],
        ['key' => 'confirmed', 'label' => 'Bestätigt',        'count' => $stats['confirmed']],
        ['key' => 'ignored',   'label' => 'Ignoriert',        'count' => $stats['ignored']],
        ['key' => 'all',       'label' => 'Alle (max. 200)',  'count' => $stats['total']],
    ] as $tab)
        <a href="{{ route('admin.reconcile.customers', ['source' => $source, 'filter' => $tab['key']]) }}"
           style="padding:6px 12px;border-radius:4px;font-size:13px;text-decoration:none;border:1px solid var(--c-border);
                  {{ $filter === $tab['key'] ? 'background:var(--c-primary,#2563eb);color:#fff;border-color:transparent' : 'background:var(--c-surface);color:var(--c-text)' }}">
            {{ $tab['label'] }}
            @if($tab['count'] > 0)
                <span style="display:inline-block;min-width:18px;height:18px;line-height:18px;text-align:center;
                             border-radius:9px;font-size:11px;margin-left:4px;
                             {{ $filter === $tab['key'] ? 'background:rgba(255,255,255,0.3)' : 'background:var(--c-bg)' }}">
                    {{ number_format($tab['count']) }}
                </span>
            @endif
        </a>
    @endforeach
</div>

{{-- ── Suche + Tabelle ── --}}
@php
    $sortLink = fn(string $col) => route('admin.reconcile.customers', [
        'source' => $source, 'filter' => $filter, 'search' => $search,
        'sort' => $col, 'dir' => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
    ]);
    $sortIcon = fn(string $col) => $sort === $col ? ($dir === 'asc' ? ' ↑' : ' ↓') : ' ↕';
    $thLink   = 'color:inherit;text-decoration:none;white-space:nowrap';
@endphp

<form method="GET" action="{{ route('admin.reconcile.customers') }}" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap">
    <input type="hidden" name="source" value="{{ $source }}">
    <input type="hidden" name="filter" value="{{ $filter }}">
    <input type="hidden" name="sort"   value="{{ $sort }}">
    <input type="hidden" name="dir"    value="{{ $dir }}">
    <input type="text" name="search" value="{{ $search }}"
           placeholder="Suche nach Name, E-Mail, K-Nr. …"
           style="flex:1;min-width:220px;padding:7px 12px;border:1px solid var(--c-border);border-radius:6px;font-size:13px;background:var(--c-surface);color:var(--c-text)">
    <button type="submit" class="btn btn-sm btn-primary">Suchen</button>
    @if($search !== '')
        <a href="{{ route('admin.reconcile.customers', ['source'=>$source,'filter'=>$filter,'sort'=>$sort,'dir'=>$dir]) }}"
           class="btn btn-sm btn-outline">× Zurücksetzen</a>
    @endif
</form>

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>{{ ucfirst($source) }}-Kunden — {{ match($filter) {
            'unmatched' => 'Noch nicht verknüpft',
            'auto'      => 'Automatisch verknüpft (zur Bestätigung)',
            'confirmed' => 'Manuell bestätigt',
            'ignored'   => 'Ignoriert',
            default     => 'Alle (max. 200)'
        } }} ({{ count($proposals) }})</span>
        @if($truncated ?? false)
            <span style="font-size:12px;color:var(--c-muted)">Anzeige auf 200 begrenzt</span>
        @endif
    </div>

    @if(empty($proposals))
        <div style="padding:40px;text-align:center;color:var(--c-muted)">
            @if($filter === 'unmatched')
                Alle Datensätze sind bereits verknüpft oder abgelehnt. 🎉
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
                        <th style="width:110px">ID / K-Nr.</th>
                        <th><a href="{{ $sortLink('name') }}" style="{{ $thLink }}">Name / Firma{{ $sortIcon('name') }}</a></th>
                        <th><a href="{{ $sortLink('email') }}" style="{{ $thLink }}">E-Mail{{ $sortIcon('email') }}</a></th>
                        <th style="width:120px"><a href="{{ $sortLink('confidence') }}" style="{{ $thLink }}">Konfidenz{{ $sortIcon('confidence') }}</a></th>
                        <th>Lokaler Kunde (Lexoffice)</th>
                        <th style="width:110px">Status</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($proposals as $p)
                    @php
                        $d     = $p['source_data'];
                        if ($source === 'ninox') {
                            $name  = trim(($d['firmenname'] ?? '') ?: (($d['vorname'] ?? '') . ' ' . ($d['nachname'] ?? '')));
                            $email = $d['e_mail'] ?? '';
                            $kNr   = $d['kundennummer'] ?? '';
                        } elseif ($source === 'wawi') {
                            $name  = trim(($d['cFirma'] ?? '') ?: (($d['cVorname'] ?? '') . ' ' . ($d['cNachname'] ?? '')));
                            $email = $d['cMail'] ?? '';
                            $kNr   = $d['cKundenNr'] ?? '';
                        } else {
                            // lexoffice
                            $rawCompany = $d['company_name'] ?? '';
                            $kNr   = app(\App\Services\Reconcile\CustomerReconcileService::class)->extractLexofficeCustomerNumber($rawCompany) ?? '';
                            $name  = $kNr ? trim(preg_replace('/\s+(K\d{4,6}|\d{5,6})\s*$/i', '', $rawCompany)) : $rawCompany;
                            $email = $d['primary_email'] ?? '';
                        }

                        $match       = $p['match'];
                        $existing    = $p['existing_match'];
                        $conf        = $p['confidence'];
                        $hasDiff     = count($p['diff']) > 0;
                        $takenBy     = $p['taken_by_source_id'] ?? null;

                        $statusLabel = match(true) {
                            $existing?->status === 'ignored'   => '— abgelehnt',
                            $existing?->status === 'confirmed' => $hasDiff ? '⚠ Diff' : '✓ OK',
                            $existing?->status === 'auto'      => $hasDiff ? '⚠ Auto+Diff' : '~ Auto',
                            (bool)$takenBy                     => '⚠ Duplikat',
                            (bool)$match                       => '? Vorschlag',
                            default                            => '— kein Match',
                        };
                        $statusColor = match(true) {
                            $existing?->status === 'ignored'                   => 'color:var(--c-muted)',
                            $existing?->status === 'confirmed' && !$hasDiff    => 'color:#10b981;font-weight:600',
                            $existing?->status === 'confirmed' && $hasDiff     => 'color:#f59e0b;font-weight:600',
                            $existing?->status === 'auto' && !$hasDiff        => 'color:#6366f1',
                            $existing?->status === 'auto' && $hasDiff         => 'color:#f59e0b',
                            (bool)$takenBy                                     => 'color:#f97316;font-weight:600',
                            (bool)$match                                        => 'color:#6366f1',
                            default                                             => 'color:var(--c-muted)',
                        };
                    @endphp
                    <tr>
                        <td style="font-family:monospace;font-size:12px">
                            {{ $p['source_id'] }}
                            @if($kNr)
                                <br><span style="color:var(--c-muted)">{{ $kNr }}</span>
                            @endif
                        </td>
                        <td>{{ $name ?: '—' }}</td>
                        <td style="font-size:12px;color:var(--c-muted)">{{ $email ?: '—' }}</td>

                        <td>
                            @if($conf > 0)
                                <div style="display:flex;align-items:center;gap:5px">
                                    <div style="width:50px;height:5px;background:var(--c-border);border-radius:3px;overflow:hidden;flex-shrink:0">
                                        <div style="width:{{ $conf }}%;height:100%;border-radius:3px;
                                                    background:{{ $conf >= 95 ? '#10b981' : ($conf >= 80 ? '#f59e0b' : '#ef4444') }}"></div>
                                    </div>
                                    <span style="font-size:12px">{{ $conf }}%</span>
                                </div>
                                @php $methodColor = match($p['match_method']) {
                                    'customer-number', 'k-nummer', 'trailing-kundennummer' => '#10b981',
                                    'email+name'   => '#3b82f6',
                                    'email+phone'  => '#6366f1',
                                    'phone'        => '#f59e0b',
                                    'fuzzy_name'   => '#94a3b8',
                                    'email-only'   => '#ef4444',
                                    default        => 'var(--c-muted)',
                                }; @endphp
                                <div style="font-size:11px;color:{{ $methodColor }};font-weight:500">{{ $p['match_method'] }}</div>
                            @else
                                <span style="color:var(--c-muted);font-size:12px">—</span>
                            @endif
                        </td>

                        <td>
                            @if($match)
                                <a href="{{ route('admin.customers.show', $match) }}"
                                   style="font-weight:600;color:var(--c-text);text-decoration:underline;text-decoration-color:var(--c-border)">
                                    {{ $match->customer_number }}
                                    {{ $match->company_name ?: trim($match->first_name . ' ' . $match->last_name) }}
                                </a>
                                @if($match->lexoffice_contact_id)
                                    <span style="font-size:10px;color:var(--c-muted);margin-left:4px">Lexoffice ✓</span>
                                @endif
                                @if($hasDiff)
                                    <div style="font-size:11px;color:#f59e0b;margin-top:3px">
                                        @foreach($p['diff'] as $field => $vals)
                                            <div>{{ $field }}: <em>lokal="{{ $vals['local'] }}"</em> ≠ <em>{{ $source }}="{{ $vals['source'] }}"</em></div>
                                        @endforeach
                                    </div>
                                @endif
                            @elseif($takenBy)
                                <span style="color:#f97316;font-size:12px">
                                    ⚠ Kunde bereits verknüpft mit {{ ucfirst($source) }}-ID #{{ $takenBy }}
                                </span>
                            @else
                                <span style="color:var(--c-muted)">—</span>
                            @endif
                        </td>

                        <td style="{{ $statusColor }};font-size:13px">{{ $statusLabel }}</td>

                        <td>
                            <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center">
                                @if(in_array($existing?->status, ['confirmed', 'ignored']) === false)
                                    @if($match)
                                        <form method="POST" action="{{ route('admin.reconcile.customers.confirm') }}">
                                            @csrf
                                            <input type="hidden" name="source" value="{{ $source }}">
                                            <input type="hidden" name="source_id" value="{{ $p['source_id'] }}">
                                            <input type="hidden" name="customer_id" value="{{ $match->id }}">
                                            <button type="submit" class="btn btn-sm btn-primary">Bestätigen</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.reconcile.customers.create-from') }}">
                                        @csrf
                                        <input type="hidden" name="source" value="{{ $source }}">
                                        <input type="hidden" name="source_id" value="{{ $p['source_id'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline">Neu anlegen</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.reconcile.customers.ignore') }}">
                                        @csrf
                                        <input type="hidden" name="source" value="{{ $source }}">
                                        <input type="hidden" name="source_id" value="{{ $p['source_id'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline" style="color:var(--c-danger);border-color:var(--c-danger)">Ablehnen</button>
                                    </form>
                                @elseif($existing?->status === 'auto')
                                    @if($match)
                                        <form method="POST" action="{{ route('admin.reconcile.customers.confirm') }}">
                                            @csrf
                                            <input type="hidden" name="source" value="{{ $source }}">
                                            <input type="hidden" name="source_id" value="{{ $p['source_id'] }}">
                                            <input type="hidden" name="customer_id" value="{{ $match->id }}">
                                            <button type="submit" class="btn btn-sm btn-primary">Bestätigen</button>
                                        </form>
                                    @endif
                                @else
                                    @if($match)
                                        <a href="{{ route('admin.customers.show', $match) }}"
                                           class="btn btn-sm btn-outline" style="color:var(--c-text)">Profil</a>
                                    @endif
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
