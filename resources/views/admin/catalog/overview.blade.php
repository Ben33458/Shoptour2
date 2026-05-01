@extends('admin.layout')
@section('title', 'Gesamtübersicht Produkte')

@section('content')
<div class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    {{-- Stats bar --}}
    <div style="display:flex;gap:1.5rem;margin-bottom:1rem;font-size:13px;color:var(--c-muted)">
        <span>Gesamt: <strong style="color:var(--c-text)">{{ number_format($stats['total']) }}</strong></span>
        <span>Nicht verknüpft: <strong style="color:var(--c-text)">{{ number_format($stats['unlinked']) }}</strong></span>
        <span>Nur Shoptour2: <strong style="color:var(--c-text)">{{ number_format($stats['local_only']) }}</strong></span>
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('admin.catalog.overview') }}"
          style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center">
        <input type="text" name="search" value="{{ $search }}"
               placeholder="Suche nach Name oder ArtNr …"
               style="flex:1;min-width:220px;max-width:340px">
        <select name="filter" style="min-width:220px">
            @php
                $filterOptions = [
                    'all'        => 'Alle Produkte',
                    'unlinked'   => 'Nicht vollst. verknüpft',
                    'local_only' => 'Nur Shoptour2 (kein NX/WW)',
                    'ninox_only' => 'Nur Ninox (nicht verknüpft)',
                    'wawi_only'  => 'Nur WaWi (nicht verknüpft)',
                ];
            @endphp
            @foreach($filterOptions as $val => $label)
                <option value="{{ $val }}" @selected($filter === $val)>
                    {{ $label }} ({{ number_format($filterCounts[$val]) }})
                </option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filtern</button>
        @if($search !== '' || $filter !== 'all')
            <a href="{{ route('admin.catalog.overview') }}" class="btn btn-outline btn-sm">Zurücksetzen</a>
        @endif
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:110px">ArtNr</th>
                    <th>Produktname</th>
                    <th style="width:90px;text-align:right">VK Brutto</th>
                    <th style="width:120px">Verfügbarkeit</th>
                    <th style="width:110px;text-align:center">Verknüpfungen</th>
                    <th style="width:60px;text-align:center">Im Shop</th>
                    <th style="width:120px">Warengruppe</th>
                    <th style="width:120px">Kategorie</th>
                    <th style="width:120px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($paginated as $row)
                @php
                    $sourceBadgeColor = match($row['source']) {
                        'local' => '#2563eb',
                        'ninox' => '#7c3aed',
                        'wawi'  => '#0891b2',
                    };
                    $sourceBadgeLabel = match($row['source']) {
                        'local' => 'S2',
                        'ninox' => 'NX',
                        'wawi'  => 'WW',
                    };
                    $canModal = $row['ninox_id'] !== null;
                @endphp
                <tr data-ninox-id="{{ $row['ninox_id'] ?? '' }}" data-wawi-id="{{ $row['wawi_id'] ?? '' }}" data-local-id="{{ $row['local_id'] ?? '' }}">
                    {{-- ArtNr --}}
                    <td style="font-size:12px;color:var(--c-muted)">{{ $row['artnr'] ?: '—' }}</td>

                    {{-- Name + source badge --}}
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:.4rem;flex-wrap:wrap">
                            <span>{{ $row['name'] ?: '—' }}</span>
                            <span style="font-size:10px;font-weight:600;padding:1px 5px;border-radius:3px;
                                         background:{{ $sourceBadgeColor }}22;color:{{ $sourceBadgeColor }};
                                         border:1px solid {{ $sourceBadgeColor }}55;letter-spacing:.05em">
                                {{ $sourceBadgeLabel }}
                            </span>
                        </span>
                    </td>

                    {{-- VK Brutto --}}
                    <td style="text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap">
                        @if($row['vk_brutto'] !== null)
                            {{ number_format($row['vk_brutto'], 2, ',', '.') }} €
                            @if($row['source'] === 'wawi')
                                <span title="ca. (Netto × 1,19)" style="font-size:10px;color:var(--c-muted)">~</span>
                            @endif
                        @else
                            —
                        @endif
                    </td>

                    {{-- Verfügbarkeit --}}
                    <td>
                        @if($row['availability'] !== null)
                        @php
                            [$avBg, $avColor, $avLabel] = match($row['availability']) {
                                'available'    => ['#dcfce7', '#166534', 'Verfügbar'],
                                'preorder'     => ['#fef9c3', '#854d0e', 'Vorbestellung'],
                                'out_of_stock' => ['#fee2e2', '#991b1b', 'Nicht verfügbar'],
                                'discontinued' => ['#f3f4f6', '#6b7280', 'Eingestellt'],
                                'stock_based'  => ['#dbeafe', '#1e40af', 'Lagerbasiert'],
                                default        => ['#f3f4f6', '#6b7280', $row['availability']],
                            };
                        @endphp
                        <span style="font-size:11px;padding:2px 7px;border-radius:9999px;
                                     background:{{ $avBg }};color:{{ $avColor }};white-space:nowrap">
                            {{ $avLabel }}
                        </span>
                        @else
                            <span style="color:var(--c-muted)">—</span>
                        @endif
                    </td>

                    {{-- Verknüpfungen: S2 NX WW — clickable when ninox_id known --}}
                    <td class="badges-cell" style="text-align:center;white-space:nowrap">
                        @if($canModal)
                            <span role="button"
                                  onclick="openLinkModal('{{ $row['ninox_id'] }}', @js($row['ninox_data']), {{ $row['has_local'] ? 'true' : 'false' }})"
                                  title="Klicken zum Verknüpfen"
                                  style="cursor:pointer;display:inline-flex;gap:2px;align-items:center;
                                         border-radius:5px;padding:2px 3px;
                                         transition:background .15s"
                                  onmouseenter="this.style.background='var(--c-bg)'"
                                  onmouseleave="this.style.background='transparent'">
                        @else
                            <a href="{{ route('admin.reconcile.products') }}?search={{ urlencode($row['artnr'] ?: $row['name']) }}"
                               title="Zum Abgleich"
                               style="text-decoration:none;display:inline-flex;gap:2px;align-items:center;
                                      border-radius:5px;padding:2px 3px;
                                      transition:background .15s"
                               onmouseenter="this.style.background='var(--c-bg)'"
                               onmouseleave="this.style.background='transparent'">
                        @endif
                            @foreach([['S2', $row['has_local'], 'Shoptour2'], ['NX', $row['has_ninox'], 'Ninox'], ['WW', $row['has_wawi'], 'WaWi']] as [$lbl, $active, $badgeTitle])
                            <span title="{{ $badgeTitle }}"
                                  style="display:inline-block;width:24px;font-size:10px;font-weight:700;
                                         text-align:center;border-radius:3px;padding:1px 0;
                                         {{ $active ? 'background:#dcfce7;color:#166534;border:1px solid #86efac' : 'background:#f3f4f6;color:#9ca3af;border:1px solid #e5e7eb' }}">
                                {{ $lbl }}
                            </span>
                            @endforeach
                        @if($canModal)
                            </span>
                        @else
                            </a>
                        @endif
                    </td>

                    {{-- Im Shop --}}
                    <td style="text-align:center">
                        @if($row['show_in_shop'] === null)
                            <span style="color:var(--c-muted)">—</span>
                        @elseif($row['show_in_shop'])
                            <span style="color:#16a34a;font-size:16px" title="Sichtbar">✓</span>
                        @else
                            <span style="color:#9ca3af;font-size:14px" title="Versteckt">–</span>
                        @endif
                    </td>

                    {{-- Warengruppe --}}
                    <td style="font-size:12px;color:var(--c-muted)">{{ $row['warengruppe'] ?? '—' }}</td>

                    {{-- Kategorie --}}
                    <td style="font-size:12px;color:var(--c-muted)">{{ $row['kategorie'] ?? '—' }}</td>

                    {{-- Aktionen --}}
                    <td style="white-space:nowrap">
                        @if($row['local_id'])
                            <a href="{{ route('admin.products.edit', $row['local_id']) }}"
                               class="btn btn-outline btn-xs" style="margin-right:3px">Bearbeiten</a>
                        @else
                            <button class="btn btn-outline btn-xs"
                                    style="margin-right:3px"
                                    onclick="quickCreateProduct(this, '{{ $row['ninox_id'] ?? '' }}', '{{ $row['wawi_id'] ?? '' }}')">
                                In S2 anlegen
                            </button>
                        @endif
                        <a href="{{ route('admin.reconcile.products') }}?search={{ urlencode($row['artnr'] ?: $row['name']) }}"
                           class="btn btn-outline btn-xs">Abgleich</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;padding:2rem;color:var(--c-muted)">
                        Keine Produkte gefunden.
                        @if($search !== '' || $filter !== 'all')
                            <a href="{{ route('admin.catalog.overview') }}">Filter zurücksetzen</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem">
        {{ $paginated->withQueryString()->links() }}
    </div>
</div>

{{-- ── Verknüpfungs-Modal ──────────────────────────────────────────────────── --}}
<div id="link-modal" style="display:none;position:fixed;inset:0;z-index:1000;
     background:rgba(0,0,0,.55);align-items:flex-start;justify-content:center;
     padding-top:40px;overflow-y:auto">
    <div style="background:var(--c-surface);border:1px solid var(--c-border);
                border-radius:10px;width:min(720px,95vw);padding:24px;
                position:relative;margin-bottom:40px">

        <button onclick="closeLinkModal()"
                style="position:absolute;top:12px;right:14px;font-size:18px;
                       background:none;border:none;cursor:pointer;color:var(--c-muted)">✕</button>

        <h3 style="margin:0 0 16px;font-size:16px">Produkt verknüpfen / neu anlegen</h3>

        {{-- Source info --}}
        <div style="margin-bottom:6px;font-size:11px;font-weight:600;text-transform:uppercase;
                    letter-spacing:.05em;color:var(--c-muted)" id="link-source-label">Ninox-Artikel</div>
        <div id="link-ninox-info" style="padding:12px;background:var(--c-bg);
             border-radius:6px;margin-bottom:16px;font-size:13px;line-height:1.6"></div>

        {{-- Tabs --}}
        <div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid var(--c-border)">
            <button type="button" id="link-tab-btn-search" onclick="switchLinkTab('search')"
                    style="padding:6px 14px;border:1px solid transparent;border-bottom:none;
                           border-radius:4px 4px 0 0;font-size:13px;cursor:pointer;
                           background:var(--c-primary);color:#fff;margin-bottom:-1px">
                WaWi-Artikel suchen
            </button>
            <button type="button" id="link-tab-btn-create" onclick="switchLinkTab('create')"
                    style="padding:6px 14px;border:1px solid var(--c-border);border-bottom:none;
                           border-radius:4px 4px 0 0;font-size:13px;cursor:pointer;
                           background:var(--c-surface);color:var(--c-text);margin-bottom:-1px">
                Neu anlegen
            </button>
        </div>

        {{-- Tab: WaWi suchen --}}
        <div id="link-tab-search">
            <input id="link-search-input" type="text" placeholder="Name, Art.-Nr. oder EAN eingeben…"
                   style="width:100%;box-sizing:border-box;padding:8px 10px;
                          border:1px solid var(--c-border);border-radius:4px;font-size:14px;
                          margin-bottom:6px;background:var(--c-bg);color:var(--c-text)">

            <div id="link-gebinde-bar" style="display:none;margin-bottom:8px">
                <button type="button" id="link-gebinde-btn" onclick="toggleLinkGebinde()"
                        class="btn btn-sm btn-outline" style="font-size:12px"></button>
            </div>

            <div id="link-search-results" style="max-height:260px;overflow-y:auto;
                 border:1px solid var(--c-border);border-radius:6px;min-height:40px"></div>

            <div id="link-selected" style="display:none;margin-top:12px;padding:10px 12px;
                 background:color-mix(in srgb,var(--c-success) 12%,var(--c-surface));
                 border:1px solid var(--c-success);border-radius:6px;font-size:13px"></div>

            <form id="link-confirm-form" method="POST"
                  action="{{ route('admin.reconcile.products.confirm') }}"
                  onsubmit="return submitLinkConfirm(event)"
                  style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
                @csrf
                <input type="hidden" name="ninox_id" id="link-ninox-id">
                <input type="hidden" name="wawi_id"  id="link-wawi-id">
                <button type="button" onclick="closeLinkModal()" class="btn btn-sm btn-outline">Abbrechen</button>
                <button type="submit" id="link-submit-btn" class="btn btn-sm btn-primary" disabled>
                    Verknüpfen &amp; Bestätigen
                </button>
            </form>
        </div>

        {{-- Tab: Neu anlegen --}}
        <div id="link-tab-create" style="display:none">
            <div id="link-create-form">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div style="grid-column:1/-1">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Produktname *</label>
                        <input id="link-np-name" type="text"
                               style="width:100%;box-sizing:border-box;padding:7px 10px;
                                      border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                      background:var(--c-bg);color:var(--c-text)">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Artikelnummer *</label>
                        <input id="link-np-artnr" type="text"
                               style="width:100%;box-sizing:border-box;padding:7px 10px;
                                      border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                      background:var(--c-bg);color:var(--c-text)">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">EAN (optional)</label>
                        <input id="link-np-ean" type="text"
                               style="width:100%;box-sizing:border-box;padding:7px 10px;
                                      border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                      background:var(--c-bg);color:var(--c-text)">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Bruttopreis (€) *</label>
                        <input id="link-np-price" type="number" step="0.01" min="0"
                               style="width:100%;box-sizing:border-box;padding:7px 10px;
                                      border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                      background:var(--c-bg);color:var(--c-text)">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">MwSt-Satz *</label>
                        <select id="link-np-tax"
                                style="width:100%;box-sizing:border-box;padding:7px 10px;
                                       border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                       background:var(--c-bg);color:var(--c-text)"></select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Warengruppe</label>
                        <select id="link-np-wg"
                                style="width:100%;box-sizing:border-box;padding:7px 10px;
                                       border:1px solid var(--c-border);border-radius:4px;font-size:13px;
                                       background:var(--c-bg);color:var(--c-text)">
                            <option value="">— keine —</option>
                        </select>
                    </div>
                </div>

                <div id="link-np-error" style="display:none;margin-top:12px;padding:10px 12px;
                     background:color-mix(in srgb,var(--c-danger) 12%,var(--c-surface));
                     border:1px solid var(--c-danger);border-radius:6px;font-size:13px;
                     color:var(--c-danger)"></div>

                <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end">
                    <button type="button" onclick="closeLinkModal()" class="btn btn-sm btn-outline">Abbrechen</button>
                    <button type="button" id="link-np-submit" onclick="submitLinkNewProduct()"
                            class="btn btn-sm btn-primary">
                        Produkt anlegen &amp; verknüpfen
                    </button>
                </div>
            </div>

            <div id="link-create-success" style="display:none;padding:20px;text-align:center">
                <div style="font-size:32px;margin-bottom:8px">✓</div>
                <div style="font-weight:600;margin-bottom:4px" id="link-success-name"></div>
                <div style="font-size:13px;color:var(--c-muted)" id="link-success-artnr"></div>
                <button type="button" onclick="closeLinkModal();location.reload()"
                        class="btn btn-sm btn-primary" style="margin-top:16px">
                    Seite neu laden
                </button>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
