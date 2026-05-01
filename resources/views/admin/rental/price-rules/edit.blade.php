@extends('admin.layout')

@section('title', 'Preisregel bearbeiten')

@section('content')
<div class="card">
    <div class="card-header">Preisregel bearbeiten</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rental.price-rules.update', $priceRule) }}">
            @csrf @method('PUT')
            <div class="form-group">
                <label>Leihartikel <span style="color:var(--c-danger)">*</span></label>
                <select name="rental_item_id" class="form-control" disabled>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}" {{ $priceRule->rental_item_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                    @endforeach
                </select>
                {{-- rental_item_id not updatable, kept from original --}}
            </div>
            <div class="form-group">
                <label>Zeitmodell <span style="color:var(--c-danger)">*</span></label>
                <select name="rental_time_model_id" class="form-control" required>
                    <option value="">– wählen –</option>
                    @foreach($timeModels as $tm)
                        <option value="{{ $tm->id }}" {{ old('rental_time_model_id', $priceRule->rental_time_model_id) == $tm->id ? 'selected' : '' }}>{{ $tm->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Preis netto (€) <span style="color:var(--c-danger)">*</span></label>
                    <input type="number" name="price_net_eur" class="form-control"
                           value="{{ old('price_net_eur', number_format($priceRule->price_net_milli / 1000000, 2, '.', '')) }}"
                           step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label>Preistyp <span style="color:var(--c-danger)">*</span></label>
                    <select name="price_type" class="form-control" required>
                        @foreach(['per_item' => 'Pro Stück', 'per_pack' => 'Pro Gebinde', 'per_set' => 'Pro Set', 'flat' => 'Pauschal'] as $val => $label)
                            <option value="{{ $val }}" {{ old('price_type', $priceRule->price_type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Min. Menge <span style="color:var(--c-danger)">*</span></label>
                    <input type="number" name="min_quantity" class="form-control" value="{{ old('min_quantity', $priceRule->min_quantity) }}" required min="1">
                </div>
                <div class="form-group">
                    <label>Max. Menge</label>
                    <input type="number" name="max_quantity" class="form-control" value="{{ old('max_quantity', $priceRule->max_quantity) }}" min="1">
                </div>
            </div>
            <div class="form-group">
                <label>Kundengruppen-ID (optional)</label>
                <input type="number" name="customer_group_id" class="form-control" value="{{ old('customer_group_id', $priceRule->customer_group_id) }}" min="1">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Gültig ab</label>
                    <input type="date" name="valid_from" class="form-control" value="{{ old('valid_from', $priceRule->valid_from?->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label>Gültig bis</label>
                    <input type="date" name="valid_until" class="form-control" value="{{ old('valid_until', $priceRule->valid_until?->format('Y-m-d')) }}">
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="requires_drink_order" value="1" {{ old('requires_drink_order', $priceRule->requires_drink_order) ? 'checked' : '' }}>
                    Nur bei gleichzeitiger Getränkebestellung
                </label>
            </div>
            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.rental.price-rules.index') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection
