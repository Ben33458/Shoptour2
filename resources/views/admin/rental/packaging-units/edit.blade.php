@extends('admin.layout')

@section('title', 'Verpackungseinheit bearbeiten')

@section('content')
<div class="card">
    <div class="card-header">VPE bearbeiten: {{ $packagingUnit->label }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rental.packaging-units.update', $packagingUnit) }}">
            @csrf @method('PUT')
            <div class="form-group">
                <label>Leihartikel</label>
                <input type="text" class="form-control" value="{{ $packagingUnit->rentalItem?->name }}" disabled>
            </div>
            <div class="form-group">
                <label>Bezeichnung (Label) <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="label" class="form-control" value="{{ old('label', $packagingUnit->label) }}" required maxlength="100">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Stück pro Gebinde <span style="color:var(--c-danger)">*</span></label>
                    <input type="number" name="pieces_per_pack" class="form-control" value="{{ old('pieces_per_pack', $packagingUnit->pieces_per_pack) }}" required min="1">
                </div>
                <div class="form-group">
                    <label>Verfügbare Gebinde <span style="color:var(--c-danger)">*</span></label>
                    <input type="number" name="available_packs" class="form-control" value="{{ old('available_packs', $packagingUnit->available_packs) }}" required min="0">
                </div>
                <div class="form-group">
                    <label>Sortierung</label>
                    <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $packagingUnit->sort_order) }}" min="0">
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="active" value="1" {{ old('active', $packagingUnit->active) ? 'checked' : '' }}>
                    Aktiv
                </label>
            </div>
            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.rental.packaging-units.index') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection
