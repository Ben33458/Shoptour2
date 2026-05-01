@extends('admin.layout')

@section('title', 'Produkt-Abgleich')

@section('content')

@if(session('success'))
    <div style="margin-bottom:16px;padding:12px 16px;background:color-mix(in srgb,var(--c-success) 15%,var(--c-surface));border:1px solid var(--c-success);border-radius:6px;color:var(--c-success)">
        {{ session('success') }}
    </div>
@endif
@if(session('warning'))
    <div style="margin-bottom:16px;padding:12px 16px;background:color-mix(in srgb,var(--c-warning) 15%,var(--c-surface));border:1px solid var(--c-warning);border-radius:6px;color:var(--c-warning)">
        {{ session('warning') }}
        @if(session('skipped_details'))
            <div style="margin-top:8px;font-size:12px;border-top:1px solid #fcd34d;padding-top:8px">
                <strong>Übersprungen — Artikelnummer-Konflikt:</strong>
                <ul style="margin:4px 0 0 16px;list-style:disc">
                    @foreach(session('skipped_details') as $skip)
                        <li>{{ $skip['name'] }} (Art.Nr. {{ $skip['artnr'] }}) — {{ $skip['reason'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
@if(session('error'))
    <div style="margin-bottom:16px;padding:12px 16px;background:color-mix(in srgb,var(--c-danger) 15%,var(--c-surface));border:1px solid var(--c-danger);border-radius:6px;color:var(--c-danger)">
        {{ session('error') }}
        @if(session('skipped_details'))
            <div style="margin-top:8px;font-size:12px;border-top:1px solid #fca5a5;padding-top:8px">
                <strong>Übersprungen — Artikelnummer-Konflikt:</strong>
                <ul style="margin:4px 0 0 16px;list-style:disc">
                    @foreach(session('skipped_details') as $skip)
                        <li>{{ $skip['name'] }} (Art.Nr. {{ $skip['artnr'] }}) — {{ $skip['reason'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif

{{-- ── Stats Dashboard ── --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:12px">
    <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px;padding:16px;text-align:center">
        <div style="font-size:11px;color:var(--c-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Quellen</div>
        <div style="display:flex;justify-content:center;gap:24px;">
            <div>
                <div style="font-size:22px;font-weight:700;color:var(--c-text)">{{ number_format($stats['total']) }}</div>
                <div style="font-size:11px;color:var(--c-muted)">Ninox-Artikel</div>
            </div>
            <div style="border-left:1px solid var(--c-border);"></div>
            <div>
                <div style="font-size:22px;font-weight:700;color:var(--c-text)">{{ number_format($stats['wawi_total']) }}</div>
                <div style="font-size:11px;color:var(--c-muted)">WaWi-Artikel</div>
            </div>
        </div>
    </div>
    <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px;padding:16px;text-align:center">
        <div style="font-size:11px;color:var(--c-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Ninox-Abgleich</div>
        <div style="display:flex;justify-content:center;gap:20px;">
            <div>
                <div style="font-size:22px;font-weight:700;color:#ef4444">{{ number_format($stats['unmatched']) }}</div>
                <div style="font-size:11px;color:var(--c-muted)">offen</div>
            </div>
            <div>
                <div style="font-size:22px;font-weight:700;color:#f59e0b">{{ number_format($stats['auto']) }}</div>
                <div style="font-size:11px;color:var(--c-muted)">auto</div>
            </div>
            <div>
                <div style="font-size:22px;font-weight:700;color:#10b981">{{ number_format($stats['confirmed']) }}</div>
                <div style="font-size:11px;color:var(--c-muted)">bestätigt</div>
            </div>
        </div>
    </div>
    <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px;padding:16px;text-align:center">
        <div style="font-size:11px;color:var(--c-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Sonstiges</div>
        <div style="display:flex;justify-content:center;gap:24px;">
            <div>
                <div style="font-size:22px;font-weight:700;color:var(--c-muted)">{{ number_format($stats['ignored']) }}</div>
                <div style="font-size:11px;color:var(--c-muted)">ignoriert</div>
            </div>
            <div style="border-left:1px solid var(--c-border);"></div>
            <div>
                <div style="font-size:22px;font-weight:700;color:#6366f1">{{ number_format($stats['confirmed_pending'] ?? 0) }}</div>
                <div style="font-size:11px;color:var(--c-muted)">bestätigt, nicht importiert</div>
            </div>
        </div>
    </div>
</div>

{{-- ── Info + Regeln-Analyse ── --}}
<div style="margin-bottom:16px;padding:12px 16px;background:color-mix(in srgb,var(--c-primary) 10%,var(--c-surface));border:1px solid color-mix(in srgb,var(--c-primary) 40%,transparent);border-radius:6px;font-size:13px;color:var(--c-primary);display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="flex:1">
        <strong>Matching-Priorität:</strong>
        1. Artikelnummer (artnrkolabrikasten = WaWi cArtNr, 100 %) →
        2. EAN (exakt, 100 %) →
        3. Artikelname Fuzzy (≥ 75 %)
    </div>
    <button type="button" onclick="openRulesModal()"
            class="btn btn-sm btn-outline"
            style="white-space:nowrap;border-color:var(--c-primary);color:var(--c-primary)">
        Neue Regeln analysieren
    </button>
</div>

{{-- ── Auto-Match bestätigen ── --}}
@if($stats['auto'] > 0)
<div class="card" style="margin-bottom:20px;border-color:#16a34a">
    <div style="padding:16px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="flex:1">
            <div style="font-weight:600;margin-bottom:4px">Auto-Matches bestätigen</div>
            <div style="font-size:13px;color:var(--c-muted)">
                Bestätigt alle automatisch verknüpften Produkte, deren aktuelle Konfidenz den
                gewählten Schwellenwert erreicht oder überschreitet.
                <strong>{{ $stats['auto'] }} Auto-Match{{ $stats['auto'] === 1 ? '' : 'es' }}</strong> warten auf Bestätigung.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.reconcile.products.confirm-all-100') }}">
            @csrf
            <div style="display:flex;align-items:center;gap:8px">
                <label style="font-size:13px;white-space:nowrap">Mindestkonfidenz:</label>
                <select name="min_confidence" style="padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;font-size:13px">
                    <option value="100">100 % (nur exakt)</option>
                    <option value="96" selected>96 % (Standard)</option>
                    <option value="90">90 %</option>
                    <option value="80">80 %</option>
                    <option value="75">75 %</option>
                </select>
                <button type="submit" class="btn btn-success btn-sm">
                    ✓ Alle bestätigen
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@if($stats['confirmed_pending'] > 0)
<div class="card" style="margin-bottom:20px;border-color:#f97316">
    <div style="padding:16px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="flex:1">
            <div style="font-weight:600;margin-bottom:4px">Bestätigte Produkte importieren</div>
            <div style="font-size:13px;color:var(--c-muted)">
                {{ $stats['confirmed_pending'] }} bestätigte Match{{ $stats['confirmed_pending'] === 1 ? '' : 'es' }} werden als lokale Produkte in den Katalog übernommen.
                Bereits importierte Matches (local_id gesetzt) werden übersprungen.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.reconcile.products.import-confirmed') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary" style="background:#f97316;border-color:#f97316;white-space:nowrap">
                {{ $stats['confirmed_pending'] }} Produkte importieren
            </button>
        </form>
    </div>
</div>
@endif

@if($stats['unmatched'] > 0)
<div class="card" style="margin-bottom:20px;border-color:#6366f1">
    <div style="padding:16px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="flex:1">
            <div style="font-weight:600;margin-bottom:4px">Auto-Abgleich starten</div>
            <div style="font-size:13px;color:var(--c-muted)">
                Verknüpft Ninox-Artikel automatisch mit dem besten WaWi-Treffer,
                wenn die Konfidenz den Mindestwert erreicht.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.reconcile.products.auto-match') }}">
            @csrf
            <div style="display:flex;align-items:center;gap:8px">
                <label style="font-size:13px;white-space:nowrap">Mindestkonfidenz:</label>
                <select name="min_confidence" style="padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;font-size:13px">
                    <option value="100">100 % (nur EAN-exakt)</option>
                    <option value="95" selected>95 % (empfohlen)</option>
                    <option value="90">90 %</option>
                    <option value="80">80 %</option>
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

{{-- ── Suchfeld ── --}}
<form method="GET" action="{{ route('admin.reconcile.products') }}" style="margin-bottom:12px;display:flex;gap:8px">
    <input type="hidden" name="filter" value="{{ $filter }}">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="dir" value="{{ $dir }}">
    <input type="text" name="search" value="{{ $search }}" placeholder="Artikelname oder Art.-Nr. suchen…"
           style="flex:1;padding:6px 10px;border:1px solid var(--c-border);border-radius:4px;font-size:13px">
    <button type="submit" class="btn btn-sm btn-outline">Suchen</button>
    @if($search !== '')
        <a href="{{ route('admin.reconcile.products', ['filter' => $filter, 'sort' => $sort, 'dir' => $dir]) }}"
           class="btn btn-sm btn-outline">✕ Löschen</a>
    @endif
</form>

{{-- ── Filter-Tabs ── --}}
<div style="display:flex;gap:4px;margin-bottom:16px">
    @foreach([
        ['key' => 'unmatched', 'label' => 'Nicht verknüpft', 'count' => $stats['unmatched']],
        ['key' => 'auto',      'label' => 'Auto (prüfen)',    'count' => $stats['auto']],
        ['key' => 'confirmed', 'label' => 'Bestätigt',        'count' => $stats['confirmed']],
        ['key' => 'ignored',   'label' => 'Ignoriert',        'count' => $stats['ignored']],
        ['key' => 'all',       'label' => 'Alle (max. 200)',  'count' => $stats['total']],
    ] as $tab)
        <a href="{{ route('admin.reconcile.products', ['filter' => $tab['key'], 'search' => $search, 'sort' => $sort, 'dir' => $dir]) }}"
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

{{-- ── Bulk-Aktionsleiste ── --}}
<div id="bulk-bar" style="display:none;align-items:center;gap:12px;padding:10px 14px;margin-bottom:12px;
                           background:color-mix(in srgb,var(--c-primary) 10%,var(--c-surface));border:1px solid color-mix(in srgb,var(--c-primary) 40%,transparent);border-radius:6px;font-size:13px;color:var(--c-primary)">
    <span><strong id="bulk-count">0</strong> ausgewählt —</span>
    <form id="bulk-confirm-form" method="POST" action="{{ route('admin.reconcile.products.bulk-confirm') }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-primary">Bestätigen</button>
    </form>
    <form id="bulk-ignore-form" method="POST" action="{{ route('admin.reconcile.products.bulk-ignore') }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline" style="color:var(--c-danger);border-color:var(--c-danger)">Ablehnen</button>
    </form>
</div>

{{-- ── Tabelle ── --}}
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Ninox-Artikel — {{ match($filter) {
            'unmatched' => 'Noch nicht verknüpft',
            'auto'      => 'Automatisch verknüpft (zur Bestätigung)',
            'confirmed' => 'Bestätigt',
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
                Alle Ninox-Artikel wurden abgeglichen oder abgelehnt. 🎉
            @else
                Keine Datensätze in dieser Kategorie.
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px">
                            <input type="checkbox" id="select-all" title="Alle auswählen">
                        </th>
                        <th style="width:80px">Ninox-ID</th>
                        @php
                            $sortLink = fn(string $col) =>
                                route('admin.reconcile.products', [
                                    'filter' => $filter,
                                    'search' => $search,
                                    'sort'   => $col,
                                    'dir'    => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
                                ]);
                            $sortIcon = fn(string $col) =>
                                $sort === $col ? ($dir === 'asc' ? ' ↑' : ' ↓') : ' ↕';
                        @endphp
                        <th style="width:90px">
                            <a href="{{ $sortLink('artnummer') }}" style="color:inherit;text-decoration:none">
                                Art.-Nr.{{ $sortIcon('artnummer') }}
                            </a>
                        </th>
                        <th>
                            <a href="{{ $sortLink('name') }}" style="color:inherit;text-decoration:none">
                                Ninox-Artikel{{ $sortIcon('name') }}
                            </a>
                        </th>
                        <th style="width:100px">
                            <a href="{{ $sortLink('confidence') }}" style="color:inherit;text-decoration:none">
                                Konfidenz{{ $sortIcon('confidence') }}
                            </a>
                        </th>
                        <th>WaWi-Artikel</th>
                        <th style="width:90px">Status</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($proposals as $p)
                    @php
                        $d         = $p['source_data'];
                        $wawiMatch = $p['match'];
                        $conf      = $p['confidence'];
                        $existing  = $p['existing_match'];

                        $statusLabel = match(true) {
                            $existing?->status === 'ignored'   => '— abgelehnt',
                            $existing?->status === 'confirmed' => '✓ OK',
                            $existing?->status === 'auto'      => '~ Auto',
                            (bool)$wawiMatch                   => '? Vorschlag',
                            default                            => '— kein Match',
                        };
                        $statusColor = match(true) {
                            $existing?->status === 'ignored'   => 'color:var(--c-muted)',
                            $existing?->status === 'confirmed' => 'color:#10b981;font-weight:600',
                            $existing?->status === 'auto'      => 'color:#6366f1',
                            (bool)$wawiMatch                   => 'color:#6366f1',
                            default                            => 'color:var(--c-muted)',
                        };
                        $canAct = !in_array($existing?->status, ['confirmed', 'ignored']);
                    @endphp
                    <tr data-ninox-id="{{ $p['source_id'] }}">
                        <td style="text-align:center">
                            @if($canAct || $existing?->status === 'auto')
                                <input type="checkbox" class="row-check" value="{{ $p['source_id'] }}">
                            @endif
                        </td>
                        <td style="font-family:monospace;font-size:12px">{{ $p['source_id'] }}</td>
                        <td style="font-family:monospace;font-size:12px;color:var(--c-muted)">
                            {{ $d['artnummer'] ?? '—' }}
                        </td>
                        <td>
                            <div style="font-weight:500">{{ $d['artikelname'] ?? '—' }}</div>
                            @if($d['ean'] ?? null)
                                <div style="font-size:11px;color:var(--c-muted)">EAN: {{ $d['ean'] }}</div>
                            @endif
                            @if(($d['vk_brutto_markt'] ?? null) > 0)
                                <div style="font-size:11px;color:var(--c-muted)">
                                    {{ number_format((float)$d['vk_brutto_markt'], 2, ',', '.') }} € brutto
                                </div>
                            @endif
                        </td>

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
                                    'ean'           => '#10b981',
                                    'fuzzy_gebinde' => '#6366f1',
                                    'fuzzy_name'    => '#f59e0b',
                                    'confirmed'     => '#10b981',
                                    default         => 'var(--c-muted)',
                                }; @endphp
                                <div style="font-size:11px;color:{{ $methodColor }};font-weight:500">{{ $p['match_method'] }}</div>
                            @else
                                <span style="color:var(--c-muted);font-size:12px">—</span>
                            @endif
                        </td>

                        <td>
                            @if($wawiMatch)
                                <div style="font-weight:500">{{ $wawiMatch->cName ?? '—' }}</div>
                                <div style="font-family:monospace;font-size:11px;color:var(--c-muted)">
                                    Nr: {{ $wawiMatch->cArtNr ?? '—' }}
                                    @if($wawiMatch->cBarcode ?? null)
                                        · EAN: {{ $wawiMatch->cBarcode }}
                                    @endif
                                </div>
                                @if(($wawiMatch->fVKNetto ?? null) > 0)
                                    <div style="font-size:11px;color:var(--c-muted)">
                                        {{ number_format((float)$wawiMatch->fVKNetto, 2, ',', '.') }} € netto
                                    </div>
                                @endif
                                @if(count($p['existing_match']?->diff_at_match ?? []) > 0)
                                    <div style="font-size:11px;color:#f59e0b;margin-top:3px">
                                        @foreach($p['existing_match']->diff_at_match as $field => $vals)
                                            <div>{{ $field }}: <em>Ninox="{{ $vals['ninox'] }}"</em> ≠ <em>WaWi="{{ $vals['wawi'] }}"</em></div>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <span style="color:var(--c-muted)">— kein WaWi-Treffer</span>
                            @endif
                        </td>

                        <td style="{{ $statusColor }};font-size:13px">{{ $statusLabel }}</td>

                        <td>
                            <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center">
                                @if($existing?->status !== 'confirmed' && $existing?->status !== 'ignored')
                                    @if($wawiMatch)
                                        <form method="POST" action="{{ route('admin.reconcile.products.confirm') }}">
                                            @csrf
                                            <input type="hidden" name="ninox_id" value="{{ $p['source_id'] }}">
                                            <input type="hidden" name="wawi_id" value="{{ $wawiMatch->kArtikel }}">
                                            <button type="submit" class="btn btn-sm btn-primary">Bestätigen</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.reconcile.products.confirm') }}">
                                            @csrf
                                            <input type="hidden" name="ninox_id" value="{{ $p['source_id'] }}">
                                            <button type="submit" class="btn btn-sm btn-outline"
                                                    title="Ninox-Artikel ohne WaWi-Entsprechung bestätigen">Nur Ninox</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.reconcile.products.ignore') }}">
                                        @csrf
                                        <input type="hidden" name="ninox_id" value="{{ $p['source_id'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline" style="color:var(--c-danger);border-color:var(--c-danger)">Ablehnen</button>
                                    </form>
                                @elseif($existing?->status === 'auto')
                                    @if($wawiMatch)
                                        <form method="POST" action="{{ route('admin.reconcile.products.confirm') }}">
                                            @csrf
                                            <input type="hidden" name="ninox_id" value="{{ $p['source_id'] }}">
                                            <input type="hidden" name="wawi_id" value="{{ $wawiMatch->kArtikel }}">
                                            <button type="submit" class="btn btn-sm btn-primary">Bestätigen</button>
                                        </form>
                                    @endif
                                @endif
                                @if(in_array($existing?->status, ['confirmed', 'ignored']))
                                    <form method="POST" action="{{ route('admin.reconcile.products.reset') }}">
                                        @csrf
                                        <input type="hidden" name="ninox_id" value="{{ $p['source_id'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline" style="color:var(--c-muted)"
                                                        title="Verknüpfung löschen und zurücksetzen">✕ Löschen</button>
                                    </form>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline"
                                        onclick="openMatchModal('{{ $p['source_id'] }}', @js($d))"
                                        title="WAWI-Artikel manuell suchen und verknüpfen"
                                        style="color:var(--c-primary);border-color:var(--c-primary)">
                                    Manuell
                                </button>
                                @if($existing?->status === 'ignored')
                                    <button type="button" class="btn btn-sm btn-outline"
                                            style="color:#10b981;border-color:#10b981"
                                            title="{{ $wawiMatch ? 'Bestätigen mit: ' . $wawiMatch->cName : 'Als Ninox-only bestätigen' }}"
                                            onclick="confirmIgnored(this, '{{ $p['source_id'] }}', '{{ $wawiMatch?->kArtikel ?? '' }}')">
                                        ✓ Bestätigen
                                    </button>
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

{{-- ── Regeln-Analyse-Modal ── --}}
<div id="rules-modal" style="display:none;position:fixed;inset:0;z-index:1000;
     background:rgba(0,0,0,.55);align-items:flex-start;justify-content:center;
     padding-top:40px;overflow-y:auto">
    <div style="background:var(--c-surface);border:1px solid var(--c-border);
                border-radius:10px;width:min(800px,95vw);padding:24px;
                position:relative;margin-bottom:40px">

        <button onclick="closeRulesModal()"
                style="position:absolute;top:12px;right:14px;font-size:18px;
                       background:none;border:none;cursor:pointer;color:var(--c-muted)">✕</button>

        <h3 style="margin:0 0 4px;font-size:16px">Matching-Regeln</h3>
        <p style="margin:0 0 16px;font-size:13px;color:var(--c-muted)">
            Analysiert bestätigte Matches, die der Auto-Abgleich nicht finden konnte,
            und schlägt neue Regeln vor. Regeln werden sofort aktiv und beim nächsten
            Auto-Abgleich angewendet.
        </p>

        {{-- Tabs --}}
        <div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid var(--c-border);padding-bottom:0">
            <button type="button" id="tab-btn-suggest" onclick="switchTab('suggest')"
                    style="padding:7px 14px;border:1px solid transparent;border-bottom:none;
                           border-radius:4px 4px 0 0;font-size:13px;cursor:pointer;
                           background:var(--c-primary);color:#fff;margin-bottom:-1px">
                Vorschläge
            </button>
            <button type="button" id="tab-btn-saved" onclick="switchTab('saved')"
                    style="padding:7px 14px;border:1px solid var(--c-border);border-bottom:none;
                           border-radius:4px 4px 0 0;font-size:13px;cursor:pointer;
                           background:var(--c-surface);color:var(--c-text);margin-bottom:-1px">
                Gespeicherte Regeln
            </button>
        </div>

        {{-- Tab: Vorschläge --}}
        <div id="tab-suggest">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
                <label style="font-size:13px">Mindest-Häufigkeit:</label>
                <select id="rules-min-freq" style="padding:4px 8px;border:1px solid var(--c-border);
                        border-radius:4px;font-size:13px">
                    <option value="2" selected>2×</option>
                    <option value="3">3×</option>
                    <option value="5">5×</option>
                </select>
                <button type="button" onclick="loadSuggestions()" class="btn btn-sm btn-primary"
                        style="background:var(--c-primary);border-color:var(--c-primary)">
                    Analysieren
                </button>
            </div>

            <div id="rules-loading" style="display:none;padding:20px;text-align:center;
                 color:var(--c-muted);font-size:13px">Wird analysiert…</div>

            <div id="rules-content" style="display:none">
                <div id="rules-meta" style="font-size:12px;color:var(--c-muted);margin-bottom:16px"></div>

                <div id="rules-synonyms-wrap">
                    <div style="font-weight:600;font-size:13px;margin-bottom:8px;
                                padding-bottom:6px;border-bottom:1px solid var(--c-border)">
                        Synonym-Vorschläge
                        <span style="font-weight:400;color:var(--c-muted);font-size:12px">
                            — je 1 Token-Unterschied zwischen Ninox und WaWi
                        </span>
                    </div>
                    <div id="rules-synonyms"></div>
                </div>

                <div id="rules-noise-ninox-wrap" style="margin-top:20px">
                    <div style="font-weight:600;font-size:13px;margin-bottom:8px;
                                padding-bottom:6px;border-bottom:1px solid var(--c-border)">
                        Ninox-Rauschtokens
                        <span style="font-weight:400;color:var(--c-muted);font-size:12px">
                            — Token in Ninox vorhanden, in WaWi nicht (Kandidat zum Strippen)
                        </span>
                    </div>
                    <div id="rules-noise-ninox"></div>
                </div>

                <div id="rules-noise-wawi-wrap" style="margin-top:20px">
                    <div style="font-weight:600;font-size:13px;margin-bottom:8px;
                                padding-bottom:6px;border-bottom:1px solid var(--c-border)">
                        WaWi-Rauschtokens
                        <span style="font-weight:400;color:var(--c-muted);font-size:12px">
                            — Token in WaWi vorhanden, in Ninox nicht
                        </span>
                    </div>
                    <div id="rules-noise-wawi"></div>
                </div>

                <div id="rules-no-results" style="display:none;padding:20px;text-align:center;
                     color:var(--c-muted);font-size:13px">
                    Keine neuen Regelvorschläge gefunden. Alle Muster sind bereits abgedeckt.
                </div>
            </div>
        </div>

        {{-- Tab: Gespeicherte Regeln --}}
        <div id="tab-saved" style="display:none">
            <div id="saved-loading" style="padding:20px;text-align:center;
                 color:var(--c-muted);font-size:13px">Wird geladen…</div>
            <div id="saved-content" style="display:none">
                <div id="saved-empty" style="display:none;padding:20px;text-align:center;
                     color:var(--c-muted);font-size:13px">
                    Noch keine gespeicherten Regeln.
                </div>
                <div id="saved-synonyms-wrap">
                    <div style="font-weight:600;font-size:13px;margin-bottom:8px;
                                padding-bottom:6px;border-bottom:1px solid var(--c-border)">
                        Synonyme
                    </div>
                    <div id="saved-synonyms"></div>
                </div>
                <div id="saved-noise-wrap" style="margin-top:20px">
                    <div style="font-weight:600;font-size:13px;margin-bottom:8px;
                                padding-bottom:6px;border-bottom:1px solid var(--c-border)">
                        Rauschtokens (Strip)
                    </div>
                    <div id="saved-noise"></div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const RULES_SUGGEST_URL = '{{ route("admin.reconcile.products.suggest-rules") }}';
const RULES_INDEX_URL   = '{{ route("admin.reconcile.products.rules.index") }}';
const RULES_STORE_URL   = '{{ route("admin.reconcile.products.rules.store") }}';
const RULES_DELETE_URL  = '{{ url("admin/reconcile/products/rules") }}';
const CSRF_TOKEN        = '{{ csrf_token() }}';

function openRulesModal() {
    document.getElementById('rules-modal').style.display = 'flex';
    switchTab('suggest');
}
function closeRulesModal() {
    document.getElementById('rules-modal').style.display = 'none';
}

function switchTab(name) {
    ['suggest', 'saved'].forEach(function(t) {
        document.getElementById('tab-' + t).style.display      = t === name ? 'block' : 'none';
        const btn = document.getElementById('tab-btn-' + t);
        if (t === name) {
            btn.style.background   = 'var(--c-primary)';
            btn.style.color        = '#fff';
            btn.style.borderColor  = 'transparent';
        } else {
            btn.style.background   = 'var(--c-surface)';
            btn.style.color        = 'var(--c-text)';
            btn.style.borderColor  = 'var(--c-border)';
        }
    });
    if (name === 'saved') loadSavedRules();
}

// ── Suggestions ─────────────────────────────────────────────────────────────

async function loadSuggestions() {
    const minFreq = document.getElementById('rules-min-freq').value;
    document.getElementById('rules-loading').style.display  = 'block';
    document.getElementById('rules-content').style.display  = 'none';

    try {
        const res  = await fetch(RULES_SUGGEST_URL + '?min_frequency=' + encodeURIComponent(minFreq));
        const data = await res.json();

        document.getElementById('rules-meta').textContent =
            data.total_analyzed + ' Matches analysiert, davon '
            + data.skipped_auto + ' bereits vom Auto-Abgleich abgedeckt.';

        const hasSynonyms   = data.synonyms.length   > 0;
        const hasNoiseNinox = data.noise_ninox.length > 0;
        const hasNoiseWawi  = data.noise_wawi.length  > 0;
        document.getElementById('rules-no-results').style.display =
            (!hasSynonyms && !hasNoiseNinox && !hasNoiseWawi) ? 'block' : 'none';

        renderSuggestGroup('rules-synonyms', 'rules-synonyms-wrap', data.synonyms, function(item) {
            return buildSuggestRow(
                item.already_saved,
                '<code>' + escHtml(item.ninox_token) + '</code>'
                    + ' <span style="color:var(--c-muted)">→</span> '
                    + '<code>' + escHtml(item.wawi_token) + '</code>'
                    + ' <span style="color:var(--c-muted);font-size:12px">' + item.count + '×</span>',
                renderExamples(item.examples),
                function() { saveRule('synonym', item.ninox_token, item.wawi_token, this); }
            );
        });

        renderSuggestGroup('rules-noise-ninox', 'rules-noise-ninox-wrap', data.noise_ninox, function(item) {
            return buildSuggestRow(
                item.already_saved,
                '<code>' + escHtml(item.token) + '</code>'
                    + ' <span style="color:var(--c-muted)">→ (strip)</span>'
                    + ' <span style="color:var(--c-muted);font-size:12px">' + item.count + '×</span>',
                renderExamples(item.examples),
                function() { saveRule('noise', item.token, '', this); }
            );
        });

        renderSuggestGroup('rules-noise-wawi', 'rules-noise-wawi-wrap', data.noise_wawi, function(item) {
            return buildSuggestRow(
                item.already_saved,
                '<code>' + escHtml(item.token) + '</code>'
                    + ' <span style="color:var(--c-muted)">→ (strip WaWi)</span>'
                    + ' <span style="color:var(--c-muted);font-size:12px">' + item.count + '×</span>',
                renderExamples(item.examples),
                function() { saveRule('noise', item.token, '', this); }
            );
        });

        document.getElementById('rules-loading').style.display = 'none';
        document.getElementById('rules-content').style.display = 'block';
    } catch (e) {
        document.getElementById('rules-loading').textContent = 'Fehler: ' + e.message;
    }
}

function buildSuggestRow(alreadySaved, labelHtml, examplesHtml, saveFn) {
    const div = document.createElement('div');
    div.style.cssText = 'padding:10px 0;border-bottom:1px solid var(--c-border);display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap';

    const label = document.createElement('div');
    label.style.flex = '1';
    label.innerHTML  = labelHtml + examplesHtml;

    const btn = document.createElement('button');
    btn.type        = 'button';
    btn.className   = 'btn btn-sm ' + (alreadySaved ? 'btn-outline' : 'btn-primary');
    btn.textContent = alreadySaved ? '✓ Aktiv' : 'Übernehmen';
    btn.disabled    = alreadySaved;
    btn.style.cssText = 'white-space:nowrap;flex-shrink:0' + (alreadySaved ? ';color:var(--c-success);border-color:var(--c-success)' : '');
    btn.addEventListener('click', saveFn);

    div.appendChild(label);
    div.appendChild(btn);
    return div;
}

function renderSuggestGroup(contentId, wrapId, items, rowFn) {
    const wrap = document.getElementById(wrapId);
    const el   = document.getElementById(contentId);
    if (!items.length) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';
    el.innerHTML = '';
    items.forEach(function(item) { el.appendChild(rowFn(item)); });
}

async function saveRule(type, sourceToken, targetToken, btn) {
    btn.disabled    = true;
    btn.textContent = '…';
    try {
        const res  = await fetch(RULES_STORE_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body:    JSON.stringify({ type, source_token: sourceToken, target_token: targetToken }),
        });
        const data = await res.json();
        if (data.success) {
            btn.textContent = '✓ Gespeichert';
            btn.className   = 'btn btn-sm btn-outline';
            btn.style.color        = 'var(--c-success)';
            btn.style.borderColor  = 'var(--c-success)';
        } else {
            btn.textContent = 'Fehler';
            btn.disabled = false;
        }
    } catch (e) {
        btn.textContent = 'Fehler';
        btn.disabled = false;
    }
}

// ── Saved Rules ──────────────────────────────────────────────────────────────

async function loadSavedRules() {
    document.getElementById('saved-loading').style.display  = 'block';
    document.getElementById('saved-content').style.display  = 'none';

    try {
        const res   = await fetch(RULES_INDEX_URL);
        const rules = await res.json();

        const synonyms = rules.filter(function(r) { return r.type === 'synonym'; });
        const noise    = rules.filter(function(r) { return r.type === 'noise'; });

        document.getElementById('saved-empty').style.display = rules.length === 0 ? 'block' : 'none';

        renderSavedGroup('saved-synonyms', 'saved-synonyms-wrap', synonyms, function(r) {
            return '<code>' + escHtml(r.source_token) + '</code>'
                 + ' <span style="color:var(--c-muted)">→</span> '
                 + '<code>' + escHtml(r.target_token) + '</code>';
        });
        renderSavedGroup('saved-noise', 'saved-noise-wrap', noise, function(r) {
            return '<code>' + escHtml(r.source_token) + '</code>'
                 + ' <span style="color:var(--c-muted)">→ (strip)</span>';
        });

        document.getElementById('saved-loading').style.display = 'none';
        document.getElementById('saved-content').style.display = 'block';
    } catch (e) {
        document.getElementById('saved-loading').textContent = 'Fehler: ' + e.message;
    }
}

function renderSavedGroup(contentId, wrapId, rules, labelFn) {
    const wrap = document.getElementById(wrapId);
    const el   = document.getElementById(contentId);
    if (!rules.length) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';
    el.innerHTML = '';
    rules.forEach(function(r) {
        const row = document.createElement('div');
        row.style.cssText = 'padding:8px 0;border-bottom:1px solid var(--c-border);display:flex;align-items:center;gap:12px';

        const label = document.createElement('div');
        label.style.flex    = '1';
        label.style.fontSize = '13px';
        label.innerHTML      = labelFn(r);

        const del = document.createElement('button');
        del.type        = 'button';
        del.className   = 'btn btn-sm btn-outline';
        del.textContent = 'Löschen';
        del.style.cssText = 'color:var(--c-danger);border-color:var(--c-danger);white-space:nowrap';
        del.addEventListener('click', async function() {
            del.disabled = true;
            del.textContent = '…';
            await fetch(RULES_DELETE_URL + '/' + r.id, {
                method:  'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            });
            row.remove();
        });

        row.appendChild(label);
        row.appendChild(del);
        el.appendChild(row);
    });
}

// ── Shared helpers ────────────────────────────────────────────────────────────

function renderExamples(examples) {
    if (!examples || !examples.length) return '';
    const lines = examples.map(function(ex) {
        if (typeof ex === 'object') {
            return '<span style="color:#f59e0b">Ninox:</span> ' + escHtml(ex.ninox)
                 + ' <span style="color:var(--c-muted)">→</span> '
                 + '<span style="color:#10b981">WaWi:</span> ' + escHtml(ex.wawi);
        }
        return escHtml(String(ex));
    });
    return '<div style="font-size:11px;color:var(--c-muted);margin-top:4px;line-height:1.7">'
         + lines.join('<br>') + '</div>';
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

document.getElementById('rules-modal').addEventListener('click', function(e) {
    if (e.target === this) closeRulesModal();
});
</script>

{{-- ── Manuell-Verknüpfungs-Modal ── --}}
<div id="match-modal" style="display:none;position:fixed;inset:0;z-index:1000;
     background:rgba(0,0,0,.55);align-items:flex-start;justify-content:center;
     padding-top:40px;overflow-y:auto">
    <div style="background:var(--c-surface);border:1px solid var(--c-border);
                border-radius:10px;width:min(720px,95vw);padding:24px;
                position:relative;margin-bottom:40px">

        <button onclick="closeMatchModal()"
                style="position:absolute;top:12px;right:14px;font-size:18px;
                       background:none;border:none;cursor:pointer;color:var(--c-muted)">✕</button>

        <h3 style="margin:0 0 16px;font-size:16px">Artikel verknüpfen / neu anlegen</h3>

        {{-- Ninox-Info --}}
        <div style="margin-bottom:6px;font-size:11px;font-weight:600;text-transform:uppercase;
                    letter-spacing:.05em;color:var(--c-muted)">Ninox-Artikel</div>
        <div id="modal-ninox-info" style="padding:12px;background:var(--c-bg);
             border-radius:6px;margin-bottom:16px;font-size:13px;line-height:1.6"></div>

        {{-- Tabs --}}
        <div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid var(--c-border)">
            <button type="button" id="mtab-btn-search" onclick="switchMatchTab('search')"
                    style="padding:6px 14px;border:1px solid transparent;border-bottom:none;
                           border-radius:4px 4px 0 0;font-size:13px;cursor:pointer;
                           background:var(--c-primary);color:#fff;margin-bottom:-1px">
                WaWi-Artikel suchen
            </button>
            <button type="button" id="mtab-btn-create" onclick="switchMatchTab('create')"
                    style="padding:6px 14px;border:1px solid var(--c-border);border-bottom:none;
                           border-radius:4px 4px 0 0;font-size:13px;cursor:pointer;
                           background:var(--c-surface);color:var(--c-text);margin-bottom:-1px">
                Neu anlegen
            </button>
        </div>

        {{-- Tab: WaWi suchen --}}
        <div id="mtab-search">
            <input id="wawi-search-input" type="text" placeholder="Name, Art.-Nr. oder EAN eingeben…"
                   style="width:100%;box-sizing:border-box;padding:8px 10px;
                          border:1px solid var(--c-border);border-radius:4px;font-size:14px;
                          margin-bottom:6px;background:var(--c-bg);color:var(--c-text)">

            <div id="gebinde-filter-bar" style="display:none;margin-bottom:8px">
                <button type="button" id="gebinde-filter-btn" onclick="toggleGebindeFilter()"
                        class="btn btn-sm btn-outline" style="font-size:12px"></button>
            </div>

            <div id="wawi-search-results" style="max-height:260px;overflow-y:auto;
                 border:1px solid var(--c-border);border-radius:6px;min-height:40px"></div>

            <div id="modal-selected" style="display:none;margin-top:12px;padding:10px 12px;
                 background:color-mix(in srgb,var(--c-success) 12%,var(--c-surface));
                 border:1px solid var(--c-success);border-radius:6px;font-size:13px"></div>

            <form id="modal-confirm-form" method="POST"
                  action="{{ route('admin.reconcile.products.confirm') }}"
                  onsubmit="return submitConfirmForm(event)"
                  style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
                @csrf
                <input type="hidden" name="ninox_id" id="modal-ninox-id">
                <input type="hidden" name="wawi_id"  id="modal-wawi-id">
                <button type="button" onclick="closeMatchModal()" class="btn btn-sm btn-outline">Abbrechen</button>
                <button type="submit" id="modal-submit-btn" class="btn btn-sm btn-primary" disabled>
                    Verknüpfen &amp; Bestätigen
                </button>
            </form>
        </div>

        {{-- Tab: Neu anlegen --}}
        <div id="mtab-create" style="display:none">
            <div id="create-form-wrap">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div style="grid-column:1/-1">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Produktname *</label>
                        <input id="np-name" type="text"
                               style="width:100%;box-sizing:border-box;padding:7px 10px;
                                      border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                      background:var(--c-bg);color:var(--c-text)">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Artikelnummer *</label>
                        <input id="np-artnr" type="text"
                               style="width:100%;box-sizing:border-box;padding:7px 10px;
                                      border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                      background:var(--c-bg);color:var(--c-text)">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">EAN (optional)</label>
                        <input id="np-ean" type="text"
                               style="width:100%;box-sizing:border-box;padding:7px 10px;
                                      border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                      background:var(--c-bg);color:var(--c-text)">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Bruttopreis (€) *</label>
                        <input id="np-price" type="number" step="0.01" min="0"
                               style="width:100%;box-sizing:border-box;padding:7px 10px;
                                      border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                      background:var(--c-bg);color:var(--c-text)">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">MwSt-Satz *</label>
                        <select id="np-tax"
                                style="width:100%;box-sizing:border-box;padding:7px 10px;
                                       border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                       background:var(--c-bg);color:var(--c-text)">
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Warengruppe</label>
                        <select id="np-wg"
                                style="width:100%;box-sizing:border-box;padding:7px 10px;
                                       border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                       background:var(--c-bg);color:var(--c-text)">
                            <option value="">— keine —</option>
                        </select>
                    </div>
                </div>

                <div id="np-error" style="display:none;margin-top:12px;padding:10px 12px;
                     background:color-mix(in srgb,var(--c-danger) 12%,var(--c-surface));
                     border:1px solid var(--c-danger);border-radius:6px;font-size:13px;
                     color:var(--c-danger)"></div>

                <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end">
                    <button type="button" onclick="closeMatchModal()" class="btn btn-sm btn-outline">Abbrechen</button>
                    <button type="button" id="np-submit-btn" onclick="submitNewProduct()"
                            class="btn btn-sm btn-primary">
                        Produkt anlegen &amp; verknüpfen
                    </button>
                </div>
            </div>

            <div id="create-success" style="display:none;padding:20px;text-align:center">
                <div style="font-size:32px;margin-bottom:8px">✓</div>
                <div style="font-weight:600;margin-bottom:4px" id="create-success-name"></div>
                <div style="font-size:13px;color:var(--c-muted)" id="create-success-artnr"></div>
                <button type="button" onclick="closeMatchModal();location.reload()"
                        class="btn btn-sm btn-primary" style="margin-top:16px">
                    Seite neu laden
                </button>
            </div>
        </div>

    </div>
</div>

<script>
const WAWI_SEARCH_URL    = '{{ route("admin.reconcile.products.wawi-search") }}';
const NEW_PRODUCT_URL    = '{{ route("admin.reconcile.products.create-product") }}';
const FORM_DATA_URL      = '{{ route("admin.reconcile.products.new-product-form-data") }}';
const MATCH_CSRF         = '{{ csrf_token() }}';

let _currentNinoxData  = {};
let _formDataLoaded    = false;
let _gebindeCount      = 0;
let _gebindeFilter     = false;
let _selectedWawiName  = '';

function openMatchModal(ninoxId, ninoxData) {
    _currentNinoxData = ninoxData;

    document.getElementById('modal-ninox-id').value = ninoxId;
    document.getElementById('modal-wawi-id').value  = '';
    document.getElementById('modal-submit-btn').disabled = true;
    document.getElementById('modal-selected').style.display = 'none';
    document.getElementById('wawi-search-results').innerHTML = '';
    document.getElementById('wawi-search-input').value = '';
    document.getElementById('create-success').style.display = 'none';
    document.getElementById('create-form-wrap').style.display = 'block';
    document.getElementById('np-error').style.display = 'none';

    const info = document.getElementById('modal-ninox-info');
    let html = '<strong>' + escHtmlM(ninoxData.artikelname || '—') + '</strong>';
    if (ninoxData.artnummer)           html += ' <span style="color:var(--c-muted)">· Art.-Nr. ' + escHtmlM(ninoxData.artnummer) + '</span>';
    if (ninoxData.ean)                 html += ' <span style="color:var(--c-muted)">· EAN: ' + escHtmlM(ninoxData.ean) + '</span>';
    if (parseFloat(ninoxData.vk_brutto_markt) > 0)
        html += ' <span style="color:var(--c-muted)">· ' + parseFloat(ninoxData.vk_brutto_markt).toFixed(2).replace('.', ',') + ' € brutto</span>';
    info.innerHTML = html;

    // Gebinde-Filter: detect pattern like "6x0,33" in ninox name
    const gebindeMatch = (ninoxData.artikelname || '').match(/(\d+)\s*[xX]\s*[\d,.]+/);
    _gebindeCount  = gebindeMatch ? parseInt(gebindeMatch[1], 10) : 0;
    _gebindeFilter    = _gebindeCount > 0;
    _selectedWawiName = '';
    const filterBar = document.getElementById('gebinde-filter-bar');
    filterBar.style.display = _gebindeCount > 0 ? 'block' : 'none';
    updateGebindeFilterBtn();

    switchMatchTab('search');
    document.getElementById('match-modal').style.display = 'flex';
    setTimeout(function() { document.getElementById('wawi-search-input').focus(); }, 80);

    if (!_formDataLoaded) loadFormData();
}

function closeMatchModal() {
    document.getElementById('match-modal').style.display = 'none';
}

function switchMatchTab(name) {
    ['search', 'create'].forEach(function(t) {
        document.getElementById('mtab-' + t).style.display = t === name ? 'block' : 'none';
        const btn = document.getElementById('mtab-btn-' + t);
        if (t === name) {
            btn.style.background  = 'var(--c-primary)';
            btn.style.color       = '#fff';
            btn.style.borderColor = 'transparent';
        } else {
            btn.style.background  = 'var(--c-surface)';
            btn.style.color       = 'var(--c-text)';
            btn.style.borderColor = 'var(--c-border)';
        }
    });
    if (name === 'create') prefillNewProductForm();
}

async function loadFormData() {
    try {
        const res  = await fetch(FORM_DATA_URL);
        const data = await res.json();

        const taxSel = document.getElementById('np-tax');
        taxSel.innerHTML = '';
        data.tax_rates.forEach(function(t) {
            const o   = document.createElement('option');
            o.value       = t.id;
            o.textContent = t.name;
            if (t.rate_basis_points === 1900) o.selected = true;
            taxSel.appendChild(o);
        });

        const wgSel = document.getElementById('np-wg');
        data.warengruppen.forEach(function(w) {
            const o   = document.createElement('option');
            o.value       = w.id;
            o.textContent = w.name;
            wgSel.appendChild(o);
        });

        _formDataLoaded = true;
    } catch (e) { /* fail silently, form still usable */ }
}

function prefillNewProductForm() {
    const d = _currentNinoxData;
    document.getElementById('np-name').value  = d.artikelname  || '';
    document.getElementById('np-artnr').value = d.artnummer    || '';
    document.getElementById('np-ean').value   = d.ean          || '';
    document.getElementById('np-price').value = parseFloat(d.vk_brutto_markt) > 0
        ? parseFloat(d.vk_brutto_markt).toFixed(2) : '';
}

async function submitNewProduct() {
    const ninoxId = document.getElementById('modal-ninox-id').value;
    const name    = document.getElementById('np-name').value.trim();
    const artnr   = document.getElementById('np-artnr').value.trim();
    const ean     = document.getElementById('np-ean').value.trim();
    const price   = document.getElementById('np-price').value.trim();
    const taxId   = document.getElementById('np-tax').value;
    const wgId    = document.getElementById('np-wg').value;

    const errEl = document.getElementById('np-error');
    errEl.style.display = 'none';

    if (!name || !artnr || !price) {
        errEl.textContent = 'Bitte Produktname, Artikelnummer und Bruttopreis ausfüllen.';
        errEl.style.display = 'block';
        return;
    }

    const btn = document.getElementById('np-submit-btn');
    btn.disabled    = true;
    btn.textContent = '…';

    try {
        const res  = await fetch(NEW_PRODUCT_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': MATCH_CSRF },
            body: JSON.stringify({
                ninox_id:       ninoxId,
                produktname:    name,
                artikelnummer:  artnr,
                ean:            ean,
                brutto_preis:   price,
                tax_rate_id:    taxId,
                warengruppe_id: wgId || null,
            }),
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('create-form-wrap').style.display  = 'none';
            document.getElementById('create-success').style.display    = 'block';
            document.getElementById('create-success-name').textContent = data.produktname;
            document.getElementById('create-success-artnr').textContent = 'Art.-Nr. ' + data.artikelnummer;
        } else {
            errEl.textContent = data.error || 'Unbekannter Fehler.';
            errEl.style.display = 'block';
            btn.disabled    = false;
            btn.textContent = 'Produkt anlegen & verknüpfen';
        }
    } catch (e) {
        errEl.textContent = 'Netzwerkfehler: ' + e.message;
        errEl.style.display = 'block';
        btn.disabled    = false;
        btn.textContent = 'Produkt anlegen & verknüpfen';
    }
}

function selectWawi(el) {
    document.getElementById('modal-wawi-id').value = el.dataset.id;
    document.getElementById('modal-submit-btn').disabled = false;
    _selectedWawiName = el.dataset.name || '';

    const sel = document.getElementById('modal-selected');
    sel.style.display = 'block';
    let html = '<strong>Gewählt:</strong> ' + escHtmlM(el.dataset.name);
    if (el.dataset.artnr) html += ' <span style="color:var(--c-muted)">· Nr: ' + escHtmlM(el.dataset.artnr) + '</span>';
    if (el.dataset.ean)   html += ' <span style="color:var(--c-muted)">· EAN: ' + escHtmlM(el.dataset.ean) + '</span>';
    if (parseFloat(el.dataset.price) > 0)
        html += ' <span style="color:var(--c-muted)">· ' + parseFloat(el.dataset.price).toFixed(2).replace('.', ',') + ' € netto</span>';
    sel.innerHTML = html;

    document.querySelectorAll('.wawi-result-row').forEach(function(r) {
        r.style.background = r === el ? 'color-mix(in srgb,var(--c-primary) 12%,var(--c-surface))' : '';
    });
}

function updateGebindeFilterBtn() {
    const btn = document.getElementById('gebinde-filter-btn');
    if (!btn) return;
    if (_gebindeFilter) {
        btn.textContent    = 'Nur ' + _gebindeCount + '× (Filter aktiv) ✕';
        btn.style.color       = 'var(--c-primary)';
        btn.style.borderColor = 'var(--c-primary)';
    } else {
        btn.textContent    = _gebindeCount + '× Filter (deaktiviert)';
        btn.style.color       = 'var(--c-muted)';
        btn.style.borderColor = 'var(--c-border)';
    }
}

function toggleGebindeFilter() {
    _gebindeFilter = !_gebindeFilter;
    updateGebindeFilterBtn();
    const q = document.getElementById('wawi-search-input').value.trim();
    if (q.length >= 2) fetchWawi(q);
}

async function fetchWawi(q) {
    const el = document.getElementById('wawi-search-results');
    el.innerHTML = '<div style="padding:12px;color:var(--c-muted);font-size:13px">Suche…</div>';
    try {
        let url = WAWI_SEARCH_URL + '?q=' + encodeURIComponent(q);
        if (_gebindeFilter && _gebindeCount > 0) {
            url += '&gebinde_count=' + _gebindeCount;
        }
        const res  = await fetch(url);
        const rows = await res.json();
        if (!rows.length) {
            let noResultMsg = '<div style="padding:12px;color:var(--c-muted);font-size:13px">Keine Treffer';
            if (_gebindeFilter && _gebindeCount > 0) {
                noResultMsg += ' mit Filter <strong>' + _gebindeCount + '×</strong>'
                    + ' — <a href="#" onclick="toggleGebindeFilter();return false" style="color:var(--c-primary)">'
                    + 'Filter deaktivieren?</a>';
            } else {
                noResultMsg += ' — <a href="#" onclick="switchMatchTab(\'create\');return false" style="color:var(--c-primary)">'
                    + 'Artikel neu anlegen?</a>';
            }
            el.innerHTML = noResultMsg + '</div>';
            return;
        }
        el.innerHTML = rows.map(function(r) {
            const name  = escHtmlM(r.cName || '—');
            const artnr = escHtmlM(r.cArtNr  || '');
            const ean   = escHtmlM(r.cBarcode || '');
            const price = r.fVKNetto || 0;
            return '<div class="wawi-result-row"'
                 + ' data-id="' + r.kArtikel + '"'
                 + ' data-name="' + (r.cName || '').replace(/"/g,'&quot;') + '"'
                 + ' data-artnr="' + (r.cArtNr || '') + '"'
                 + ' data-ean="' + (r.cBarcode || '') + '"'
                 + ' data-price="' + price + '"'
                 + ' style="padding:10px 12px;border-bottom:1px solid var(--c-border);cursor:pointer;font-size:13px"'
                 + ' onmouseenter="this.style.background=\'var(--c-bg)\'"'
                 + ' onmouseleave="this.style.background=\'\'"'
                 + ' onclick="selectWawi(this)">'
                 + '<strong>' + name + '</strong>'
                 + (artnr ? '<span style="color:var(--c-muted);margin-left:8px">Nr: ' + artnr + '</span>' : '')
                 + (ean   ? '<span style="color:var(--c-muted);margin-left:8px">EAN: ' + ean + '</span>' : '')
                 + (parseFloat(price) > 0
                     ? '<span style="float:right;color:var(--c-muted)">' + parseFloat(price).toFixed(2).replace('.', ',') + ' € netto</span>'
                     : '')
                 + '</div>';
        }).join('');
    } catch (e) {
        el.innerHTML = '<div style="padding:12px;color:var(--c-danger);font-size:13px">Fehler bei der Suche</div>';
    }
}

function escHtmlM(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function submitConfirmForm(event) {
    event.preventDefault();
    const ninoxId = document.getElementById('modal-ninox-id').value;
    const wawiId  = document.getElementById('modal-wawi-id').value;
    const btn     = document.getElementById('modal-submit-btn');

    btn.disabled    = true;
    btn.textContent = '…';

    try {
        const body = { ninox_id: ninoxId };
        if (wawiId) body.wawi_id = wawiId;

        const res  = await fetch('{{ route("admin.reconcile.products.confirm") }}', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': MATCH_CSRF, 'Accept': 'application/json' },
            body:    JSON.stringify(body),
        });
        const data = await res.json();

        if (data.success) {
            closeMatchModal();
            applyConfirmedRow(ninoxId, _selectedWawiName || null);
        } else {
            btn.textContent = 'Fehler — erneut versuchen';
            btn.disabled    = false;
        }
    } catch (e) {
        btn.textContent = 'Fehler — erneut versuchen';
        btn.disabled    = false;
    }
    return false;
}

function applyConfirmedRow(ninoxId, wawiName) {
    const row = document.querySelector('tr[data-ninox-id="' + ninoxId + '"]');
    if (!row) return;

    // Status-Zelle
    const statusCell = row.querySelector('td:nth-last-child(2)');
    if (statusCell) {
        statusCell.innerHTML = '<span style="color:#10b981;font-weight:600">✓ OK</span>';
    }

    // Aktions-Zelle: Info + Löschen + Manuell
    const actionCell = row.querySelector('td:last-child');
    if (!actionCell) return;

    const info = wawiName
        ? '<div style="font-size:11px;color:#10b981;margin-bottom:6px">Verknüpft mit: ' + escHtmlM(wawiName) + '</div>'
        : '<div style="font-size:11px;color:#10b981;margin-bottom:6px">Ninox-only bestätigt</div>';

    // Reset-Form + Manuell-Button via JS (CSRF aus Konstante)
    const resetForm = '<form method="POST" action="{{ route("admin.reconcile.products.reset") }}" style="display:inline">'
        + '<input type="hidden" name="_token" value="' + MATCH_CSRF + '">'
        + '<input type="hidden" name="ninox_id" value="' + ninoxId + '">'
        + '<button type="submit" class="btn btn-sm btn-outline" style="color:var(--c-muted)" title="Verknüpfung löschen">✕ Löschen</button>'
        + '</form>';

    // Ninox-Daten für Manuell-Button aus dem Row lesen (data-Attribut nicht vorhanden,
    // aber _currentNinoxData ist noch gesetzt da wir gerade das Modal hatten)
    const ninoxData = _currentNinoxData;
    actionCell.innerHTML = info
        + '<div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;margin-top:2px">'
        + resetForm
        + '<button type="button" class="btn btn-sm btn-outline" '
        + 'style="color:var(--c-primary);border-color:var(--c-primary)" '
        + 'onclick="openMatchModal(\'' + ninoxId + '\',' + JSON.stringify(ninoxData) + ')">'
        + 'Manuell</button>'
        + '</div>';
}

async function confirmIgnored(btn, ninoxId, wawiId) {
    btn.disabled    = true;
    btn.textContent = '…';

    try {
        const body = { ninox_id: ninoxId };
        if (wawiId) body.wawi_id = wawiId;

        const res  = await fetch('{{ route("admin.reconcile.products.confirm") }}', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': MATCH_CSRF, 'Accept': 'application/json' },
            body:    JSON.stringify(body),
        });
        const data = await res.json();

        if (data.success) {
            const row = btn.closest('tr');

            // Status-Zelle aktualisieren
            const statusCell = row.querySelector('td:nth-last-child(2)');
            if (statusCell) {
                statusCell.textContent = '✓ OK';
                statusCell.style.cssText = 'color:#10b981;font-weight:600;font-size:13px';
            }

            // Aktionsbereich: nur noch "Löschen"-Button + "Manuell"-Button behalten,
            // "✓ Bestätigen" entfernen
            btn.remove();
        } else {
            btn.textContent = 'Fehler';
            btn.disabled    = false;
        }
    } catch (e) {
        btn.textContent = 'Fehler';
        btn.disabled    = false;
    }
}

let searchTimer = null;
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('wawi-search-input').addEventListener('input', function () {
        clearTimeout(searchTimer);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('wawi-search-results').innerHTML = ''; return; }
        searchTimer = setTimeout(function() { fetchWawi(q); }, 280);
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMatchModal();
    });

    document.getElementById('match-modal').addEventListener('click', function(e) {
        if (e.target === this) closeMatchModal();
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('select-all');
    const bulkBar   = document.getElementById('bulk-bar');
    const bulkCount = document.getElementById('bulk-count');

    function getChecked() {
        return Array.from(document.querySelectorAll('.row-check:checked'));
    }

    function updateBulkBar() {
        const checked = getChecked();
        bulkBar.style.display = checked.length > 0 ? 'flex' : 'none';
        bulkCount.textContent = checked.length;
    }

    selectAll?.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(cb => { cb.checked = this.checked; });
        updateBulkBar();
    });

    document.querySelectorAll('.row-check').forEach(cb => {
        cb.addEventListener('change', function () {
            updateBulkBar();
            if (!this.checked && selectAll) selectAll.checked = false;
        });
    });

    ['bulk-confirm-form', 'bulk-ignore-form'].forEach(function (formId) {
        document.getElementById(formId)?.addEventListener('submit', function () {
            getChecked().forEach(function (cb) {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'ninox_ids[]';
                input.value = cb.value;
                this.appendChild(input);
            }, this);
        });
    });
});
</script>

@endsection
