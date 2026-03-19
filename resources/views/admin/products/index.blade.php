@extends('admin.layout')

@section('title', 'Produkte')

@section('actions')
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">+ Neues Produkt</a>
    <details class="actions-dropdown">
        <summary class="btn btn-outline btn-sm">Aktionen ▾</summary>
        <div class="actions-menu">
            <a href="{{ route('admin.imports.products') }}">CSV importieren</a>
            <hr class="actions-menu-divider">
            <a href="{{ route('admin.lmiv.index') }}">LMIV verwalten</a>
            <a href="{{ route('admin.imports.lmiv') }}">LMIV importieren</a>
        </div>
    </details>
@endsection

@section('content')

{{-- ── Filter bar ── --}}
<form method="GET" action="{{ route('admin.products.index') }}" class="card" style="margin-bottom:16px;padding:14px 16px">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="text" name="search" value="{{ $search }}"
               class="form-control" style="max-width:280px"
               placeholder="Artikelnummer / Name …">
        <label style="display:flex;align-items:center;gap:6px;font-size:.9em">
            <input type="checkbox" name="only_base" value="1" {{ $onlyBase ? 'checked' : '' }}>
            Nur Basis-Artikel
        </label>
        <button type="submit" class="btn btn-primary btn-sm">Suchen</button>
        @if($search || $onlyBase)
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline btn-sm">Reset</a>
        @endif
    </div>
</form>

{{-- ── Products table ── --}}
<div class="card">
    <div class="card-header">
        Produkte
        <span class="text-muted" style="font-size:.85em">({{ $products->total() }} gesamt)</span>
        <span style="font-size:.8rem;color:var(--c-muted);margin-left:8px">— Zelle klicken zum Bearbeiten</span>
    </div>
    <div class="card-body" style="padding:0">
        <table class="table">
            <thead>
                <tr>
                    <th>Artikelnummer</th>
                    <th>Produktname</th>
                    <th>Netto-EP</th>
                    <th>Verfügbarkeit</th>
                    <th style="text-align:center">Basis</th>
                    <th style="text-align:center">LMIV</th>
                    <th style="text-align:center">Aktiv</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($products as $product)
                @php
                    $availOptions = collect([
                        ['value' => 'available',    'label' => 'Verfügbar'],
                        ['value' => 'preorder',     'label' => 'Vorbestellung'],
                        ['value' => 'out_of_stock', 'label' => 'Ausverkauft'],
                        ['value' => 'discontinued', 'label' => 'Abgekündigt'],
                        ['value' => 'stock_based',  'label' => 'Lagerbasiert'],
                    ])->toJson();
                    $netEur = number_format($product->base_price_net_milli / 1_000_000, 2, '.', '');
                @endphp
                <tr data-ie-url="{{ route('admin.products.update', $product) }}">
                    <td><code>{{ $product->artikelnummer }}</code></td>
                    <td data-ie-field="produktname"
                        data-ie-type="text"
                        data-ie-value="{{ $product->produktname }}">{{ $product->produktname }}</td>
                    <td data-ie-field="base_price_net_eur"
                        data-ie-type="money"
                        data-ie-value="{{ $netEur }}"></td>
                    <td data-ie-field="availability_mode"
                        data-ie-type="select"
                        data-ie-value="{{ $product->availability_mode }}"
                        data-ie-options="{{ $availOptions }}">
                        {{ match($product->availability_mode) {
                            'available'    => 'Verfügbar',
                            'preorder'     => 'Vorbestellung',
                            'out_of_stock' => 'Ausverkauft',
                            'discontinued' => 'Abgekündigt',
                            'stock_based'  => 'Lagerbasiert',
                            default        => $product->availability_mode,
                        } }}
                    </td>
                    <td style="text-align:center">
                        @if($product->is_base_item)
                            <span class="badge badge-success">Ja</span>
                        @else
                            <span style="color:var(--c-muted)">–</span>
                        @endif
                    </td>
                    <td style="text-align:center">
                        @if($product->is_base_item)
                            @if($product->activeLmivVersion)
                                <span class="badge badge-success" title="Version {{ $product->activeLmivVersion->version_number }}">
                                    v{{ $product->activeLmivVersion->version_number }}
                                </span>
                            @else
                                <span class="badge badge-warning">Leer</span>
                            @endif
                        @else
                            <span style="color:var(--c-muted)">–</span>
                        @endif
                    </td>
                    <td style="text-align:center"
                        data-ie-field="active"
                        data-ie-type="checkbox"
                        data-ie-value="{{ $product->active ? '1' : '0' }}"
                        title="Klick zum Umschalten">
                        @if($product->active)
                            <span style="color:var(--c-success)">✓</span>
                        @else
                            <span style="color:var(--c-danger)">✗</span>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.products.edit', $product) }}"
                           class="btn btn-primary btn-sm">Bearbeiten</a>
                        <a href="{{ route('admin.products.show', $product) }}"
                           class="btn btn-outline btn-sm">Details</a>
                        @if($product->is_base_item)
                            <a href="{{ route('admin.lmiv.edit', $product) }}"
                               class="btn btn-outline btn-sm">LMIV</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center;color:var(--c-muted);padding:24px">
                        Keine Produkte gefunden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Pagination --}}
<div style="margin-top:12px">
    {{ $products->links() }}
</div>

@endsection

@push('scripts')
<script src="{{ asset('admin/inline-edit.js') }}" defer></script>
@endpush
