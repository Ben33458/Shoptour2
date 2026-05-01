@extends('admin.layout')

@section('title', 'Bestandsverwaltung — Leihartikel')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Bestandsverwaltung</h1>
        <a href="{{ route('admin.rental.items.index') }}" class="btn btn-sm btn-outline-secondary">
            ← Leihartikel
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @foreach($categories as $category)
        @php $items = $category->items; @endphp
        @if($items->isEmpty()) @continue @endif

        <div class="card mb-4">
            <div class="card-header fw-semibold bg-light">{{ $category->name }}</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light text-muted small">
                        <tr>
                            <th style="width:25%">Artikel</th>
                            <th style="width:10%">Art.-Nr.</th>
                            <th style="width:12%">Art</th>
                            <th>Bestand</th>
                            <th style="width:130px"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($items as $item)

                        {{-- ── quantity_based ──────────────────────────────── --}}
                        @if($item->inventory_mode === 'quantity_based')
                        <tr>
                            <td>
                                <a href="{{ route('admin.rental.items.show', $item) }}"
                                   class="text-decoration-none text-dark fw-semibold">{{ $item->name }}</a>
                            </td>
                            <td class="text-muted small">{{ $item->article_number ?? '—' }}</td>
                            <td><span class="badge bg-secondary">Menge</span></td>
                            <td>
                                <form action="{{ route('admin.rental.inventory.updateQty', $item) }}"
                                      method="POST" class="d-flex align-items-center gap-2">
                                    @csrf @method('PUT')
                                    <input type="number" name="total_quantity"
                                           value="{{ $item->total_quantity ?? 0 }}"
                                           min="0" class="form-control form-control-sm" style="width:90px">
                                    <span class="text-muted small">{{ $item->unit_label }}</span>
                                    <button type="submit" class="btn btn-sm btn-primary">OK</button>
                                </form>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.rental.items.edit', $item) }}"
                                   class="btn btn-sm btn-outline-secondary">Bearbeiten</a>
                            </td>
                        </tr>

                        {{-- ── packaging_based ─────────────────────────────── --}}
                        @elseif($item->inventory_mode === 'packaging_based')
                        <tr>
                            <td>
                                <a href="{{ route('admin.rental.items.show', $item) }}"
                                   class="text-decoration-none text-dark fw-semibold">{{ $item->name }}</a>
                            </td>
                            <td class="text-muted small">{{ $item->article_number ?? '—' }}</td>
                            <td><span class="badge bg-info text-dark">VPE</span></td>
                            <td>
                                @if($item->packagingUnits->isEmpty())
                                    <span class="text-muted small">Keine VPEs definiert</span>
                                @else
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <form action="{{ route('admin.rental.inventory.updateQty', $item) }}"
                                              method="POST" class="d-flex align-items-center gap-2">
                                            @csrf @method('PUT')
                                            <input type="number" name="total_quantity"
                                                   value="{{ $item->total_quantity ?? 0 }}"
                                                   min="0" class="form-control form-control-sm" style="width:90px">
                                            <span class="text-muted small">{{ $item->unit_label }} gesamt</span>
                                            <button type="submit" class="btn btn-sm btn-primary">OK</button>
                                        </form>
                                        <span class="text-muted small">
                                            @foreach($item->packagingUnits->where('active', true) as $pu)
                                                {{ $pu->label }}: {{ $pu->available_packs }} VPE
                                                @if(!$loop->last) · @endif
                                            @endforeach
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.rental.items.edit', $item) }}"
                                   class="btn btn-sm btn-outline-secondary">Bearbeiten</a>
                            </td>
                        </tr>

                        {{-- ── unit_based ───────────────────────────────────── --}}
                        @elseif($item->inventory_mode === 'unit_based')
                        <tr>
                            <td>
                                <a href="{{ route('admin.rental.items.show', $item) }}"
                                   class="text-decoration-none text-dark fw-semibold">{{ $item->name }}</a>
                            </td>
                            <td class="text-muted small">{{ $item->article_number ?? '—' }}</td>
                            <td><span class="badge bg-warning text-dark">Einheit</span></td>
                            <td>
                                @php
                                    $totalUnits     = $item->inventoryUnits->count();
                                    $availableUnits = $item->inventoryUnits->where('status', 'available')->count();
                                @endphp
                                @if($totalUnits === 0)
                                    <span class="text-warning small">Keine Einheiten — nicht buchbar</span>
                                @else
                                    <details>
                                        <summary class="text-primary small" style="cursor:pointer">
                                            {{ $availableUnits }}/{{ $totalUnits }} verfügbar
                                        </summary>
                                        <div class="mt-2">
                                            <table class="table table-sm table-borderless mb-1">
                                                <thead class="text-muted small">
                                                    <tr>
                                                        <th>Nr.</th>
                                                        <th>Bezeichnung</th>
                                                        <th>Status</th>
                                                        <th>Standort</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                @foreach($item->inventoryUnits as $unit)
                                                    <tr>
                                                        <td class="small"><code>{{ $unit->inventory_number }}</code></td>
                                                        <td class="small">{{ $unit->title }}</td>
                                                        <td>
                                                            <form action="{{ route('admin.rental.inventory.updateUnit', $unit) }}"
                                                                  method="POST" class="d-flex gap-1">
                                                                @csrf @method('PUT')
                                                                <select name="status" class="form-select form-select-sm"
                                                                        style="width:140px" onchange="this.form.submit()">
                                                                    @foreach(['available','reserved','maintenance','defective','retired'] as $s)
                                                                        <option value="{{ $s }}" {{ $unit->status === $s ? 'selected' : '' }}>
                                                                            {{ match($s) {
                                                                                'available'   => '✅ Verfügbar',
                                                                                'reserved'    => '📋 Reserviert',
                                                                                'maintenance' => '🔧 Wartung',
                                                                                'defective'   => '⚠️ Defekt',
                                                                                'retired'     => '🚫 Außer Betrieb',
                                                                            } }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </form>
                                                        </td>
                                                        <td class="small">{{ $unit->location ?? '—' }}</td>
                                                        <td>
                                                            <form action="{{ route('admin.rental.inventory.destroyUnit', $unit) }}"
                                                                  method="POST" onsubmit="return confirm('Einheit löschen?')">
                                                                @csrf @method('DELETE')
                                                                <button class="btn btn-sm btn-outline-danger">×</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                            {{-- Neue Einheit --}}
                                            <form action="{{ route('admin.rental.inventory.storeUnit', $item) }}"
                                                  method="POST" class="d-flex gap-2 flex-wrap mt-1">
                                                @csrf
                                                <input type="text" name="inventory_number"
                                                       placeholder="Inventar-Nr." class="form-control form-control-sm" style="width:140px" required>
                                                <input type="text" name="title"
                                                       placeholder="Bezeichnung" class="form-control form-control-sm" style="width:180px" required>
                                                <select name="status" class="form-select form-select-sm" style="width:130px">
                                                    <option value="available">Verfügbar</option>
                                                    <option value="maintenance">Wartung</option>
                                                    <option value="defective">Defekt</option>
                                                </select>
                                                <input type="text" name="location" placeholder="Standort"
                                                       class="form-control form-control-sm" style="width:120px">
                                                <button type="submit" class="btn btn-sm btn-success">+ Hinzufügen</button>
                                            </form>
                                        </div>
                                    </details>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.rental.items.edit', $item) }}"
                                   class="btn btn-sm btn-outline-secondary">Bearbeiten</a>
                            </td>
                        </tr>

                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

</div>
@endsection
