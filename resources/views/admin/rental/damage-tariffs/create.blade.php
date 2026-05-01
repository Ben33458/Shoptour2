@extends('admin.layout')

@section('title', 'Neuer Schadenstarif')

@section('content')
<div class="card">
    <div class="card-header">Neuen Schadenstarif anlegen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rental.damage-tariffs.store') }}">
            @csrf
            <div class="form-group">
                <label>Name <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="150">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Gilt für Typ <span style="color:var(--c-danger)">*</span></label>
                    <select name="applies_to_type" class="form-control" required>
                        @foreach(['rental_item' => 'Leihartikel', 'category' => 'Kategorie', 'packaging_unit' => 'Verpackungseinheit'] as $val => $label)
                            <option value="{{ $val }}" {{ old('applies_to_type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Gilt für ID <span style="color:var(--c-danger)">*</span></label>
                    <input type="number" name="applies_to_id" class="form-control" value="{{ old('applies_to_id') }}" required min="1">
                </div>
            </div>
            <div class="form-group">
                <label>Betrag netto (€) <span style="color:var(--c-danger)">*</span></label>
                <input type="number" name="amount_net_eur" class="form-control" value="{{ old('amount_net_eur') }}" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>Notizen</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="active" value="1" checked>
                    Aktiv
                </label>
            </div>
            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.rental.damage-tariffs.index') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection
