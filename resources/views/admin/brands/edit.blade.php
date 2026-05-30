@extends('admin.layout')

@section('title', 'Hersteller bearbeiten')

@section('actions')
    <a href="{{ route('admin.brands.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
@endsection

@section('content')
<div class="card" style="max-width:640px">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">Bitte Eingaben prüfen.</div>@endif

    <form method="POST" action="{{ route('admin.brands.update', $brand) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label>Name <span style="color:var(--c-danger)">*</span></label>
            <input type="text" name="name" value="{{ old('name', $brand->name) }}" required maxlength="150">
            @error('name')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
        </div>

        <hr style="margin:1.25rem 0;border-color:var(--c-border)">
        <p style="font-size:.85rem;color:var(--c-muted);margin-bottom:1rem">
            Pflichtangabe laut LMIV: Name und vollständige Anschrift des Lebensmittelunternehmers.
        </p>

        <div class="form-group">
            <label>Straße &amp; Hausnummer</label>
            <input type="text" name="adresse" value="{{ old('adresse', $brand->adresse) }}" maxlength="255"
                   placeholder="z.B. Hopfenstraße 12">
            @error('adresse')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
        </div>

        <div style="display:grid;grid-template-columns:120px 1fr;gap:.75rem">
            <div class="form-group">
                <label>PLZ</label>
                <input type="text" name="plz" value="{{ old('plz', $brand->plz) }}" maxlength="10"
                       placeholder="80331">
                @error('plz')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label>Ort</label>
                <input type="text" name="ort" value="{{ old('ort', $brand->ort) }}" maxlength="100"
                       placeholder="München">
                @error('ort')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-group">
            <label>Land</label>
            <input type="text" name="land" value="{{ old('land', $brand->land ?? 'Deutschland') }}" maxlength="100">
            @error('land')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
        </div>

        <hr style="margin:1.25rem 0;border-color:var(--c-border)">

        <div class="form-group">
            <label>E-Mail</label>
            <input type="email" name="email" value="{{ old('email', $brand->email) }}" maxlength="255"
                   placeholder="info@beispiel.de">
            @error('email')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label>Website</label>
            <input type="url" name="website" value="{{ old('website', $brand->website) }}" maxlength="255"
                   placeholder="https://www.beispiel.de">
            @error('website')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.5rem">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="{{ route('admin.brands.index') }}" class="btn btn-outline">Abbrechen</a>
        </div>
    </form>
</div>
@endsection
