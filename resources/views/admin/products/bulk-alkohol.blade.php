@extends('admin.layout')

@section('title', 'Bulk-Alkohol Editor')

@section('actions')
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline btn-sm">← Produkte</a>
@endsection

@section('content')

{{-- Stats --}}
<div class="card" style="margin-bottom:16px;padding:12px 16px;display:flex;gap:24px;flex-wrap:wrap;align-items:center">
    <div style="font-size:.9em;color:var(--c-text-muted)">
        <strong>{{ number_format($filterCounts['all']) }}</strong> Produkte in alkohol. Warengruppen
    </div>
    <div style="font-size:.9em;color:var(--c-danger)">
        <strong>{{ number_format($filterCounts['missing']) }}</strong> ohne Alkoholgehalt
    </div>
    <div style="font-size:.9em;color:var(--c-success)">
        <strong>{{ number_format($filterCounts['set']) }}</strong> mit Alkoholgehalt
    </div>
</div>

{{-- Filter bar --}}
<form method="GET" action="{{ route('admin.products.bulk-alkohol') }}" class="card" style="margin-bottom:16px;padding:14px 16px">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="text" name="search" value="{{ $search }}"
               class="form-control" style="max-width:240px"
               placeholder="Artikelnummer / Produktname …">

        <select name="wg" class="form-control" style="max-width:200px">
            <option value="">Alle Warengruppen</option>
            @foreach($warengruppen as $wg)
                <option value="{{ $wg->id }}" {{ $wgFilter == $wg->id ? 'selected' : '' }}>
                    {{ $wg->name }}
                </option>
            @endforeach
        </select>

        <div style="display:flex;gap:4px">
            @foreach(['missing' => 'Fehlend ('.$filterCounts['missing'].')', 'set' => 'Gesetzt ('.$filterCounts['set'].')', 'all' => 'Alle ('.$filterCounts['all'].')'] as $val => $label)
                <a href="{{ request()->fullUrlWithQuery(['filter' => $val, 'page' => 1]) }}"
                   class="btn btn-sm {{ $filter === $val ? 'btn-primary' : 'btn-outline' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <button type="submit" class="btn btn-primary btn-sm">Suchen</button>
        @if($search || $wgFilter)
            <a href="{{ route('admin.products.bulk-alkohol') }}" class="btn btn-outline btn-sm">Reset</a>
        @endif
    </div>
</form>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:16px">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('admin.products.bulk-alkohol.update') }}">
    @csrf

    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span>{{ $products->total() }} Produkte{{ $products->total() !== $filterCounts['all'] ? ' (gefiltert)' : '' }}</span>
            <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:110px">Art.-Nr.</th>
                        <th>Produktname</th>
                        <th style="width:140px">Warengruppe</th>
                        <th style="width:130px">Alkohol % vol.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td style="font-family:monospace;font-size:.85em">{{ $product->artikelnummer }}</td>
                            <td>
                                <a href="{{ route('admin.products.edit', $product->id) }}" target="_blank"
                                   style="color:inherit;text-decoration:none" title="Produkt bearbeiten">
                                    {{ $product->produktname }}
                                </a>
                            </td>
                            <td style="font-size:.85em;color:var(--c-text-muted)">
                                {{ $product->warengruppe?->name ?? '–' }}
                            </td>
                            <td>
                                <input type="number"
                                       name="alkohol[{{ $product->id }}]"
                                       value="{{ $product->alkoholgehalt_vol_percent !== null ? number_format((float)$product->alkoholgehalt_vol_percent, 1, '.', '') : '' }}"
                                       min="0" max="100" step="0.1"
                                       placeholder="–"
                                       style="width:100px;padding:3px 6px;border:1px solid var(--c-border);border-radius:4px;font-size:.9em;{{ $product->alkoholgehalt_vol_percent !== null ? 'background:#f0fdf4;' : '' }}">
                                <span style="font-size:.8em;color:var(--c-text-muted)">%</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:var(--c-text-muted);padding:32px">
                                Keine Produkte gefunden.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div style="padding:12px 16px;border-top:1px solid var(--c-border)">
                {{ $products->withQueryString()->links() }}
            </div>
        @endif

        <div style="padding:12px 16px;border-top:1px solid var(--c-border);text-align:right">
            <button type="submit" class="btn btn-primary">Speichern</button>
        </div>
    </div>
</form>

@endsection
