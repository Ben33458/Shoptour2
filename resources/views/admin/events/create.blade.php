@extends('admin.layout')

@section('title', 'Neue Veranstaltung')

@section('content')

<div style="margin-bottom:16px">
    <a href="{{ route('admin.events.index') }}" style="color:var(--c-muted);font-size:.85rem">&larr; Veranstaltungen</a>
    <h1 style="margin:4px 0;font-size:1.3rem">Neue Veranstaltung</h1>
</div>

<form method="POST" action="{{ route('admin.events.store') }}">
    @csrf

    <div class="card" style="max-width:700px">
        <div class="card-header">Basisdaten</div>
        <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:16px">

            <div class="form-group" style="grid-column:1/-1">
                <label>Titel <span style="color:red">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="255">
                @error('title') <span style="color:red;font-size:.8rem">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label>Veranstaltungsart <span style="color:red">*</span></label>
                <select name="event_type" required>
                    @foreach($eventTypes as $type => $label)
                        <option value="{{ $type }}" @selected(old('event_type') === $type)>{{ $label }}</option>
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
                <label>Serie</label>
                <select name="event_series_id">
                    <option value="">— keine Serie —</option>
                    @foreach($series as $s)
                        <option value="{{ $s->id }}" @selected(old('event_series_id') == $s->id)>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Beginn</label>
                <input type="datetime-local" name="event_start_at" value="{{ old('event_start_at') }}">
            </div>

            <div class="form-group">
                <label>Ende</label>
                <input type="datetime-local" name="event_end_at" value="{{ old('event_end_at') }}">
            </div>

            <div class="form-group">
                <label>Ort</label>
                <input type="text" name="location_name" value="{{ old('location_name') }}" maxlength="255">
            </div>

            <div class="form-group">
                <label>Stadt</label>
                <input type="text" name="city" value="{{ old('city') }}" maxlength="100">
            </div>

            <div class="form-group">
                <label>Straße</label>
                <input type="text" name="address_line1" value="{{ old('address_line1') }}" maxlength="255">
            </div>

            <div class="form-group">
                <label>PLZ</label>
                <input type="text" name="postal_code" value="{{ old('postal_code') }}" maxlength="20">
            </div>

            <div class="form-group">
                <label>Erwartete Gäste</label>
                <input type="number" name="expected_guests" value="{{ old('expected_guests') }}" min="0">
            </div>

            <div class="form-group">
                <label>Anfrage-Kanal</label>
                <select name="request_channel">
                    @foreach(\App\Constants\EventLabels::requestChannels() as $val => $label)
                        <option value="{{ $val }}" @selected(old('request_channel', 'unknown') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" style="grid-column:1/-1">
                <label>Interne Notizen</label>
                <textarea name="internal_notes" rows="3">{{ old('internal_notes') }}</textarea>
            </div>
        </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">Veranstaltung erstellen</button>
        <a href="{{ route('admin.events.index') }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>

@endsection
