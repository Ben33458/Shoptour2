@extends('admin.layout')

@section('title', 'Fahrer-Uploads')

@section('content')
<div class="page-header">
    <h1>Fahrer-Uploads</h1>
    <div class="page-actions">
        <a href="http://{{ request()->getHost() }}:2283" target="_blank" class="btn btn-outline btn-sm">
            In Immich öffnen ↗
        </a>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.driver-uploads.index') }}" class="d-flex gap-2 flex-wrap align-items-end">
            <div>
                <label class="form-label mb-1" style="font-size:.8rem;">Status</label>
                <select name="immich_status" class="form-select form-select-sm" style="width:140px;">
                    <option value="" {{ !request('immich_status') && !request('alle') ? 'selected' : '' }}>Offen (Standard)</option>
                    <option value="offen"       {{ request('immich_status') === 'offen'       ? 'selected' : '' }}>Offen</option>
                    <option value="geprueft"    {{ request('immich_status') === 'geprueft'    ? 'selected' : '' }}>Geprüft</option>
                    <option value="abgerechnet" {{ request('immich_status') === 'abgerechnet' ? 'selected' : '' }}>Abgerechnet</option>
                </select>
            </div>
            <div>
                <label class="form-label mb-1" style="font-size:.8rem;">Typ</label>
                <select name="typ" class="form-select form-select-sm" style="width:160px;">
                    <option value="">Alle Typen</option>
                    <option value="delivery_note"     {{ request('typ') === 'delivery_note'     ? 'selected' : '' }}>Lieferschein</option>
                    <option value="proof_of_delivery" {{ request('typ') === 'proof_of_delivery' ? 'selected' : '' }}>Liefernachweis</option>
                    <option value="other"             {{ request('typ') === 'other'             ? 'selected' : '' }}>Sonstiges</option>
                </select>
            </div>
            <div>
                <label class="form-label mb-1" style="font-size:.8rem;">Von</label>
                <input type="date" name="von" value="{{ request('von') }}" class="form-control form-control-sm" style="width:145px;">
            </div>
            <div>
                <label class="form-label mb-1" style="font-size:.8rem;">Bis</label>
                <input type="date" name="bis" value="{{ request('bis') }}" class="form-control form-control-sm" style="width:145px;">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filtern</button>
                <a href="{{ route('admin.driver-uploads.index') }}" class="btn btn-outline btn-sm">Reset</a>
                <a href="{{ route('admin.driver-uploads.index') }}?alle=1" class="btn btn-outline btn-sm">Alle anzeigen</a>
            </div>
        </form>
    </div>
</div>

{{-- Flash messages --}}
@if(session('success'))
    <div class="alert alert-success mb-3">{{ session('success') }}</div>
@endif

