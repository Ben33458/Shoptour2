@extends('admin.layout')

@section('title', 'Leihgerät bearbeiten: ' . $item->name)

@section('actions')
    <a href="{{ route('admin.rental.items.show', $item) }}" class="btn btn-outline btn-sm">← Details</a>
    <a href="{{ route('admin.rental.items.index') }}" class="btn btn-outline btn-sm">← Übersicht</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.rental.items.update', $item) }}">
    @csrf @method('PUT')

    <div class="card">
        <div class="card-header">Stammdaten</div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" value="{{ old('name', $item->name) }}"
                           class="form-control" required maxlength="255">
                    @error('name')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Artikelnummer</label>
                    <input type="text" name="article_number" value="{{ old('article_number', $item->article_number) }}"
                           class="form-control" maxlength="100">
                    @error('article_number')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-group">
                <label>Slug *</label>
                <input type="text" name="slug" value="{{ old('slug', $item->slug) }}"
                       class="form-control" required maxlength="255">
                @error('slug')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label>Beschreibung</label>
                <textarea name="description" class="form-control" rows="4">{{ old('description', $item->description) }}</textarea>
                @error('description')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label>Kategorie</label>
                <select name="category_id" class="form-control">
                    <option value="">— Keine Kategorie —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}"
                            {{ old('category_id', $item->category_id) == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>

        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-header">Abrechnung &amp; Inventar</div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Abrechnungsart *</label>
                    <select name="billing_mode" class="form-control" required>
                        <option value="per_item" {{ old('billing_mode', $item->billing_mode) == 'per_item' ? 'selected' : '' }}>Pro Stück</option>
                        <option value="per_pack" {{ old('billing_mode', $item->billing_mode) == 'per_pack' ? 'selected' : '' }}>Pro Gebinde</option>
                        <option value="per_set"  {{ old('billing_mode', $item->billing_mode) == 'per_set'  ? 'selected' : '' }}>Pro Set</option>
                        <option value="flat"     {{ old('billing_mode', $item->billing_mode) == 'flat'     ? 'selected' : '' }}>Pauschal</option>
                    </select>
                    @error('billing_mode')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label>Inventarart *</label>
                    <select name="inventory_mode" class="form-control" required id="inventory-mode">
                        <option value="unit_based"      {{ old('inventory_mode', $item->inventory_mode) == 'unit_based'      ? 'selected' : '' }}>Einheitenbasiert</option>
                        <option value="quantity_based"  {{ old('inventory_mode', $item->inventory_mode) == 'quantity_based'  ? 'selected' : '' }}>Mengenbasiert</option>
                        <option value="component_based" {{ old('inventory_mode', $item->inventory_mode) == 'component_based' ? 'selected' : '' }}>Komponentenbasiert</option>
                        <option value="packaging_based" {{ old('inventory_mode', $item->inventory_mode) == 'packaging_based' ? 'selected' : '' }}>Verpackungsbasiert</option>
                    </select>
                    @error('inventory_mode')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label>Transportklasse *</label>
                    <select name="transport_class" class="form-control" required>
                        <option value="small"  {{ old('transport_class', $item->transport_class) == 'small'  ? 'selected' : '' }}>Klein</option>
                        <option value="normal" {{ old('transport_class', $item->transport_class) == 'normal' ? 'selected' : '' }}>Normal</option>
                        <option value="truck"  {{ old('transport_class', $item->transport_class) == 'truck'  ? 'selected' : '' }}>LKW</option>
                    </select>
                    @error('transport_class')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

            <div id="total-quantity-field"
                 style="{{ old('inventory_mode', $item->inventory_mode) !== 'quantity_based' ? 'display:none' : '' }}">
                <div class="form-group">
                    <label>Gesamtmenge</label>
                    <input type="number" name="total_quantity"
                           value="{{ old('total_quantity', $item->total_quantity) }}"
                           class="form-control" min="0" style="max-width:160px">
                    @error('total_quantity')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

            <div style="display:flex;gap:24px;flex-wrap:wrap">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="hidden" name="allow_overbooking" value="0">
                    <input type="checkbox" name="allow_overbooking" value="1"
                           {{ old('allow_overbooking', $item->allow_overbooking) ? 'checked' : '' }}>
                    Überbuchung erlauben
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="hidden" name="price_on_request" value="0">
                    <input type="checkbox" name="price_on_request" value="1"
                           {{ old('price_on_request', $item->price_on_request) ? 'checked' : '' }}>
                    Preis auf Anfrage (externe Miete)
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1"
                           {{ old('active', $item->active) ? 'checked' : '' }}>
                    Gerät aktiv
                </label>
            </div>

        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-header">Gebührenregeln</div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Kautionsregel</label>
                    <select name="deposit_rule_id" class="form-control">
                        <option value="">— Keine —</option>
                        @foreach($depositRules as $rule)
                            <option value="{{ $rule->id }}"
                                {{ old('deposit_rule_id', $item->deposit_rule_id) == $rule->id ? 'selected' : '' }}>
                                {{ $rule->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('deposit_rule_id')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Reinigungspauschale</label>
                    <select name="cleaning_fee_rule_id" class="form-control">
                        <option value="">— Keine —</option>
                        @foreach($cleaningFeeRules as $rule)
                            <option value="{{ $rule->id }}"
                                {{ old('cleaning_fee_rule_id', $item->cleaning_fee_rule_id) == $rule->id ? 'selected' : '' }}>
                                {{ $rule->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('cleaning_fee_rule_id')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

        </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit" class="btn btn-primary">Änderungen speichern</button>
        <a href="{{ route('admin.rental.items.show', $item) }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>

<script>
document.getElementById('inventory-mode').addEventListener('change', function () {
    var field = document.getElementById('total-quantity-field');
    field.style.display = this.value === 'quantity_based' ? '' : 'none';
});
</script>
@endsection
