@extends('admin.layout')

@section('title', 'Neue Bestandsaufnahme')

@section('content')

<div style="max-width:500px">
    <form method="POST" action="{{ route('admin.bestandsaufnahme.store') }}">
        @csrf
        <div class="form-group">
            <label>Lager <span class="text-danger">*</span></label>
            <select name="warehouse_id" required class="@error('warehouse_id') is-invalid @enderror">
                <option value="">— Lager wählen —</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}" @selected(old('warehouse_id') == $w->id)>{{ $w->name }}</option>
                @endforeach
            </select>
            @error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label>Titel (optional)</label>
            <input type="text" name="titel" value="{{ old('titel') }}" placeholder="z. B. Jahresinventur April 2026" class="form-control">
        </div>

        <div style="display:flex;gap:8px;margin-top:16px">
            <button type="submit" class="btn btn-primary">Session starten</button>
            <a href="{{ route('admin.bestandsaufnahme.index') }}" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>

@endsection
