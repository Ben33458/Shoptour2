@extends('admin.layout')

@section('title', 'Neue Pfandregel')

@section('content')
<div class="card">
    <div class="card-header">Neue Pfandregel anlegen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rental.deposit-rules.store') }}">
            @csrf
            <div class="form-group">
                <label>Name <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="150">
            </div>
            <div class="form-group">
                <label>Regeltyp <span style="color:var(--c-danger)">*</span></label>
                <select name="rule_type" class="form-control" required>
                    @foreach(['none' => 'Kein Pfand', 'fixed_per_item' => 'Fix pro Stück', 'private_only' => 'Nur Privatkunden', 'risk_class' => 'Ab Risikoklasse'] as $val => $label)
                        <option value="{{ $val }}" {{ old('rule_type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Betrag netto (€)</label>
                <input type="number" name="amount_net_eur" class="form-control" value="{{ old('amount_net_eur') }}" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label>Min. Risikoklasse</label>
                <input type="text" name="min_risk_class" class="form-control" value="{{ old('min_risk_class') }}" maxlength="50" placeholder="z.B. B, C">
            </div>
            <div class="form-group">
                <label>Notizen</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div style="display:flex;gap:16px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="private_only" value="1" {{ old('private_only') ? 'checked' : '' }}>
                    Nur für Privatkunden
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="active" value="1" checked>
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
