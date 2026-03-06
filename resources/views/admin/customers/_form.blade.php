{{--
    Shared form partial for Customer create/edit.
    Variables: $customer (null for create, Customer for edit), $customerGroups
--}}

<div class="form-row">
    <div class="form-group">
        <label>Kundengruppe <span style="color:var(--c-danger)">*</span></label>
        <select name="customer_group_id" required>
            <option value="">— wählen —</option>
            @foreach($customerGroups as $group)
                <option value="{{ $group->id }}"
                    @selected(old('customer_group_id', $customer?->customer_group_id) == $group->id)>
                    {{ $group->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Kundennummer</label>
        <input type="text" name="customer_number"
               value="{{ old('customer_number', $customer?->customer_number) }}"
               placeholder="Wird automatisch vergeben wenn leer">
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Vorname</label>
        <input type="text" name="first_name"
               value="{{ old('first_name', $customer?->first_name) }}">
    </div>

    <div class="form-group">
        <label>Nachname</label>
        <input type="text" name="last_name"
               value="{{ old('last_name', $customer?->last_name) }}">
    </div>

    <div class="form-group">
        <label>E-Mail</label>
        <input type="email" name="email"
               value="{{ old('email', $customer?->email) }}">
    </div>

    <div class="form-group">
        <label>Telefon</label>
        <input type="tel" name="phone"
               value="{{ old('phone', $customer?->phone) }}"
               placeholder="+49 123 456789">
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Preisanzeige <span style="color:var(--c-danger)">*</span></label>
        <select name="price_display_mode" required>
            <option value="gross" @selected(old('price_display_mode', $customer?->price_display_mode ?? 'gross') === 'gross')>
                Bruttopreise
            </option>
            <option value="net" @selected(old('price_display_mode', $customer?->price_display_mode) === 'net')>
                Nettopreise
            </option>
        </select>
    </div>

    <div class="form-group" style="display:flex;align-items:center;gap:10px;padding-top:24px">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" name="active" value="1" id="active_cb"
               @checked(old('active', $customer?->active ?? true))>
        <label for="active_cb" style="margin:0;cursor:pointer">Aktiv</label>
    </div>
</div>

<div class="form-group">
    <label>Lieferadresse</label>
    <textarea name="delivery_address_text" rows="3"
              placeholder="Straße, Hausnummer, PLZ, Ort">{{ old('delivery_address_text', $customer?->delivery_address_text) }}</textarea>
</div>

<div class="form-group">
    <label>Lieferhinweis (Fahrer)</label>
    <textarea name="delivery_note" rows="2"
              placeholder="z.B. Hintereingang, Klingelschild Müller">{{ old('delivery_note', $customer?->delivery_note) }}</textarea>
</div>

@include('admin._partials.contacts_widget', ['contacts' => $customer?->contacts ?? collect()])
