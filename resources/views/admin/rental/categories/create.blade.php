@extends('admin.layout')

@section('title', 'Neue Kategorie anlegen')

@section('actions')
    <a href="{{ route('admin.rental.categories.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.rental.categories.store') }}">
    @csrf

    <div class="card">
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="{{ old('name') }}"
                       class="form-control" required maxlength="255"
                       placeholder="z.B. Zapfanlagen, Zelte, Mobiliar">
                @error('name')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label>Slug *</label>
                <input type="text" name="slug" value="{{ old('slug') }}"
                       class="form-control" required maxlength="255"
                       placeholder="z.B. zapfanlagen">
                @error('slug')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label>Sortierreihenfolge</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}"
                       class="form-control" min="0" style="max-width:160px">
                @error('sort_order')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>

            <div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1"
                           {{ old('active', true) ? 'checked' : '' }}>
                    Kategorie aktiv
                </label>
            </div>

        </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit" class="btn btn-primary">Kategorie anlegen</button>
        <a href="{{ route('admin.rental.categories.index') }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>
@endsection
