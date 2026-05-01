@extends('admin.layout')

@section('title', 'Neuer Veranstaltungsort')

@section('content')
<div class="card">
    <div class="card-header">Neuen Veranstaltungsort anlegen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.event.locations.store') }}">
            @csrf
            <div class="form-group">
                <label>Name <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="255">
            </div>
            <div class="form-group">
                <label>Straße</label>
                <input type="text" name="street" class="form-control" value="{{ old('street') }}" maxlength="255">
            </div>
            <div style="display:grid;grid-template-columns:120px 1fr;gap:16px">
                <div class="form-group">
                    <label>PLZ</label>
                    <input type="text" name="zip" class="form-control" value="{{ old('zip') }}" maxlength="10">
                </div>
                <div class="form-group">
                    <label>Ort</label>
                    <input type="text" name="city" class="form-control" value="{{ old('city') }}" maxlength="255">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Breitengrad (geo_lat)</label>
                    <input type="number" name="geo_lat" class="form-control" value="{{ old('geo_lat') }}" step="any" min="-90" max="90">
                </div>
                <div class="form-group">
                    <label>Längengrad (geo_lng)</label>
                    <input type="number" name="geo_lng" class="form-control" value="{{ old('geo_lng') }}" step="any" min="-180" max="180">
                </div>
            </div>
            <div class="form-group">
                <label>Notizen</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" {{ old('active', '1') ? 'checked' : '' }}>
                    Aktiv (im Dropdown der Bestellung sichtbar)
                </label>
            </div>
            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.event.locations.index') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection
