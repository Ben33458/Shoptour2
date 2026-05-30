@extends('admin.layout')

@section('title', 'Letzte Änderungen')

@section('actions')
    {{-- Stat pills in the header bar --}}
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span style="font-size:.78rem;color:var(--c-muted);padding-right:4px;">30 Tage:</span>
        <span style="display:flex;align-items:center;gap:5px;background:var(--c-surface);border:1px solid var(--c-border);border-radius:20px;padding:3px 10px 3px 8px;font-size:.78rem;">
            <span style="width:8px;height:8px;border-radius:50%;background:var(--c-primary,#3b82f6);display:inline-block;flex-shrink:0"></span>
            <strong>{{ $stats['total'] }}</strong>&nbsp;gesamt
        </span>
        @if($stats['bild'] > 0)
        <span style="display:flex;align-items:center;gap:5px;background:var(--c-surface);border:1px solid var(--c-border);border-radius:20px;padding:3px 10px 3px 8px;font-size:.78rem;">
            <span style="width:8px;height:8px;border-radius:50%;background:#06b6d4;display:inline-block;flex-shrink:0"></span>
            <strong>{{ $stats['bild'] }}</strong>&nbsp;Bilder
        </span>
        @endif
        @if($stats['lmiv'] > 0)
        <span style="display:flex;align-items:center;gap:5px;background:var(--c-surface);border:1px solid var(--c-border);border-radius:20px;padding:3px 10px 3px 8px;font-size:.78rem;">
            <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block;flex-shrink:0"></span>
            <strong>{{ $stats['lmiv'] }}</strong>&nbsp;LMIV
        </span>
        @endif
        @if($stats['stammdaten'] > 0)
        <span style="display:flex;align-items:center;gap:5px;background:var(--c-surface);border:1px solid var(--c-border);border-radius:20px;padding:3px 10px 3px 8px;font-size:.78rem;">
            <span style="width:8px;height:8px;border-radius:50%;background:#94a3b8;display:inline-block;flex-shrink:0"></span>
            <strong>{{ $stats['stammdaten'] }}</strong>&nbsp;Stammdaten
        </span>
        @endif
    </div>
@endsection

@section('content')

<style>
.rc-thumb {
    width: 44px;
    height: 44px;
    object-fit: contain;
    border-radius: 6px;
    background: var(--c-bg, #f8fafc);
    border: 1px solid var(--c-border, #e2e8f0);
    display: block;
    flex-shrink: 0;
}
.rc-nothumb {
    width: 44px;
    height: 44px;
    border-radius: 6px;
    background: var(--c-bg, #f8fafc);
    border: 1px solid var(--c-border, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--c-muted, #94a3b8);
    font-size: .6rem;
    text-align: center;
    line-height: 1.2;
    flex-shrink: 0;
}
.rc-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 9px;
    border-radius: 12px;
    font-size: .72rem;
    font-weight: 600;
    white-space: nowrap;
}
.rc-badge-bild       { background: #e0f2fe; color: #0369a1; }
.rc-badge-lmiv       { background: #dcfce7; color: #15803d; }
.rc-badge-stammdaten { background: #f1f5f9; color: #64748b; }
.rc-badge-bild .rc-dot       { background: #06b6d4; }
.rc-badge-lmiv .rc-dot       { background: #22c55e; }
.rc-badge-stammdaten .rc-dot { background: #94a3b8; }
[data-theme="dark"] .rc-badge-bild       { background: #0c4a6e; color: #7dd3fc; }
[data-theme="dark"] .rc-badge-lmiv       { background: #14532d; color: #86efac; }
[data-theme="dark"] .rc-badge-stammdaten { background: #1e293b; color: #94a3b8; }
.rc-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}
.rc-time {
    font-size: .8rem;
    color: var(--c-text, #1e293b);
    white-space: nowrap;
}
.rc-time-rel {
    font-size: .72rem;
    color: var(--c-muted, #94a3b8);
    white-space: nowrap;
}
</style>

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Letzte Änderungen</span>
        <span style="font-size:.8rem;color:var(--c-muted)">{{ $allEvents->count() }} Einträge · letzten 30 Tage</span>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width:52px"></th>
                <th>Produkt</th>
                <th style="width:160px">Art der Änderung</th>
                <th style="width:140px">Zeitpunkt</th>
                <th style="width:90px"></th>
            </tr>
        </thead>
        <tbody>
        @forelse($allEvents as $event)
            @php
                $thumb  = $thumbs->get($event->product_id);
                $imgUrl = $thumb ? \Illuminate\Support\Facades\Storage::disk('public')->url($thumb->path) : null;
                $changedAt = \Carbon\Carbon::parse($event->changed_at);
            @endphp
            <tr>
                {{-- Thumbnail --}}
                <td style="padding:8px 8px 8px 16px;vertical-align:middle">
                    @if($imgUrl)
                        <img src="{{ $imgUrl }}" class="rc-thumb" alt="{{ $event->produktname }}">
                    @else
                        <div class="rc-nothumb">kein<br>Bild</div>
                    @endif
                </td>

                {{-- Product name + Artikelnummer --}}
                <td style="vertical-align:middle">
                    <div style="font-weight:500;line-height:1.3">{{ $event->produktname }}</div>
                    <div style="font-size:.78rem;color:var(--c-muted)">Art.&nbsp;{{ $event->artikelnummer }}</div>
                </td>

                {{-- Change type badge --}}
                <td style="vertical-align:middle">
                    <span class="rc-badge rc-badge-{{ $event->type }}">
                        <span class="rc-dot"></span>
                        {{ $event->type_label }}
                    </span>
                </td>

                {{-- Timestamp --}}
                <td style="vertical-align:middle">
                    <div class="rc-time">{{ $changedAt->format('d.m.Y H:i') }}</div>
                    <div class="rc-time-rel">{{ $changedAt->diffForHumans() }}</div>
                </td>

                {{-- Edit link --}}
                <td style="vertical-align:middle;text-align:right;padding-right:16px">
                    <a href="{{ route('admin.products.edit', ['product' => $event->product_id]) }}"
                       class="btn btn-primary btn-sm">
                        Bearbeiten
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="text-align:center;color:var(--c-muted);padding:40px">
                    Keine Änderungen in den letzten 30 Tagen.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

@endsection
