@extends('admin.layout')

@section('title', 'Serie bearbeiten')

@section('content')

<div style="margin-bottom:16px">
    <a href="{{ route('admin.events.series.show', $series) }}" style="color:var(--c-muted);font-size:.85rem">&larr; {{ $series->name }}</a>
    <h1 style="margin:4px 0;font-size:1.3rem">Serie bearbeiten</h1>
</div>

<form method="POST" action="{{ route('admin.events.series.update', $series) }}">
    @csrf
    @method('PUT')

    <div class="card" style="max-width:600px">
        <div class="card-header">Seriendetails</div>
        <div style="padding:16px;display:grid;gap:16px">

            <div class="form-group">
                <label>Name <span style="color:red">*</span></label>
                <input type="text" name="name" value="{{ old('name', $series->name) }}" required maxlength="255">
            </div>

            <div class="form-group">
                <label>Art <span style="color:red">*</span></label>
                <select name="event_type" required>
                    @foreach($eventTypes as $type)
                        <option value="{{ $type }}" @selected(old('event_type', $series->event_type) === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Kunde</label>
                <select name="customer_id">
                    <option value="">— kein Kunde —</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id', $series->customer_id) == $customer->id)>
                            {{ $customer->company_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Standardort</label>
                <input type="text" name="default_location_name" value="{{ old('default_location_name', $series->default_location_name) }}" maxlength="255">
            </div>

            <div class="form-group">
                <label>Standardadresse</label>
                <textarea name="default_address" rows="2">{{ old('default_address', $series->default_address) }}</textarea>
            </div>

            <div class="form-group">
                <label>Notizen</label>
                <textarea name="default_notes" rows="2">{{ old('default_notes', $series->default_notes) }}</textarea>
            </div>

            <div class="form-group">
                <label style="display:flex;gap:6px;align-items:center;cursor:pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $series->is_active))>
                    Aktiv
                </label>
            </div>

            <div class="form-group">
                <label style="display:flex;gap:6px;align-items:center;cursor:pointer">
                    <input type="hidden" name="is_duplicate_suspect" value="0">
                    <input type="checkbox" name="is_duplicate_suspect" value="1" @checked(old('is_duplicate_suspect', $series->is_duplicate_suspect))>
                    Dublettenverdacht
                </label>
            </div>
        </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="{{ route('admin.events.series.show', $series) }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>

@endsection
