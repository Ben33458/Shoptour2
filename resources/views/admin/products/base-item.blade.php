@extends('admin.layout')

@section('title', 'Produkt: ' . $product->artikelnummer)

@section('actions')
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline btn-sm">← Produkte</a>
    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-primary btn-sm">Bearbeiten</a>
    @if($product->is_base_item)
        <a href="{{ route('admin.lmiv.edit', $product) }}" class="btn btn-outline btn-sm">LMIV bearbeiten</a>
    @endif
@endsection

@section('content')

{{-- ── Product info card ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">📦 Produktdaten</div>
    <div class="card-body">
        <table style="border-collapse:collapse;font-size:.9em">
            <tr>
                <td style="padding:3px 20px 3px 0;color:var(--c-muted)">Artikelnummer</td>
                <td><code>{{ $product->artikelnummer }}</code></td>
            </tr>
            <tr>
                <td style="padding:3px 20px 3px 0;color:var(--c-muted)">Produktname</td>
                <td>{{ $product->produktname }}</td>
            </tr>
            <tr>
                <td style="padding:3px 20px 3px 0;color:var(--c-muted)">Aktiv</td>
                <td>{{ $product->active ? 'Ja' : 'Nein' }}</td>
            </tr>
            <tr>
                <td style="padding:3px 20px 3px 0;color:var(--c-muted)">Basis-Artikel</td>
                <td>
                    @if($product->is_base_item)
                        <span class="badge badge-success">Ja</span>
                    @else
                        <span style="color:var(--c-muted)">Nein</span>
                    @endif
                </td>
            </tr>
            @if($product->base_item_product_id)
                <tr>
                    <td style="padding:3px 20px 3px 0;color:var(--c-muted)">Basis-Artikel verknüpft</td>
                    <td>
                        @php $bi = $product->baseItem @endphp
                        @if($bi)
                            <a href="{{ route('admin.products.show', $bi) }}">
                                {{ $bi->artikelnummer }} – {{ $bi->produktname }}
                            </a>
                        @else
                            ID {{ $product->base_item_product_id }} (nicht gefunden)
                        @endif
                    </td>
                </tr>
            @endif
        </table>

        {{-- Toggle base-item status --}}
        <div style="margin-top:16px">
            <form method="POST" action="{{ route('admin.products.mark-base-item', $product) }}">
                @csrf
                @if($product->is_base_item)
                    <input type="hidden" name="is_base_item" value="0">
                    <button type="submit" class="btn btn-outline btn-sm"
                            onclick="return confirm('Basis-Artikel-Markierung entfernen?')">
                        Basis-Artikel-Markierung entfernen
                    </button>
                @else
                    <input type="hidden" name="is_base_item" value="1">
                    <button type="submit" class="btn btn-primary btn-sm">
                        Als Basis-Artikel markieren
                    </button>
                @endif
            </form>
        </div>
    </div>
</div>

{{-- ── LMIV version history ── --}}
@if($product->is_base_item)
<div class="card">
    <div class="card-header">🏷️ LMIV-Versionen</div>
    <div class="card-body" style="padding:0">
        @if($lmivVersions->isEmpty())
            <p style="padding:16px;color:var(--c-muted)">Noch keine LMIV-Versionen vorhanden.</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>EAN</th>
                        <th>Status</th>
                        <th>Gültig von</th>
                        <th>Gültig bis</th>
                        <th>Grund</th>
                        <th>Erstellt von</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($lmivVersions as $ver)
                    <tr>
                        <td><strong>v{{ $ver->version_number }}</strong></td>
                        <td><code>{{ $ver->ean ?? '–' }}</code></td>
                        <td>
                            @php
                                $badgeClass = match($ver->status) {
                                    'active'   => 'badge-success',
                                    'draft'    => 'badge-warning',
                                    'archived' => '',
                                    default    => '',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $ver->statusLabel() }}</span>
                        </td>
                        <td>{{ $ver->effective_from?->format('d.m.Y H:i') ?? '–' }}</td>
                        <td>{{ $ver->effective_to?->format('d.m.Y H:i') ?? '–' }}</td>
                        <td style="font-size:.85em">{{ $ver->change_reason ?? '–' }}</td>
                        <td style="font-size:.85em">{{ $ver->createdBy?->name ?? 'System' }}</td>
                        <td>
                            <a href="{{ route('admin.lmiv.edit', $product) }}"
                               class="btn btn-outline btn-sm">
                                Bearbeiten
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endif

@endsection
