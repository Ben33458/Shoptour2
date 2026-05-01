@extends('admin.layout')

@section('title', 'Neue Reinigungsgebühr')

@section('content')
<div class="card">
    <div class="card-header">Neue Reinigungsgebühr anlegen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rental.cleaning-fee-rules.store') }}">
            @csrf
            <div class="form-group">
                <label>Name <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="150">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Gilt für Typ <span style="color:var(--c-danger)">*</span></label>
                    <select name="applies_to_type" class="form-control" required>
                        @foreach(['rental_item' => 'Leihartikel', 'category' => 'Kategorie', 'packaging_unit' => 'Verpackungseinheit', 'inventory_unit' => 'Inventareinheit'] as $val => $label)
                            <option value="{{ $val }}" {{ old('applies_to_type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Gilt für ID</label>
                    <input type="number" name="applies_to_id" class="form-control" value="{{ old('applies_to_id') }}" min="1">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Gebührentyp <span style="color:var(--c-danger)">*</span></label>
                    <select name="fee_type" class="form-control" required>
                        @foreach(['flat' => 'Pauschale', 'per_item' => 'Pro Stück', 'per_pack' => 'Pro Gebinde', 'per_unit' => 'Pro Einheit'] as $val => $label)
                            <option value="{{ $val }}" {{ old('fee_type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Betrag netto (€) <span style="color:var(--c-danger)">*</span></label>
                    <input type="number" name="amount_net_eur" class="form-control" value="{{ old('amount_net_eur') }}" step="0.01" min="0" required>
                </div>
            </div>
            <div class="form-group">
                <label>Notizen</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.rental.cleaning-fee-rules.index') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection
