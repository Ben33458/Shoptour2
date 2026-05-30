@extends('admin.layout')

@section('title', 'Anlieferung #' . $goodsReceipt->id)

@section('actions')
    <a href="{{ route('admin.einkauf.anlieferungen.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
    @if($goodsReceipt->purchaseOrder)
        <a href="{{ route('admin.einkauf.show', $goodsReceipt->purchaseOrder) }}" class="btn btn-outline btn-sm">
            Bestellung {{ $goodsReceipt->purchaseOrder->po_number }} →
        </a>
    @endif
@endsection

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

{{-- ── Header ── --}}
<div class="meta-grid" style="margin-bottom:20px">
    <div class="meta-item">
        <label>Lieferant</label>
        <div class="val">{{ $goodsReceipt->supplier?->name ?? '—' }}</div>
    </div>
    <div class="meta-item">
        <label>Anlieferdatum</label>
        <div class="val">{{ $goodsReceipt->arrived_at?->format('d.m.Y') ?? '—' }}</div>
    </div>
    <div class="meta-item">
        <label>Status</label>
        <div class="val">
            <span class="badge" style="{{ $statusColors[$goodsReceipt->status] ?? '' }}">
                {{ $statusLabels[$goodsReceipt->status] ?? $goodsReceipt->status }}
            </span>
        </div>
    </div>
    <div class="meta-item">
        <label>Lieferschein-Nr</label>
        <div class="val">{{ $goodsReceipt->lieferschein_nr ?? '—' }}</div>
    </div>
    <div class="meta-item">
        <label>Lager</label>
        <div class="val">{{ $goodsReceipt->warehouse?->name ?? '—' }}</div>
    </div>
    <div class="meta-item">
        <label>Ninox-ID</label>
        <div class="val">
            @if($goodsReceipt->ninox_bestellung_id)
                <code>#{{ $goodsReceipt->ninox_bestellung_id }}</code>
                @if($goodsReceipt->ninox_pushed_at)
                    <span class="text-muted" style="font-size:11px;display:block">
                        Sync: {{ $goodsReceipt->ninox_pushed_at->format('d.m.Y H:i') }}
                    </span>
                @endif
            @else
                <span class="text-muted">—</span>
            @endif
        </div>
    </div>
    @if($goodsReceipt->gebucht_at)
    <div class="meta-item">
        <label>Gebucht am</label>
        <div class="val">{{ $goodsReceipt->gebucht_at->format('d.m.Y H:i') }}</div>
    </div>
    @endif
</div>

@if($goodsReceipt->notiz)
<div style="margin-bottom:16px;font-size:.85rem;color:#1e40af;background:#eff6ff;
            border-left:3px solid #3b82f6;padding:8px 12px;border-radius:0 6px 6px 0;
            white-space:pre-wrap;word-break:break-word">📋 {{ $goodsReceipt->notiz }}</div>
@endif

{{-- ── Verknüpfte Einkaufsbestellung ── --}}
@if($goodsReceipt->purchaseOrder)
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        Einkaufsbestellung
        <a href="{{ route('admin.einkauf.show', $goodsReceipt->purchaseOrder) }}"
           class="btn btn-outline btn-sm" style="float:right">
            {{ $goodsReceipt->purchaseOrder->po_number }} →
        </a>
    </div>
    <div class="card-body" style="font-size:13px;display:flex;gap:24px;flex-wrap:wrap">
        <div>
            <span class="text-muted">Lieferant:</span>
            {{ $goodsReceipt->purchaseOrder->supplier?->name ?? '—' }}
        </div>
        <div>
            <span class="text-muted">Bestellt am:</span>
            {{ $goodsReceipt->purchaseOrder->ordered_at?->format('d.m.Y') ?? '—' }}
        </div>
        <div>
            <span class="text-muted">Positionen:</span>
            {{ $goodsReceipt->purchaseOrder->items->count() }}
        </div>
    </div>
</div>
@endif

{{-- ── Positionen ── --}}
@if($goodsReceipt->items->isNotEmpty())
<div class="card" style="margin-bottom:16px">
    <div class="card-header">Positionen</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Artikel-Nr.</th>
                    <th>Produkt</th>
                    <th class="text-right">Bestellt</th>
                    <th class="text-right">Geliefert</th>
                    <th>Abweichung</th>
                </tr>
            </thead>
            <tbody>
            @foreach($goodsReceipt->items as $item)
                <tr>
                    <td><code>{{ $item->product?->artikelnummer ?? '—' }}</code></td>
                    <td>{{ $item->product?->produktname ?? '—' }}</td>
                    <td class="text-right">{{ number_format($item->bestellte_menge, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($item->gelieferte_menge, 0, ',', '.') }}</td>
                    <td>
                        @if($item->hatAbweichung())
                            <span style="color:var(--c-danger,#dc2626);font-size:12px">
                                {{ $item->abweichungs_grund }}
                                @if($item->abweichungs_notiz)
                                    — {{ $item->abweichungs_notiz }}
                                @endif
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Lieferschein-Scan ── --}}
@if($goodsReceipt->docScanUpload)
<div class="card" style="margin-bottom:16px">
    <div class="card-header">📄 Lieferschein-Scan</div>
    <div class="card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        @php $scan = $goodsReceipt->docScanUpload; @endphp
        @if(in_array(strtolower(pathinfo($scan->storage_path, PATHINFO_EXTENSION)), ['jpg','jpeg','png']))
            <a href="{{ route('admin.docscan.file', $scan) }}" target="_blank" style="flex-shrink:0">
                <img src="{{ route('admin.docscan.file', $scan) }}"
                     alt="Lieferschein"
                     style="max-height:120px;max-width:180px;border:1px solid #e5e7eb;border-radius:4px;object-fit:contain">
            </a>
        @endif
        <div style="font-size:13px">
            <div><strong>{{ $scan->original_filename }}</strong></div>
            <div class="text-muted" style="margin-top:4px">
                Hochgeladen: {{ $scan->created_at->format('d.m.Y H:i') }}
                @if($scan->intern_zugeordnet_at)
                    · Zugeordnet: {{ $scan->intern_zugeordnet_at->format('d.m.Y H:i') }}
                @endif
            </div>
        </div>
        <a href="{{ route('admin.docscan.file', $scan) }}" target="_blank"
           class="btn btn-outline btn-sm" style="margin-left:auto">
            Öffnen ↗
        </a>
    </div>
</div>
@endif

@endsection
