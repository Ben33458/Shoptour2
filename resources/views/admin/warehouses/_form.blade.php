{{-- Gemeinsames Formular für create + edit --}}
<div class="card">
    <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

        <div class="form-group">
            <label>Name *</label>
            <input type="text" name="name"
                   value="{{ old('name', $warehouse?->name) }}"
                   class="form-control" required maxlength="255"
                   placeholder="z.B. Hauptlager, Aussenlager Nord">
            @error('name')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label>Standort / Adresse</label>
            <input type="text" name="location"
                   value="{{ old('location', $warehouse?->location) }}"
                   class="form-control" maxlength="255"
                   placeholder="z.B. Musterstraße 12, 12345 Musterstadt">
            @error('location')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
        </div>

        <div style="display:flex;gap:24px;flex-wrap:wrap">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1"
                       {{ old('active', $warehouse?->active ?? true) ? 'checked' : '' }}>
                Lagerort aktiv
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="hidden" name="is_pickup_location" value="0">
                <input type="checkbox" name="is_pickup_location" value="1"
                       {{ old('is_pickup_location', $warehouse?->is_pickup_location ?? false) ? 'checked' : '' }}>
                Als Abholort für Kunden (Selbstabholung)
            </label>
        </div>

    </div>
</div>
