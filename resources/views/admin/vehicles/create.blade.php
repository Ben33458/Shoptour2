@extends('admin.layout')

@section('title', 'Neues Fahrzeug anlegen')

@section('actions')
    <a href="{{ route('admin.vehicles.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.vehicles.store') }}">
    @csrf

    {{-- Stammdaten --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Stammdaten</div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Interner Name *</label>
                    <input type="text" name="internal_name" value="{{ old('internal_name') }}"
                           class="form-control" required maxlength="255"
                           placeholder="z.B. Transporter 1">
                    @error('internal_name')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Kennzeichen *</label>
                    <input type="text" name="plate_number" value="{{ old('plate_number') }}"
                           class="form-control" required maxlength="20"
                           placeholder="z.B. AB-CD 123">
                    @error('plate_number')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Hersteller</label>
                    <input type="text" name="manufacturer" value="{{ old('manufacturer') }}"
                           class="form-control" maxlength="100" placeholder="z.B. Mercedes">
                    @error('manufacturer')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Modell</label>
                    <input type="text" name="model" value="{{ old('model') }}"
                           class="form-control" maxlength="100" placeholder="z.B. Sprinter">
                    @error('model')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Fahrzeugtyp</label>
                    <input type="text" name="vehicle_type" value="{{ old('vehicle_type') }}"
                           class="form-control" maxlength="100" placeholder="z.B. Kastenwagen">
                    @error('vehicle_type')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>FIN / VIN</label>
                    <input type="text" name="vin" value="{{ old('vin') }}"
                           class="form-control" maxlength="50" placeholder="Fahrzeugidentifikationsnummer">
                    @error('vin')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Erstzulassung</label>
                    <input type="date" name="first_registration" value="{{ old('first_registration') }}"
                           class="form-control">
                    @error('first_registration')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Baujahr</label>
                    <input type="number" name="year" value="{{ old('year') }}"
                           class="form-control" min="1900" max="2100" placeholder="z.B. 2020">
                    @error('year')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Standort</label>
                    <input type="text" name="location" value="{{ old('location') }}"
                           class="form-control" maxlength="255" placeholder="z.B. Lager A">
                    @error('location')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:28px">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1"
                               {{ old('active', true) ? 'checked' : '' }}>
                        Fahrzeug aktiv
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>Notizen</label>
                <textarea name="notes" class="form-control" rows="3"
                          placeholder="Interne Anmerkungen zum Fahrzeug">{{ old('notes') }}</textarea>
                @error('notes')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>

        </div>
    </div>

    {{-- Gewichte & Maße --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Gewichte &amp; Maße</div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Gesamtgewicht (kg)</label>
                    <input type="number" name="gross_vehicle_weight" value="{{ old('gross_vehicle_weight') }}"
                           class="form-control" min="0" placeholder="zGG in kg">
                    @error('gross_vehicle_weight')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Leergewicht (kg)</label>
                    <input type="number" name="empty_weight" value="{{ old('empty_weight') }}"
                           class="form-control" min="0" placeholder="Leergewicht in kg">
                    @error('empty_weight')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Nutzlast (kg)</label>
                    <input type="number" name="payload_weight" value="{{ old('payload_weight') }}"
                           class="form-control" min="0" placeholder="Nutzlast in kg">
                    @error('payload_weight')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Ladevolumen (l)</label>
                    <input type="number" name="load_volume" value="{{ old('load_volume') }}"
                           class="form-control" min="0" placeholder="Liter">
                    @error('load_volume')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Ladelänge (cm)</label>
                    <input type="number" name="load_length" value="{{ old('load_length') }}"
                           class="form-control" min="0" placeholder="cm">
                    @error('load_length')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Ladebreite (cm)</label>
                    <input type="number" name="load_width" value="{{ old('load_width') }}"
                           class="form-control" min="0" placeholder="cm">
                    @error('load_width')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Ladehöhe (cm)</label>
                    <input type="number" name="load_height" value="{{ old('load_height') }}"
                           class="form-control" min="0" placeholder="cm">
                    @error('load_height')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Max. VPE ohne Sackkarre</label>
                    <input type="number" name="max_vpe_without_hand_truck" value="{{ old('max_vpe_without_hand_truck') }}"
                           class="form-control" min="0">
                    @error('max_vpe_without_hand_truck')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Max. VPE mit Sackkarre</label>
                    <input type="number" name="max_vpe_with_hand_truck" value="{{ old('max_vpe_with_hand_truck') }}"
                           class="form-control" min="0">
                    @error('max_vpe_with_hand_truck')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

        </div>
    </div>

    {{-- Ausstattung --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Ausstattung</div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Sitzplätze</label>
                    <input type="number" name="seats" value="{{ old('seats') }}"
                           class="form-control" min="1" placeholder="Anzahl Sitzplätze">
                    @error('seats')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Max. Anhängelast (kg)</label>
                    <input type="number" name="max_trailer_load" value="{{ old('max_trailer_load') }}"
                           class="form-control" min="0">
                    @error('max_trailer_load')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Erforderliche Führerscheinklasse</label>
                    <input type="text" name="required_license_class" value="{{ old('required_license_class') }}"
                           class="form-control" maxlength="20" placeholder="z.B. B, BE, C1">
                    @error('required_license_class')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

            <div style="display:flex;gap:24px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="hidden" name="trailer_hitch" value="0">
                    <input type="checkbox" name="trailer_hitch" value="1"
                           {{ old('trailer_hitch') ? 'checked' : '' }}>
                    Anhängerkupplung vorhanden
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="hidden" name="cooling_unit" value="0">
                    <input type="checkbox" name="cooling_unit" value="1"
                           {{ old('cooling_unit') ? 'checked' : '' }}>
                    Kühlaggregat vorhanden
                </label>
            </div>

        </div>
    </div>

    {{-- Wartung --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Wartung</div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>TÜV fällig am</label>
                    <input type="date" name="tuev_due_date" value="{{ old('tuev_due_date') }}"
                           class="form-control">
                    @error('tuev_due_date')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>HU/AU fällig am</label>
                    <input type="date" name="inspection_due_date" value="{{ old('inspection_due_date') }}"
                           class="form-control">
                    @error('inspection_due_date')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Ölwechsel fällig am</label>
                    <input type="date" name="oil_service_due_date" value="{{ old('oil_service_due_date') }}"
                           class="form-control">
                    @error('oil_service_due_date')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Nächster Service bei (km)</label>
                    <input type="number" name="next_service_km" value="{{ old('next_service_km') }}"
                           class="form-control" min="0" placeholder="km">
                    @error('next_service_km')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Aktueller Kilometerstand</label>
                    <input type="number" name="current_mileage" value="{{ old('current_mileage') }}"
                           class="form-control" min="0" placeholder="km">
                    @error('current_mileage')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>

        </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit" class="btn btn-primary">Fahrzeug anlegen</button>
        <a href="{{ route('admin.vehicles.index') }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>
@endsection
