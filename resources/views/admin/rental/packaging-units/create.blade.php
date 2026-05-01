@extends('admin.layout')

@section('title', 'Neue Verpackungseinheit')

@section('content')
<div class="card">
    <div class="card-header">Neue Verpackungseinheit (VPE) anlegen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rental.packaging-units.store') }}">
            @csrf
            <div class="form-group">
                <label>Leihartikel <span style="color:var(--c-danger)">*</span></label>
                <select name="rental_item_id" class="form-control" required>
                    <option value="">– wählen –</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}"
                            {{ (old('rental_item_id', request('item_id'))) == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Bezeichnung (Label) <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="label" class="form-control" value="{{ old('label') }}" required maxlength="100"
                       placeholder="z.B. 6er-Pack, Kiste (20 Gläser)">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Stück pro Gebinde <span style="color:var(--c-danger)">*</span></label>
                    <input type="number" name="pieces_per_pack" class="form-control" value="{{ old('pieces_per_pack') }}" required min="1">
                </div>
                <div class="form-group">
                    <label>Verfügbare Gebinde <span style="color:var(--c-danger)">*</span></label>
                    <input type="number" name="available_packs" class="form-control" value="{{ old('available_packs', 0) }}" required min="0">
                </div>
                <div class="form-group">
                    <label>Sortierung</label>
                    <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="active" value="1" checked>
                    Aktiv
                </label>
            </div>
            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.rental.packaging-units.index', request()->only('item_id')) }}"
                   class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection
