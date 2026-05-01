@extends('admin.layout')

@section('title', 'Mangel erfassen')

@section('content')
<div class="card">
    <div class="card-header">Neuen Mangel erfassen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.assets.issues.store') }}">
            @csrf

            <div class="form-group">
                <label>Asset-Typ <span style="color:var(--c-danger)">*</span></label>
                <select name="asset_type" class="form-control" required id="assetTypeSelect"
                        onchange="toggleAssetSelect()">
                    <option value="">– wählen –</option>
                    <option value="vehicle" {{ old('asset_type', request('asset_type')) === 'vehicle' ? 'selected' : '' }}>Fahrzeug</option>
                    <option value="rental_inventory_unit" {{ old('asset_type', request('asset_type')) === 'rental_inventory_unit' ? 'selected' : '' }}>Mieteinheit</option>
                </select>
            </div>

            <div class="form-group" id="vehicleSelect" style="display:none">
                <label>Fahrzeug <span style="color:var(--c-danger)">*</span></label>
                <select name="asset_id" class="form-control" id="vehicleSelectField">
                    <option value="">– wählen –</option>
                    @foreach($vehicles as $v)
                        <option value="{{ $v->id }}" {{ (string)(old('asset_id', request('asset_id'))) === (string)$v->id && old('asset_type', request('asset_type')) === 'vehicle' ? 'selected' : '' }}>
                            {{ $v->internal_name }} ({{ $v->plate_number }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" id="unitSelect" style="display:none">
                <label>Mieteinheit <span style="color:var(--c-danger)">*</span></label>
                <select name="asset_id" class="form-control" id="unitSelectField">
                    <option value="">– wählen –</option>
                    @foreach($units as $u)
                        <option value="{{ $u->id }}" {{ (string)(old('asset_id', request('asset_id'))) === (string)$u->id && old('asset_type', request('asset_type')) === 'rental_inventory_unit' ? 'selected' : '' }}>
                            {{ $u->inventory_number }} – {{ $u->rentalItem?->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Titel <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="255">
            </div>

            <div class="form-group">
                <label>Beschreibung</label>
                <textarea name="description" class="form-control" rows="4">{{ old('description') }}</textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Priorität <span style="color:var(--c-danger)">*</span></label>
                    <select name="priority" class="form-control" required>
                        @foreach(['low' => 'Niedrig', 'medium' => 'Mittel', 'high' => 'Hoch', 'critical' => 'Kritisch'] as $val => $label)
                            <option value="{{ $val }}" {{ old('priority') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Schwere <span style="color:var(--c-danger)">*</span></label>
                    <select name="severity" class="form-control" required>
                        @foreach(['minor' => 'Gering', 'moderate' => 'Mittel', 'major' => 'Schwer'] as $val => $label)
                            <option value="{{ $val }}" {{ old('severity') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Zugewiesen an</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">– niemanden –</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('assigned_to') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Geschätzte Kosten (€)</label>
                    <input type="number" name="estimated_cost_eur" class="form-control" value="{{ old('estimated_cost_eur') }}" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Werkstatt</label>
                    <input type="text" name="workshop_name" class="form-control" value="{{ old('workshop_name') }}" maxlength="255">
                </div>
            </div>

            <div class="form-group">
                <label>Fällig bis</label>
                <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
            </div>

            <div style="display:flex;gap:16px;margin-top:8px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="blocks_usage" value="1" {{ old('blocks_usage') ? 'checked' : '' }}>
                    Sperrt Nutzung (blocks_usage)
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="blocks_rental" value="1" {{ old('blocks_rental') ? 'checked' : '' }}>
                    Sperrt Vermietung (blocks_rental)
                </label>
            </div>

            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Mangel erfassen</button>
                <a href="{{ route('admin.assets.issues.index') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAssetSelect() {
    const type = document.getElementById('assetTypeSelect').value;
    document.getElementById('vehicleSelect').style.display = type === 'vehicle' ? '' : 'none';
    document.getElementById('unitSelect').style.display   = type === 'rental_inventory_unit' ? '' : 'none';
    // disable the non-active select so it doesn't submit
    document.getElementById('vehicleSelectField').disabled = type !== 'vehicle';
    document.getElementById('unitSelectField').disabled   = type !== 'rental_inventory_unit';
}
// Run on load to handle old() prefill
document.addEventListener('DOMContentLoaded', toggleAssetSelect);
</script>
@endsection