const LINK_WAWI_SEARCH = '{{ route("admin.reconcile.products.wawi-search") }}';
const LINK_NEW_PRODUCT = '{{ route("admin.reconcile.products.create-product") }}';
const LINK_FORM_DATA   = '{{ route("admin.reconcile.products.new-product-form-data") }}';
const LINK_CSRF        = '{{ csrf_token() }}';

let _linkNinoxData    = {};
let _linkFormLoaded   = false;
let _linkGebindeCount = 0;
let _linkGebindeOn    = false;
let _linkSelWawiName  = '';
let _linkHasLocal     = false;

// ── Open / close ────────────────────────────────────────────────────────────

function openLinkModal(ninoxId, ninoxData, hasLocal) {
    _linkNinoxData   = ninoxData || {};
    _linkHasLocal    = hasLocal;
    _linkSelWawiName = '';

    document.getElementById('link-ninox-id').value  = ninoxId;
    document.getElementById('link-wawi-id').value   = '';
    document.getElementById('link-submit-btn').disabled = true;
    document.getElementById('link-selected').style.display       = 'none';
    document.getElementById('link-search-results').innerHTML     = '';
    document.getElementById('link-search-input').value           = '';
    document.getElementById('link-create-success').style.display = 'none';
    document.getElementById('link-create-form').style.display    = 'block';
    document.getElementById('link-np-error').style.display       = 'none';

    // Source info header
    const info = document.getElementById('link-ninox-info');
    let html = '<strong>' + escL(ninoxData.artikelname || '—') + '</strong>';
    if (ninoxData.artnummer)
        html += ' <span style="color:var(--c-muted)">· Art.-Nr. ' + escL(ninoxData.artnummer) + '</span>';
    if (ninoxData.ean)
        html += ' <span style="color:var(--c-muted)">· EAN: ' + escL(ninoxData.ean) + '</span>';
    if (parseFloat(ninoxData.vk_brutto_markt) > 0)
        html += ' <span style="color:var(--c-muted)">· '
             + parseFloat(ninoxData.vk_brutto_markt).toFixed(2).replace('.', ',')
             + ' € brutto</span>';
    info.innerHTML = html;

    // Gebinde filter
    const gMatch = (ninoxData.artikelname || '').match(/(\d+)\s*[xX]\s*[\d,.]+/);
    _linkGebindeCount = gMatch ? parseInt(gMatch[1], 10) : 0;
    _linkGebindeOn    = _linkGebindeCount > 0;
    document.getElementById('link-gebinde-bar').style.display = _linkGebindeCount > 0 ? 'block' : 'none';
    updateLinkGebindeBtn();

    // Show/hide "Neu anlegen" tab: only relevant when no local product exists yet
    document.getElementById('link-tab-btn-create').style.display = hasLocal ? 'none' : '';

    switchLinkTab('search');
    document.getElementById('link-modal').style.display = 'flex';
    setTimeout(function () { document.getElementById('link-search-input').focus(); }, 80);

    if (!_linkFormLoaded) loadLinkFormData();
}

