@extends('admin.layout')

@section('title', 'Pfandregel bearbeiten')

@section('content')
<div class="card">
    <div class="card-header">Pfandregel bearbeiten</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rental.deposit-rules.update', $depositRule) }}">
            @csrf @method('PUT')
            <div class="form-group">
                <label>Name <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $depositRule->name) }}" required maxlength="150">
            </div>
            <div class="form-group">
                <label>Regeltyp <span style="color:var(--c-danger)">*</span></label>
                <select name="rule_type" class="form-control" required>
                    @foreach(['none' => 'Kein Pfand', 'fixed_per_item' => 'Fix pro Stück', 'private_only' => 'Nur Privatkunden', 'risk_class' => 'Ab Risikoklasse'] as $val => $label)
                        <option value="{{ $val }}" {{ old('rule_type', $depositRule->rule_type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Betrag netto (€)</label>
                <input type="number" name="amount_net_eur" class="form-control"
                       value="{{ old('amount_net_eur', $depositRule->amount_net_milli ? number_format($depositRule->amount_net_milli / 1000000, 2, '.', '') : '') }}"
                       step="0.01" min="0">
            </div>
            <div class="form-group">
                <label>Min. Risikoklasse</label>
                <input type="text" name="min_risk_class" class="form-control" value="{{ old('min_risk_class', $depositRule->min_risk_class) }}" maxlength="50">
            </div>
            <div class="form-group">
                <label>Notizen</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $depositRule->notes) }}</textarea>
            </div>
            <div style="display:flex;gap:16px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="private_only" value="1" {{ old('private_only', $depositRule->private_only) ? 'checked' : '' }}>
                    Nur für Privatkunden
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="active" value="1" {{ old('active', $depositRule->active) ? 'checked' : '' }}>
                    Aktiv
                </label>
            </div>
            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.rental.deposit-rules.index') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection
