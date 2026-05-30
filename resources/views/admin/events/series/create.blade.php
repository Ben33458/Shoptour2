@extends('admin.layout')

@section('title', 'Neue Serie')

@section('content')

<div style="margin-bottom:16px">
    <a href="{{ route('admin.events.series.index') }}" style="color:var(--c-muted);font-size:.85rem">&larr; Serien</a>
    <h1 style="margin:4px 0;font-size:1.3rem">Neue Veranstaltungsserie</h1>
</div>

<form method="POST" action="{{ route('admin.events.series.store') }}">
    @csrf

    <div class="card" style="max-width:600px">
        <div class="card-header">Seriendetails</div>
        <div style="padding:16px;display:grid;gap:16px">

            <div class="form-group">
                <label>Name <span style="color:red">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="255">
                @error('name') <span style="color:red;font-size:.8rem">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label>Art <span style="color:red">*</span></label>
                <select name="event_type" required>
                    @foreach($eventTypes as $type)
                        <option value="{{ $type }}" @selected(old('event_type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Kunde</label>
                <select name="customer_id">
                    <option value="">— kein Kunde —</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>
                            {{ $customer->company_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Standardort</label>
                <input type="text" name="default_location_name" value="{{ old('default_location_name') }}" maxlength="255">
            </div>

            <div class="form-group">
                <label>Standardadresse</label>
                <textarea name="default_address" rows="2">{{ old('default_address') }}</textarea>
            </div>

            <div class="form-group">
                <label>Notizen</label>
                <textarea name="default_notes" rows="2">{{ old('default_notes') }}</textarea>
            </div>
        </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">Serie erstellen</button>
        <a href="{{ route('admin.events.series.index') }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>

@endsection
