{{--
    Shared form partial for Supplier create/edit.
    Variable: $supplier (null for create, Supplier for edit)
--}}

@if($supplier?->id)
<div style="margin-bottom:14px;padding:8px 12px;background:var(--c-bg-alt,#f5f5f5);border-radius:6px;font-size:.85rem;color:var(--c-muted);display:flex;gap:16px;align-items:center">
    <span><strong>ID:</strong> <code style="font-size:.9rem;user-select:all">{{ $supplier->id }}</code></span>
    @if($supplier->lieferanten_nr)
    <span><strong>Lieferanten-Nr.:</strong> {{ $supplier->lieferanten_nr }}</span>
    @endif
    @if($supplier->ninox_lieferanten_id)
    <span><strong>Ninox-ID:</strong> {{ $supplier->ninox_lieferanten_id }}</span>
    @endif
</div>
@endif

<div class="form-row">
    <div class="form-group" style="flex:2">
        <label>Firmenname <span style="color:var(--c-danger)">*</span></label>
        <input type="text" name="name" required
               value="{{ old('name', $supplier?->name) }}"
               placeholder="z.B. Getränke Großhandel GmbH">
    </div>
    <div class="form-group">
        <label>Typ</label>
        <select name="type">
            <option value="supplier" @selected(old('type', $supplier?->type ?? 'supplier') === 'supplier')>
                Warenlieferant
            </option>
            <option value="partner" @selected(old('type', $supplier?->type) === 'partner')>
                Geschäftspartner (kein Warenlieferant)
            </option>
        </select>
        <div class="hint" style="margin-top:4px">Geschäftspartner erscheinen nicht in Einkaufsbestellungen.</div>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>E-Mail</label>
        <input type="email" name="email"
               value="{{ old('email', $supplier?->email) }}">
    </div>

    <div class="form-group">
        <label>Telefon</label>
        <input type="tel" name="phone"
               value="{{ old('phone', $supplier?->phone) }}"
               placeholder="+49 123 456789">
    </div>

    <div class="form-group">
        <label>Währung (ISO 4217)</label>
        <input type="text" name="currency" maxlength="3"
               value="{{ old('currency', $supplier?->currency ?? 'EUR') }}"
               placeholder="EUR" style="text-transform:uppercase">
    </div>
</div>

<div class="form-group">
    <label>Adresse</label>
    <textarea name="address" rows="3"
              placeholder="Straße, Hausnummer, PLZ, Ort">{{ old('address', $supplier?->address) }}</textarea>
</div>

<div class="form-group" style="display:flex;align-items:center;gap:10px">
    <input type="hidden" name="active" value="0">
    <input type="checkbox" name="active" value="1" id="active_sup"
           @checked(old('active', $supplier?->active ?? true))>
    <label for="active_sup" style="margin:0;cursor:pointer">Aktiv</label>
</div>

<div class="form-group" style="display:flex;align-items:center;gap:10px">
    <input type="hidden" name="po_filter_own_products" value="0">
    <input type="checkbox" name="po_filter_own_products" value="1" id="po_filter_own_products"
           @checked(old('po_filter_own_products', $supplier?->po_filter_own_products ?? false))>
    <label for="po_filter_own_products" style="margin:0;cursor:pointer">
        Einkauf: nur Lieferanten-Produkte anzeigen
        <span style="font-size:.8rem;color:var(--c-muted);font-weight:normal">
            (Produktsuche im Einkauf auf bereits bezogene Produkte begrenzen)
        </span>
    </label>
</div>

@include('admin._partials.contacts_widget', ['contacts' => $supplier?->contacts ?? collect()])
