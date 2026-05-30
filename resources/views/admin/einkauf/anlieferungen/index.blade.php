@extends('admin.layout')

@section('title', 'Einkauf — Anlieferungen')

@section('content')

{{-- Tab-Navigation --}}
<div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid #e5e7eb">
    <a href="{{ route('admin.einkauf.index') }}"
       style="padding:9px 20px;font-size:.9rem;color:#6b7280;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px">
        Bestellungen
    </a>
    <a href="{{ route('admin.einkauf.anlieferungen.index') }}"
       style="padding:9px 20px;font-weight:600;font-size:.9rem;border-bottom:2px solid var(--c-primary);margin-bottom:-2px;color:var(--c-primary);text-decoration:none">
        Anlieferungen
    </a>
</div>

<div class="card">
    <div class="card-header flex justify-between items-center">
        <span>Anlieferungen ({{ $goodsReceipts->total() }})</span>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.einkauf.anlieferungen.index') }}"
          class="p-3 flex gap-3 flex-wrap items-end border-b" style="background:#f9fafb">
        <div>
            <label class="text-sm font-bold block mb-1">Lieferant</label>
            <select name="supplier_id" class="input" style="min-width:200px">
                <option value="">Alle</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>
                        {{ $s->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-bold block mb-1">Status</label>
            <select name="status" class="input">
                <option value="">Alle</option>
                @foreach(['angekuendigt' => 'Angekündigt', 'in_kontrolle' => 'In Kontrolle', 'gebucht' => 'Gebucht', 'abgebrochen' => 'Abgebrochen'] as $key => $label)
                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-bold block mb-1">Von</label>
            <input type="date" name="from" class="input" value="{{ request('from') }}">
        </div>
        <div>
            <label class="text-sm font-bold block mb-1">Bis</label>
            <input type="date" name="to" class="input" value="{{ request('to') }}">
        </div>
        <button type="submit" class="btn btn-outline btn-sm">Filtern</button>
        @if(request()->hasAny(['supplier_id','status','from','to']))
            <a href="{{ route('admin.einkauf.anlieferungen.index') }}" class="btn btn-outline btn-sm">× Reset</a>
        @endif
    </form>

    <div class="table-wrap">
        <table id="anlieferungen-table">
            <thead>
                <tr>
                    <th>Anlieferdatum</th>
                    <th>Lieferant</th>
                    <th>Lieferschein-Nr</th>
                    <th>Status</th>
                    <th>Ninox</th>
                    <th>Bestellung</th>
                    <th>Scan</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($goodsReceipts as $gr)
                <tr>
                    <td>{{ $gr->arrived_at?->format('d.m.Y') ?? '—' }}</td>
                    <td>{{ $gr->supplier?->name ?? '—' }}</td>
                    <td class="text-muted" style="font-size:12px">{{ $gr->lieferschein_nr ?? '—' }}</td>
                    <td>
                        @php
                            $statusColors = [
                                'angekuendigt' => 'background:#dbeafe;color:#1e40af',
                                'in_kontrolle' => 'background:#fef9c3;color:#92400e',
                                'gebucht'      => 'background:#dcfce7;color:#166534',
                                'abgebrochen'  => 'background:#fee2e2;color:#991b1b',
                            ];
                            $statusLabels = [
                                'angekuendigt' => 'Angekündigt',
                                'in_kontrolle' => 'In Kontrolle',
                                'gebucht'      => 'Gebucht',
                                'abgebrochen'  => 'Abgebrochen',
                            ];
                        @endphp
                        <span class="badge" style="{{ $statusColors[$gr->status] ?? '' }}">
                            {{ $statusLabels[$gr->status] ?? $gr->status }}
                        </span>
                    </td>
                    <td style="font-size:12px">
                        @if($gr->ninox_bestellung_id)
                            <span class="badge badge-outline" title="Ninox-ID {{ $gr->ninox_bestellung_id }}">#{{ $gr->ninox_bestellung_id }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="font-size:12px">
                        @if($gr->purchaseOrder)
                            <a href="{{ route('admin.einkauf.show', $gr->purchaseOrder) }}">
                                {{ $gr->purchaseOrder->po_number }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($gr->docScanUpload)
                            <button type="button"
                                    class="lb-trigger"
                                    data-lb-url="{{ route('admin.docscan.file', $gr->docScanUpload) }}"
                                    data-lb-caption="{{ $gr->supplier?->name ?? '' }} · {{ $gr->arrived_at?->format('d.m.Y') ?? '' }}"
                                    title="{{ $gr->docScanUpload->original_filename }}"
                                    style="font-size:18px;background:none;border:none;cursor:pointer;padding:0">📄</button>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.einkauf.anlieferungen.show', $gr) }}"
                           class="btn btn-outline btn-sm">Detail →</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted" style="padding:32px">
                        Keine Anlieferungen gefunden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($goodsReceipts->hasPages())
        <div class="p-3">{{ $goodsReceipts->links() }}</div>
    @endif
</div>

{{-- Lightbox --}}
<div id="scan-lightbox" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);
     align-items:center;justify-content:center;flex-direction:column">

    {{-- Toolbar --}}
    <div style="position:absolute;top:0;left:0;right:0;height:52px;display:flex;align-items:center;
                justify-content:space-between;padding:0 16px;background:rgba(0,0,0,.5);color:#fff">
        <span id="lb-caption" style="font-size:.9rem;opacity:.85"></span>
        <div style="display:flex;gap:8px">
            <a id="lb-open" href="#" target="_blank"
               style="color:#fff;font-size:.85rem;opacity:.7;text-decoration:none;padding:4px 10px;
                      border:1px solid rgba(255,255,255,.3);border-radius:4px">
                ↗ Öffnen
            </a>
            <button onclick="closeLightbox()"
                    style="background:none;border:none;color:#fff;font-size:24px;cursor:pointer;padding:0 4px;
                           line-height:1;opacity:.8">×</button>
        </div>
    </div>

    {{-- Image --}}
    <img id="lb-img" src="" alt=""
         style="max-width:90vw;max-height:85vh;object-fit:contain;border-radius:4px;box-shadow:0 8px 40px rgba(0,0,0,.6)">

    {{-- Nav arrows --}}
    <button id="lb-prev" onclick="lbNav(-1)"
            style="position:absolute;left:12px;top:50%;transform:translateY(-50%);
                   background:rgba(255,255,255,.15);border:none;color:#fff;font-size:32px;
                   cursor:pointer;padding:10px 14px;border-radius:6px;display:none">‹</button>
    <button id="lb-next" onclick="lbNav(+1)"
            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                   background:rgba(255,255,255,.15);border:none;color:#fff;font-size:32px;
                   cursor:pointer;padding:10px 14px;border-radius:6px;display:none">›</button>

    {{-- Counter --}}
    <div id="lb-counter"
         style="position:absolute;bottom:14px;left:50%;transform:translateX(-50%);
                color:rgba(255,255,255,.6);font-size:.8rem;display:none"></div>
</div>

@endsection

@push('scripts')
<script>
new AdminTable('anlieferungen-table', { tableKey: 'anlieferungen-table' });

// ── Lightbox ──────────────────────────────────────────────────────────────────
const lbEl      = document.getElementById('scan-lightbox');
const lbImg     = document.getElementById('lb-img');
const lbCaption = document.getElementById('lb-caption');
const lbOpen    = document.getElementById('lb-open');
const lbPrev    = document.getElementById('lb-prev');
const lbNext    = document.getElementById('lb-next');
const lbCounter = document.getElementById('lb-counter');

// Collect all .lb-trigger buttons on page load (scripts run after DOM)
const lbItems = Array.from(document.querySelectorAll('.lb-trigger')).map(btn => ({
    url:     btn.dataset.lbUrl,
    caption: btn.dataset.lbCaption,
}));
let lbIndex = 0;

// Bind click handlers
document.querySelectorAll('.lb-trigger').forEach((btn, i) => {
    btn.addEventListener('click', () => lbShow(i));
});

function lbShow(index) {
    lbIndex = index;
    const item = lbItems[index];
    lbImg.src             = item.url;
    lbCaption.textContent = item.caption;
    lbOpen.href           = item.url;
    lbEl.style.display    = 'flex';
    document.body.style.overflow = 'hidden';
    const many = lbItems.length > 1;
    lbPrev.style.display    = many ? '' : 'none';
    lbNext.style.display    = many ? '' : 'none';
    lbCounter.style.display = many ? '' : 'none';
    if (many) lbCounter.textContent = (index + 1) + ' / ' + lbItems.length;
}

function lbNav(dir) {
    lbShow((lbIndex + dir + lbItems.length) % lbItems.length);
}

function closeLightbox() {
    lbEl.style.display = 'none';
    document.body.style.overflow = '';
}

// Close on backdrop click, Escape / arrow keys
lbEl.addEventListener('click', e => { if (e.target === lbEl) closeLightbox(); });
document.addEventListener('keydown', e => {
    if (lbEl.style.display === 'none') return;
    if (e.key === 'Escape')     closeLightbox();
    if (e.key === 'ArrowLeft')  lbNav(-1);
    if (e.key === 'ArrowRight') lbNav(+1);
});
</script>
@endpush
