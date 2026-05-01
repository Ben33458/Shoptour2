@extends('admin.layout')

@section('title', 'Kategorie bearbeiten: ' . $category->name)

@section('actions')
    <a href="{{ route('admin.rental.categories.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.rental.categories.update', $category) }}">
    @csrf @method('PUT')

    <div class="card">
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}"
                       class="form-control" required maxlength="255">
                @error('name')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label>Slug *</label>
                <input type="text" name="slug" value="{{ old('slug', $category->slug) }}"
                       class="form-control" required maxlength="255">
                @error('slug')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label>Sortierreihenfolge</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}"
                       class="form-control" min="0" style="max-width:160px">
                @error('sort_order')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>

            <div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1"
                           {{ old('active', $category->active) ? 'checked' : '' }}>
                    Kategorie aktiv
                </label>
            </div>

        </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit" class="btn btn-primary">Änderungen speichern</button>
        <a href="{{ route('admin.rental.categories.index') }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>
@endsection
