@extends('admin.layout')

@section('title', 'Veranstaltung bearbeiten')

@section('content')

<div style="margin-bottom:16px">
    <a href="{{ route('admin.events.show', $occurrence) }}" style="color:var(--c-muted);font-size:.85rem">&larr; {{ $occurrence->title }}</a>
    <h1 style="margin:4px 0;font-size:1.3rem">Veranstaltung bearbeiten</h1>
</div>

<form method="POST" action="{{ route('admin.events.update', $occurrence) }}">
    @csrf
    @method('PUT')

    <div class="card" style="max-width:700px">
        <div class="card-header">Basisdaten</div>
        <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:16px">

            <div class="form-group" style="grid-column:1/-1">
                <label>Titel <span style="color:red">*</span></label>
                <input type="text" name="title" value="{{ old('title', $occurrence->title) }}" required maxlength="255">
                @error('title') <span style="color:red;font-size:.8rem">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label>Veranstaltungsart <span style="color:red">*</span></label>
                <select name="event_type" required>
                    @foreach($eventTypes as $type => $label)
                        <option value="{{ $type }}" @selected(old('event_type', $occurrence->event_type) === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Kunde</label>
                <select name="customer_id">
                    <option value="">— kein Kunde —</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id', $occurrence->customer_id) == $customer->id)>
                            {{ $customer->company_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Rechnungskunde</label>
                <select name="billing_customer_id">
                    <option value="">— wie Kunde —</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('billing_customer_id', $occurrence->billing_customer_id) == $customer->id)>
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
                        <option value="{{ $s->id }}" @selected(old('event_series_id', $occurrence->event_series_id) == $s->id)>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Angebotsstatus</label>
                <select name="offer_status">
                    @foreach([
                        'requested'              => 'Anfrage',
                        'needs_clarification'    => 'Klärung nötig',
                        'draft'                  => 'Entwurf',
                        'external_offer_created' => 'Ext. Angebot erstellt',
                        'sent'                   => 'Angebot versendet',
                        'revision_requested'     => 'Überarbeitung angefordert',
                        'accepted'               => 'Angenommen',
                        'rejected'               => 'Abgelehnt',
                        'expired'                => 'Abgelaufen',
                        'cancelled'              => 'Storniert',
                        'converted_to_order'     => 'In Bestellung umgewandelt',
                    ] as $s => $label)
                        <option value="{{ $s }}" @selected(old('offer_status', $occurrence->offer_status) === $s)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Veranstaltungsstatus</label>
                <select name="event_status">
                    @foreach([
                        'planned'               => 'Geplant',
                        'confirmed'             => 'Bestätigt',
                        'delivery_planned'      => 'Lieferung geplant',
                        'partially_delivered'   => 'Teilgeliefert',
                        'delivered'             => 'Geliefert',
                        'during_event'          => 'Laufend',
                        'pickup_planned'        => 'Abholung geplant',
                        'picked_up'             => 'Abgeholt',
                        'post_calculation_open' => 'Nachkalkulation offen',
                        'closed'                => 'Abgeschlossen',
                    ] as $s => $label)
                        <option value="{{ $s }}" @selected(old('event_status', $occurrence->event_status) === $s)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Beginn</label>
                <input type="datetime-local" name="event_start_at"
                    value="{{ old('event_start_at', $occurrence->event_start_at?->format('Y-m-d\TH:i')) }}">
            </div>

            <div class="form-group">
                <label>Ende</label>
                <input type="datetime-local" name="event_end_at"
                    value="{{ old('event_end_at', $occurrence->event_end_at?->format('Y-m-d\TH:i')) }}">
            </div>

            <div class="form-group">
                <label>Ort</label>
                <input type="text" name="location_name" value="{{ old('location_name', $occurrence->location_name) }}" maxlength="255">
            </div>

            <div class="form-group">
                <label>Stadt</label>
                <input type="text" name="city" value="{{ old('city', $occurrence->city) }}" maxlength="100">
            </div>

            <div class="form-group">
                <label>Straße</label>
                <input type="text" name="address_line1" value="{{ old('address_line1', $occurrence->address_line1) }}" maxlength="255">
            </div>

            <div class="form-group">
                <label>PLZ</label>
                <input type="text" name="postal_code" value="{{ old('postal_code', $occurrence->postal_code) }}" maxlength="20">
            </div>

            <div class="form-group">
                <label>Erwartete Gäste</label>
                <input type="number" name="expected_guests" value="{{ old('expected_guests', $occurrence->expected_guests) }}" min="0">
            </div>

            <div class="form-group">
                <label>Tatsächliche Gäste</label>
                <input type="number" name="actual_guests" value="{{ old('actual_guests', $occurrence->actual_guests) }}" min="0">
            </div>

            <div class="form-group">
                <label style="display:flex;gap:6px;align-items:center;cursor:pointer">
                    <input type="hidden" name="needs_review" value="0">
                    <input type="checkbox" name="needs_review" value="1" @checked(old('needs_review', $occurrence->needs_review))>
                    Review nötig
                </label>
            </div>

            <div class="form-group" style="grid-column:1/-1">
                <label>Interne Notizen</label>
                <textarea name="internal_notes" rows="3">{{ old('internal_notes', $occurrence->internal_notes) }}</textarea>
            </div>

            <div class="form-group" style="grid-column:1/-1">
                <label>Kundennotizen</label>
                <textarea name="customer_visible_notes" rows="3">{{ old('customer_visible_notes', $occurrence->customer_visible_notes) }}</textarea>
            </div>
        </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="{{ route('admin.events.show', $occurrence) }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>

@endsection
