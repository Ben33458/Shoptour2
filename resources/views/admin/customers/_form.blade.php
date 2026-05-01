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
        <label>Firma</label>
        <input type="text" name="company_name"
               value="{{ old('company_name', $customer?->company_name) }}"
               placeholder="Firmenname (optional)">
    </div>

    <div class="form-group">
        <label>Telefon</label>
        <input type="tel" name="phone"
               value="{{ old('phone', $customer?->phone) }}"
               placeholder="+49 123 456789">
    </div>

    <div class="form-group">
        <label>Geburtsdatum</label>
        <input type="date" name="birth_date"
               value="{{ old('birth_date', $customer?->birth_date?->format('Y-m-d')) }}"
               max="{{ date('Y-m-d') }}">
        <div class="hint">Optional. Für Jugendschutz-Dokumentation.</div>
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
</div>

<div class="form-row">
    <div class="form-group">
        <label>E-Mail (Login & Bestellbestätigung)</label>
        <input type="email" name="email"
               value="{{ old('email', $customer?->email) }}">
    </div>

    <div class="form-group">
        <label>Rechnungs-E-Mail</label>
        <input type="email" name="billing_email"
               value="{{ old('billing_email', $customer?->billing_email) }}"
               placeholder="Leer = Haupt-E-Mail wird verwendet">
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Versandbenachrichtigung-E-Mail</label>
        <input type="email" name="notification_email"
               value="{{ old('notification_email', $customer?->notification_email) }}"
               placeholder="Leer = Haupt-E-Mail wird verwendet">
    </div>

    <div class="form-group">
        <label>Newsletter-Einwilligung</label>
        <select name="newsletter_consent">
            <option value="all" @selected(old('newsletter_consent', $customer?->newsletter_consent ?? 'important_only') === 'all')>
                Alle E-Mails
            </option>
            <option value="important_only" @selected(old('newsletter_consent', $customer?->newsletter_consent ?? 'important_only') === 'important_only')>
                Nur wichtige Infos
            </option>
            <option value="none" @selected(old('newsletter_consent', $customer?->newsletter_consent ?? 'important_only') === 'none')>
                Keine Newsletter
            </option>
        </select>
    </div>
</div>

<div class="form-row">
    <div class="form-group" style="display:flex;align-items:center;gap:10px;padding-top:24px">
        <input type="hidden" name="email_notification_shipping" value="0">
        <input type="checkbox" name="email_notification_shipping" value="1" id="email_notif_cb"
               @checked(old('email_notification_shipping', $customer?->email_notification_shipping ?? true))>
        <label for="email_notif_cb" style="margin:0;cursor:pointer">Versandbenachrichtigungen per E-Mail senden</label>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Preisanzeige <span style="color:var(--c-danger)">*</span></label>
        <select name="price_display_mode" required>
            <option value="brutto" @selected(old('price_display_mode', $customer?->price_display_mode ?? 'brutto') === 'brutto')>
                Bruttopreise
            </option>
            <option value="netto" @selected(old('price_display_mode', $customer?->price_display_mode ?? 'brutto') === 'netto')>
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

<div class="form-row">
    <div class="form-group">
        <label>Herkunft (Firma)</label>
        <select name="kunde_von">
            <option value="">– unbekannt –</option>
            <option value="kolabri" @selected(old('kunde_von', $customer?->kunde_von) === 'kolabri')>Kolabri Getränke</option>
            <option value="kehr"    @selected(old('kunde_von', $customer?->kunde_von) === 'kehr')>Getränke Kehr</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label>Lieferhinweis (Fahrer)</label>
    <textarea name="delivery_note" rows="2"
              placeholder="z.B. Hintereingang, Klingelschild Müller">{{ old('delivery_note', $customer?->delivery_note) }}</textarea>
</div>

@include('admin._partials.contacts_widget', ['contacts' => $customer?->contacts ?? collect()])