function closeLinkModal() {
    document.getElementById('link-modal').style.display = 'none';
}

// ── Tabs ─────────────────────────────────────────────────────────────────────

function switchLinkTab(name) {
    ['search', 'create'].forEach(function (t) {
        document.getElementById('link-tab-' + t).style.display = t === name ? 'block' : 'none';
        const btn = document.getElementById('link-tab-btn-' + t);
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
    if (name === 'create') prefillLinkNewForm();
}

// ── Form data (tax rates + warengruppen) ─────────────────────────────────────

async function loadLinkFormData() {
    try {
        const res  = await fetch(LINK_FORM_DATA);
        const data = await res.json();

        const taxSel = document.getElementById('link-np-tax');
        taxSel.innerHTML = '';
        data.tax_rates.forEach(function (t) {
            const o = document.createElement('option');
            o.value       = t.id;
            o.textContent = t.name;
            if (t.rate_basis_points === 1900) o.selected = true;
            taxSel.appendChild(o);
        });

        const wgSel = document.getElementById('link-np-wg');
        data.warengruppen.forEach(function (w) {
            const o = document.createElement('option');
            o.value       = w.id;
            o.textContent = w.name;
            wgSel.appendChild(o);
        });

        _linkFormLoaded = true;
    } catch (e) { /* fail silently */ }
}

function prefillLinkNewForm() {
    const d = _linkNinoxData;
    document.getElementById('link-np-name').value  = d.artikelname || '';
    document.getElementById('link-np-artnr').value = d.artnummer   || '';
    document.getElementById('link-np-ean').value   = d.ean         || '';
    document.getElementById('link-np-price').value = parseFloat(d.vk_brutto_markt) > 0
        ? parseFloat(d.vk_brutto_markt).toFixed(2) : '';
}

// ── Gebinde filter ───────────────────────────────────────────────────────────

function updateLinkGebindeBtn() {
    const btn = document.getElementById('link-gebinde-btn');
    if (!btn) return;
    if (_linkGebindeOn) {
        btn.textContent    = 'Nur ' + _linkGebindeCount + '× (Filter aktiv) ✕';
        btn.style.color       = 'var(--c-primary)';
        btn.style.borderColor = 'var(--c-primary)';
    } else {
        btn.textContent    = _linkGebindeCount + '× Filter (deaktiviert)';
        btn.style.color       = 'var(--c-muted)';
        btn.style.borderColor = 'var(--c-border)';
    }
}

function toggleLinkGebinde() {
    _linkGebindeOn = !_linkGebindeOn;
    updateLinkGebindeBtn();
    const q = document.getElementById('link-search-input').value.trim();
    if (q.length >= 2) fetchLinkWawi(q);
}

// ── WaWi search ──────────────────────────────────────────────────────────────

async function fetchLinkWawi(q) {
    const el = document.getElementById('link-search-results');
    el.innerHTML = '<div style="padding:12px;color:var(--c-muted);font-size:13px">Suche…</div>';
    try {
        let url = LINK_WAWI_SEARCH + '?q=' + encodeURIComponent(q);
        if (_linkGebindeOn && _linkGebindeCount > 0) url += '&gebinde_count=' + _linkGebindeCount;
        const res  = await fetch(url);
        const rows = await res.json();
        if (!rows.length) {
            let msg = '<div style="padding:12px;color:var(--c-muted);font-size:13px">Keine Treffer';
            if (_linkGebindeOn && _linkGebindeCount > 0) {
                msg += ' mit Filter <strong>' + _linkGebindeCount + '×</strong>'
                    + ' — <a href="#" onclick="toggleLinkGebinde();return false" style="color:var(--c-primary)">Filter deaktivieren?</a>';
            } else {
                msg += ' — <a href="#" onclick="switchLinkTab(\'create\');return false" style="color:var(--c-primary)">Artikel neu anlegen?</a>';
            }
            el.innerHTML = msg + '</div>';
            return;
        }
        el.innerHTML = rows.map(function (r) {
            const name  = escL(r.cName   || '—');
            const artnr = escL(r.cArtNr  || '');
            const ean   = escL(r.cBarcode || '');
            const price = r.fVKNetto || 0;
            return '<div class="link-wawi-row"'
                 + ' data-id="'    + r.kArtikel + '"'
                 + ' data-name="'  + (r.cName   || '').replace(/"/g, '&quot;') + '"'
                 + ' data-artnr="' + (r.cArtNr  || '') + '"'
                 + ' data-ean="'   + (r.cBarcode || '') + '"'
                 + ' data-price="' + price + '"'
                 + ' style="padding:10px 12px;border-bottom:1px solid var(--c-border);cursor:pointer;font-size:13px"'
                 + ' onmouseenter="this.style.background=\'var(--c-bg)\'"'
                 + ' onmouseleave="this.style.background=\'\'"'
                 + ' onclick="selectLinkWawi(this)">'
                 + '<strong>' + name + '</strong>'
                 + (artnr ? '<span style="color:var(--c-muted);margin-left:8px">Nr: ' + artnr + '</span>' : '')
                 + (ean   ? '<span style="color:var(--c-muted);margin-left:8px">EAN: ' + ean + '</span>' : '')
                 + (parseFloat(price) > 0
                     ? '<span style="float:right;color:var(--c-muted)">'
                         + parseFloat(price).toFixed(2).replace('.', ',') + ' € netto</span>'
                     : '')
                 + '</div>';
        }).join('');
    } catch (e) {
        el.innerHTML = '<div style="padding:12px;color:var(--c-danger);font-size:13px">Fehler bei der Suche</div>';
    }
}

function selectLinkWawi(el) {
    document.getElementById('link-wawi-id').value = el.dataset.id;
    document.getElementById('link-submit-btn').disabled = false;
    _linkSelWawiName = el.dataset.name || '';

    const sel = document.getElementById('link-selected');
    sel.style.display = 'block';
    let html = '<strong>Gewählt:</strong> ' + escL(el.dataset.name);
    if (el.dataset.artnr) html += ' <span style="color:var(--c-muted)">· Nr: ' + escL(el.dataset.artnr) + '</span>';
    if (el.dataset.ean)   html += ' <span style="color:var(--c-muted)">· EAN: ' + escL(el.dataset.ean) + '</span>';
    if (parseFloat(el.dataset.price) > 0)
        html += ' <span style="color:var(--c-muted)">· '
             + parseFloat(el.dataset.price).toFixed(2).replace('.', ',') + ' € netto</span>';
    sel.innerHTML = html;

    document.querySelectorAll('.link-wawi-row').forEach(function (r) {
        r.style.background = r === el ? 'color-mix(in srgb,var(--c-primary) 12%,var(--c-surface))' : '';
    });
}

// ── Confirm (WaWi link) ──────────────────────────────────────────────────────

async function submitLinkConfirm(event) {
    event.preventDefault();
    const ninoxId = document.getElementById('link-ninox-id').value;
    const wawiId  = document.getElementById('link-wawi-id').value;
    const btn     = document.getElementById('link-submit-btn');

    btn.disabled    = true;
    btn.textContent = '…';

    try {
        const body = { ninox_id: ninoxId };
        if (wawiId) body.wawi_id = wawiId;

        const res  = await fetch('{{ route("admin.reconcile.products.confirm") }}', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': LINK_CSRF,
                'Accept':       'application/json',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json();

        if (data.success) {
            closeLinkModal();
            updateLinkBadges(ninoxId, true, !!wawiId);
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

// ── Create new product ───────────────────────────────────────────────────────

async function submitLinkNewProduct() {
    const ninoxId = document.getElementById('link-ninox-id').value;
    const name    = document.getElementById('link-np-name').value.trim();
    const artnr   = document.getElementById('link-np-artnr').value.trim();
    const ean     = document.getElementById('link-np-ean').value.trim();
    const price   = document.getElementById('link-np-price').value.trim();
    const taxId   = document.getElementById('link-np-tax').value;
    const wgId    = document.getElementById('link-np-wg').value;

    const errEl = document.getElementById('link-np-error');
    errEl.style.display = 'none';

    if (!name || !artnr || !price) {
        errEl.textContent   = 'Bitte Produktname, Artikelnummer und Bruttopreis ausfüllen.';
        errEl.style.display = 'block';
        return;
    }

    const btn = document.getElementById('link-np-submit');
    btn.disabled    = true;
    btn.textContent = '…';

    try {
        const res  = await fetch(LINK_NEW_PRODUCT, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': LINK_CSRF },
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
            document.getElementById('link-create-form').style.display    = 'none';
            document.getElementById('link-create-success').style.display = 'block';
            document.getElementById('link-success-name').textContent  = data.produktname;
            document.getElementById('link-success-artnr').textContent = 'Art.-Nr. ' + data.artikelnummer;
        } else {
            errEl.textContent   = data.error || 'Unbekannter Fehler.';
            errEl.style.display = 'block';
            btn.disabled    = false;
            btn.textContent = 'Produkt anlegen & verknüpfen';
        }
    } catch (e) {
        errEl.textContent   = 'Netzwerkfehler: ' + e.message;
        errEl.style.display = 'block';
        btn.disabled    = false;
        btn.textContent = 'Produkt anlegen & verknüpfen';
    }
}

// ── DOM badge update after confirm ──────────────────────────────────────────

function updateLinkBadges(ninoxId, ninoxLinked, wawiLinked) {
    const row = document.querySelector('tr[data-ninox-id="' + ninoxId + '"]');
    if (!row) return;
    const cell = row.querySelector('.badges-cell');
    if (!cell) return;

    // The span/a wrapper contains 3 badge spans: S2 [0], NX [1], WW [2]
    const wrapper = cell.querySelector('[role="button"]') || cell.querySelector('a');
    if (!wrapper) return;
    const spans = wrapper.querySelectorAll('span[title]');

    const greenStyle = 'display:inline-block;width:24px;font-size:10px;font-weight:700;'
        + 'text-align:center;border-radius:3px;padding:1px 0;'
        + 'background:#dcfce7;color:#166534;border:1px solid #86efac';

    if (ninoxLinked && spans[1]) spans[1].style.cssText = greenStyle;
    if (wawiLinked  && spans[2]) spans[2].style.cssText = greenStyle;
}

// ── Quick-create product ─────────────────────────────────────────────────────

const QUICK_CREATE_URL  = '{{ route("admin.catalog.quick-create") }}';
const QUICK_CREATE_CSRF = '{{ csrf_token() }}';

async function quickCreateProduct(btn, ninoxId, wawiId) {
    btn.disabled    = true;
    btn.textContent = '…';

    const body = {};
    if (ninoxId) body.ninox_id = ninoxId;
    if (wawiId)  body.wawi_id  = parseInt(wawiId);

    try {
        const res  = await fetch(QUICK_CREATE_URL, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': QUICK_CREATE_CSRF,
                'Accept':       'application/json',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json();

        if (data.success) {
            // Replace button with Bearbeiten link
            const editLink = document.createElement('a');
            editLink.href        = '/admin/products/' + data.product_id + '/edit';
            editLink.className   = 'btn btn-outline btn-xs';
            editLink.style.marginRight = '3px';
            editLink.textContent = 'Bearbeiten';
            btn.parentNode.insertBefore(editLink, btn);
            btn.remove();

            // Update S2 badge to green
            const row     = editLink.closest('tr');
            const cell    = row.querySelector('.badges-cell');
            const wrapper = cell && (cell.querySelector('[role="button"]') || cell.querySelector('a'));
            const spans   = wrapper ? wrapper.querySelectorAll('span[title]') : [];
            if (spans[0]) {
                spans[0].style.cssText = 'display:inline-block;width:24px;font-size:10px;font-weight:700;'
                    + 'text-align:center;border-radius:3px;padding:1px 0;'
                    + 'background:#dcfce7;color:#166534;border:1px solid #86efac';
            }
        } else {
            alert(data.error || 'Fehler beim Anlegen.');
            btn.disabled    = false;
            btn.textContent = 'In S2 anlegen';
        }
    } catch (e) {
        alert('Netzwerkfehler: ' + e.message);
        btn.disabled    = false;
        btn.textContent = 'In S2 anlegen';
    }
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function escL(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Event listeners ──────────────────────────────────────────────────────────

let _linkSearchTimer = null;
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('link-search-input').addEventListener('input', function () {
        clearTimeout(_linkSearchTimer);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('link-search-results').innerHTML = ''; return; }
        _linkSearchTimer = setTimeout(function () { fetchLinkWawi(q); }, 280);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLinkModal();
    });

    document.getElementById('link-modal').addEventListener('click', function (e) {
        if (e.target === this) closeLinkModal();
    });
});
</script>
@endpush
@endsection