{{-- Grid --}}
@if($uploads->isEmpty())
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            Keine Uploads gefunden.
        </div>
    </div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">
        @foreach($uploads as $upload)
            @php
                $customer = $customerNames[$upload->tour_stop_id] ?? null;
                $statusLabel = match($upload->immich_status) {
                    'geprueft'    => ['Geprüft',     'badge-success'],
                    'abgerechnet' => ['Abgerechnet', 'badge-primary'],
                    'offen'       => ['Offen',       'badge-warning'],
                    default       => ['Neu',         'badge-secondary'],
                };
                $typLabel = match($upload->upload_type) {
                    'delivery_note'     => 'Lieferschein',
                    'proof_of_delivery' => 'Liefernachweis',
                    default             => 'Sonstiges',
                };
            @endphp
            <div class="card"
                 id="upload-card-{{ $upload->id }}"
                 style="overflow:hidden;">

                {{-- Thumbnail --}}
                <div style="background:#f0f2f5;height:180px;overflow:hidden;position:relative;">
                    @if($upload->mime_type && str_starts_with($upload->mime_type, 'image/'))
                        <img src="{{ route('admin.driver-uploads.thumb', $upload) }}"
                             alt="Upload {{ $upload->id }}"
                             style="width:100%;height:100%;object-fit:cover;cursor:zoom-in;"
                             title="Klicken für Vollbild · 1 = Geprüft · 2 = Abgerechnet"
                             onclick="openFull({{ $upload->id }})">
                    @elseif($upload->mime_type === 'application/pdf')
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center text-muted">
                                <div style="font-size:2.5rem;">📄</div>
                                <small>PDF</small>
                            </div>
                        </div>
                    @else
                        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                            <small>Kein Vorschau</small>
                        </div>
                    @endif

                    {{-- Status badge overlay --}}
                    <span class="badge {{ $statusLabel[1] }}"
                          style="position:absolute;top:8px;right:8px;">
                        {{ $statusLabel[0] }}
                    </span>
                </div>

                {{-- Meta --}}
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="badge badge-secondary" style="font-size:.7rem;">{{ $typLabel }}</span>
                        <small class="text-muted">{{ $upload->created_at->format('d.m.Y H:i') }}</small>
                    </div>

                    @if($customer)
                        <div class="text-sm mb-1">
                            <a href="{{ route('admin.customers.show', $customer['id']) }}"
                               class="text-body fw-semibold text-decoration-none">
                                {{ $customer['name'] }}
                            </a>
                        </div>
                    @else
                        <div class="text-muted text-sm mb-1">Kein Kunde zugeordnet</div>
                    @endif

                    @if($upload->tour_stop_id)
                        <small class="text-muted">Stop #{{ $upload->tour_stop_id }}</small>
                    @endif

                    @if($upload->immich_asset_id)
                        <div class="mt-1">
                            <small class="text-muted">
                                <a href="http://{{ request()->getHost() }}:2283/photos/{{ $upload->immich_asset_id }}"
                                   target="_blank" class="text-muted" style="font-size:.7rem;">
                                    In Immich ansehen ↗
                                </a>
                            </small>
                        </div>
                    @endif
                </div>

                {{-- Action buttons --}}
                <div class="card-footer py-2 px-3 d-flex gap-2 flex-wrap">
                    <button onclick="setStatus({{ $upload->id }}, 'geprueft')"
                            class="btn btn-sm btn-outline flex-1
                                   {{ $upload->immich_status === 'geprueft' ? 'btn-success' : '' }}"
                            title="Tastenkürzel: 1">
                        ✓ Geprüft
                    </button>
                    <button onclick="setStatus({{ $upload->id }}, 'abgerechnet')"
                            class="btn btn-sm btn-primary flex-1"
                            title="Tastenkürzel: 2">
                        € Abgerechnet
                    </button>
                    @if($upload->immich_asset_id)
                    <button onclick="syncDesc({{ $upload->id }})"
                            class="btn btn-sm btn-outline"
                            title="Aktions-Links in Immich-Beschreibung aktualisieren"
                            style="flex:0 0 auto;padding:4px 8px;font-size:.75rem;">
                        ↺ Immich
                    </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $uploads->links() }}
    </div>
@endif

{{-- Lightbox overlay --}}
<div id="lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:9999;"
     onclick="closeLightbox(event)">
    <button onclick="closeLightbox(null)"
            style="position:absolute;top:16px;right:20px;background:none;border:none;color:#fff;font-size:1.6rem;cursor:pointer;z-index:10;">✕</button>
    <img id="lightbox-img" src=""
         style="max-width:90vw;max-height:75vh;position:absolute;top:50%;left:50%;transform:translate(-50%,-58%);border-radius:4px;cursor:default;"
         onclick="event.stopPropagation()">
    <div id="lightbox-actions"
         style="position:absolute;bottom:48px;left:50%;transform:translateX(-50%);display:flex;gap:12px;z-index:10;">
        <button id="lb-btn-geprueft"
                onclick="event.stopPropagation();lbSetStatus('geprueft')"
                style="padding:10px 22px;border-radius:8px;border:2px solid #22c55e;background:rgba(34,197,94,.15);
                       color:#fff;font-size:.95rem;cursor:pointer;font-weight:600;">
            ✓ Geprüft <kbd style="font-size:.7rem;opacity:.7;margin-left:4px;">1</kbd>
        </button>
        <button id="lb-btn-abgerechnet"
                onclick="event.stopPropagation();lbSetStatus('abgerechnet')"
                style="padding:10px 22px;border-radius:8px;border:2px solid #3b82f6;background:rgba(59,130,246,.2);
                       color:#fff;font-size:.95rem;cursor:pointer;font-weight:600;">
            € Abgerechnet <kbd style="font-size:.7rem;opacity:.7;margin-left:4px;">2</kbd>
        </button>
    </div>
    <div id="lightbox-label"
         style="position:absolute;bottom:18px;left:50%;transform:translateX(-50%);
                color:rgba(255,255,255,.5);font-size:.78rem;text-align:center;white-space:nowrap;">
    </div>
</div>

<script>
let _lbUploadId = null;

function setStatus(uploadId, newStatus, callback) {
    const card = document.getElementById('upload-card-' + uploadId);
    fetch(`/admin/driver-uploads/${uploadId}/status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
        },
        body: JSON.stringify({ immich_status: newStatus }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const labels  = {offen: 'Offen', geprueft: 'Geprüft', abgerechnet: 'Abgerechnet'};
            const classes = {offen: 'badge-warning', geprueft: 'badge-success', abgerechnet: 'badge-primary'};
            if (card) {
                const badge = card.querySelector('.badge.badge-warning, .badge.badge-success, .badge.badge-primary, .badge.badge-secondary');
                if (badge) {
                    badge.className = 'badge ' + (classes[newStatus] || 'badge-secondary');
                    badge.textContent = labels[newStatus] || newStatus;
                }
                card.style.outline = '2px solid #22c55e';
                setTimeout(() => { card.style.outline = ''; }, 1200);
            }
            if (callback) callback(newStatus);
        }
    })
    .catch(e => console.error('Status update failed', e));
}

function openFull(uploadId) {
    event.stopPropagation();
    _lbUploadId = uploadId;
    document.getElementById('lightbox-img').src = `/admin/driver-uploads/${uploadId}/thumb`;

    // Reflect current status on buttons
    const card  = document.getElementById('upload-card-' + uploadId);
    const badge = card?.querySelector('.badge');
    const label = document.getElementById('lightbox-label');
    if (label) label.textContent = badge ? badge.textContent.trim() : '';

    document.getElementById('lightbox').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeLightbox(e) {
    if (e && e.target !== document.getElementById('lightbox')) return;
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = '';
    _lbUploadId = null;
}

function lbSetStatus(newStatus) {
    if (!_lbUploadId) return;
    const labels = {offen: 'Offen', geprueft: 'Geprüft', abgerechnet: 'Abgerechnet'};
    setStatus(_lbUploadId, newStatus, () => {
        const label = document.getElementById('lightbox-label');
        if (label) label.textContent = '✓ ' + labels[newStatus];
        // Highlight active button
        document.getElementById('lb-btn-geprueft').style.background    = newStatus === 'geprueft'    ? 'rgba(34,197,94,.45)' : 'rgba(34,197,94,.15)';
        document.getElementById('lb-btn-abgerechnet').style.background = newStatus === 'abgerechnet' ? 'rgba(59,130,246,.45)' : 'rgba(59,130,246,.2)';
    });
}

function syncDesc(uploadId) {
    fetch(`/admin/driver-uploads/${uploadId}/sync-description`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
        },
    })
    .then(r => r.json())
    .then(data => {
        const card = document.getElementById('upload-card-' + uploadId);
        if (card) {
            card.style.outline = data.ok ? '2px solid #22c55e' : '2px solid #ef4444';
            setTimeout(() => { card.style.outline = ''; }, 1500);
        }
    })
    .catch(e => console.error('syncDesc failed', e));
}

// ── Keyboard shortcuts ─────────────────────────────────────────────────────────
// Lightbox open: G = geprüft, A = abgerechnet, Escape = close
// No lightbox: G / A still work on the last hovered card
let _hoveredUploadId = null;

document.querySelectorAll('[id^="upload-card-"]').forEach(card => {
    card.addEventListener('mouseenter', () => {
        _hoveredUploadId = parseInt(card.id.replace('upload-card-', ''), 10);
    });
});

document.addEventListener('keydown', e => {
    // Ignore if typing in an input
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;

    const lb = document.getElementById('lightbox');
    const lbOpen = lb && lb.style.display !== 'none';

    if (e.key === 'Escape' && lbOpen) {
        closeLightbox(null);
        return;
    }

    const targetId = lbOpen ? _lbUploadId : _hoveredUploadId;
    if (!targetId) return;

    if (e.key === '2') {
        e.preventDefault();
        lbOpen ? lbSetStatus('abgerechnet') : setStatus(targetId, 'abgerechnet', null);
    }
    if (e.key === '1') {
        e.preventDefault();
        lbOpen ? lbSetStatus('geprueft') : setStatus(targetId, 'geprueft', null);
    }
});
</script>
@endsection
