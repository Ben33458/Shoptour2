@extends('admin.layout')

@section('title', 'Neue Inventareinheit')

@section('content')
<div class="card">
    <div class="card-header">Neue Inventareinheit anlegen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rental.inventory-units.store') }}">
            @csrf
            <div class="form-group">
                <label>Leihartikel <span style="color:var(--c-danger)">*</span></label>
                <select name="rental_item_id" class="form-control" required>
                    <option value="">– wählen –</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}"
                            {{ old('rental_item_id', $selectedItem?->id) == $item->id ? 'selected' : '' }}>
                            {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Inventarnummer <span style="color:var(--c-danger)">*</span></label>
                    <input type="text" name="inventory_number" class="form-control" value="{{ old('inventory_number') }}" required maxlength="50">
                </div>
                <div class="form-group">
                    <label>Seriennummer</label>
                    <input type="text" name="serial_number" class="form-control" value="{{ old('serial_number') }}" maxlength="100">
                </div>
            </div>
            <div class="form-group">
                <label>Bezeichnung <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="255">
            </div>
            <div class="form-group">
                <label>Status <span style="color:var(--c-danger)">*</span></label>
                <select name="status" class="form-control" required>
                    @foreach(['available' => 'Verfügbar', 'reserved' => 'Reserviert', 'in_use' => 'Im Einsatz', 'maintenance' => 'Wartung', 'defective' => 'Defekt', 'retired' => 'Ausgemustert'] as $val => $label)
                        <option value="{{ $val }}" {{ old('status', 'available') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Zustand / Notizen</label>
                <textarea name="condition_notes" class="form-control" rows="3">{{ old('condition_notes') }}</textarea>
            </div>
            <div class="form-group">
                <label>Lagerort</label>
                <input type="text" name="location" class="form-control" value="{{ old('location') }}" maxlength="255">
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="preferred_for_booking" value="1" {{ old('preferred_for_booking') ? 'checked' : '' }}>
                    Bevorzugt für Buchungen
                </label>
            </div>
            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.rental.inventory-units.index') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection
