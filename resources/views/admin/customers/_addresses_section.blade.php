{{--
    Admin address management section for a Customer.
    Rendered OUTSIDE the main customer form (separate POST/PUT/DELETE actions).
    Variables: $customer (Customer with loaded addresses)
--}}
<div class="card" style="margin-top:24px" id="addresses-section">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
        <span>Adressen</span>
        <button type="button" class="btn btn-sm" onclick="addrOpenAdd()">+ Adresse hinzufügen</button>
    </div>
    <div class="card-body" style="padding:0">

        @if($customer->addresses->isEmpty())
            <p style="padding:16px 20px;color:var(--c-muted);margin:0">Noch keine Adressen hinterlegt.</p>
        @else
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:var(--c-bg-alt,#f8f8f8);font-size:.8rem;color:var(--c-muted)">
                        <th style="padding:8px 16px;text-align:left;font-weight:600">Typ</th>
                        <th style="padding:8px 16px;text-align:left;font-weight:600">Adresse</th>
                        <th style="padding:8px 16px;text-align:left;font-weight:600">Hinweis</th>
                        <th style="padding:8px 16px;text-align:right;font-weight:600">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customer->addresses as $addr)
                    <tr style="border-top:1px solid var(--c-border,#eee)">
                        <td style="padding:10px 16px;white-space:nowrap">
                            <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:600;
                                {{ $addr->type === 'delivery' ? 'background:#e0f0ff;color:#1a6fb5' : 'background:#f0e0ff;color:#7a1ab5' }}">
                                {{ $addr->type === 'delivery' ? 'Lieferung' : 'Rechnung' }}
                            </span>
                            @if($addr->is_default)
                                <span style="display:inline-block;margin-left:4px;padding:2px 6px;border-radius:4px;font-size:.7rem;background:#fff8e0;color:#b58a00">★ Standard</span>
                            @endif
                            @if($addr->label)
                                <div style="font-size:.78rem;color:var(--c-muted);margin-top:2px">{{ $addr->label }}</div>
                            @endif
                        </td>
                        <td style="padding:10px 16px">
                            @if($addr->company)<div style="font-weight:500">{{ $addr->company }}</div>@endif
                            @if($addr->first_name || $addr->last_name)
                                <div>{{ trim($addr->first_name . ' ' . $addr->last_name) }}</div>
                            @endif
                            <div>{{ $addr->street }}{{ $addr->house_number ? ' ' . $addr->house_number : '' }}</div>
                            <div>{{ $addr->zip }} {{ $addr->city }}</div>
                            @if($addr->phone)<div style="color:var(--c-muted);font-size:.85rem">{{ $addr->phone }}</div>@endif
                        </td>
                        <td style="padding:10px 16px;color:var(--c-muted);font-size:.85rem">
                            {{ $addr->delivery_note ?? '—' }}
                        </td>
                        <td style="padding:10px 16px;text-align:right;white-space:nowrap">
                            @if(!$addr->is_default)
                                <form method="POST" action="{{ route('admin.customers.addresses.setDefault', [$customer, $addr]) }}"
                                      style="display:inline" onsubmit="return confirm('Als Standard setzen?')">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm">★</button>
                                </form>
                            @endif
                            <button type="button" class="btn btn-sm"
                                    onclick="addrOpenEdit({{ $addr->id }}, {{ json_encode($addr) }})"
                                    style="margin-left:4px">Bearbeiten</button>
                            <form method="POST" action="{{ route('admin.customers.addresses.destroy', [$customer, $addr]) }}"
                                  style="display:inline;margin-left:4px"
                                  onsubmit="return confirm('Adresse wirklich löschen?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- ─── Address modal ──────────────────────────────────────────────────────── --}}
<div id="addr-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:900;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:100%;max-width:520px;max-height:90vh;overflow-y:auto;margin:16px">
        <div style="padding:20px 24px;border-bottom:1px solid var(--c-border,#eee);display:flex;align-items:center;justify-content:space-between">
            <h3 id="addr-modal-title" style="margin:0;font-size:1rem">Adresse</h3>
            <button type="button" onclick="addrCloseModal()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--c-muted)">✕</button>
        </div>

        <form id="addr-form" method="POST" style="padding:20px 24px">
            @csrf
            <input type="hidden" name="_method" id="addr-form-method" value="POST">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <label style="display:flex;align-items:center;gap:8px;border:1px solid var(--c-border,#ddd);border-radius:6px;padding:8px 12px;cursor:pointer">
                    <input type="radio" name="type" value="delivery" id="addr-type-delivery" required> Lieferadresse
                </label>
                <label style="display:flex;align-items:center;gap:8px;border:1px solid var(--c-border,#ddd);border-radius:6px;padding:8px 12px;cursor:pointer">
                    <input type="radio" name="type" value="billing" id="addr-type-billing"> Rechnungsadresse
                </label>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div class="form-group" style="margin:0">
                    <label>Bezeichnung (optional)</label>
                    <input type="text" name="label" id="addr-label" placeholder="z.B. Lager, Büro">
                </div>
                <div class="form-group" style="margin:0">
                    <label>Firma (optional)</label>
                    <input type="text" name="company" id="addr-company">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div class="form-group" style="margin:0">
                    <label>Vorname</label>
                    <input type="text" name="first_name" id="addr-first-name">
                </div>
                <div class="form-group" style="margin:0">
                    <label>Nachname</label>
                    <input type="text" name="last_name" id="addr-last-name">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:3fr 1fr;gap:12px;margin-bottom:12px">
                <div class="form-group" style="margin:0">
                    <label>Straße <span style="color:var(--c-danger)">*</span></label>
                    <input type="text" name="street" id="addr-street" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Nr.</label>
                    <input type="text" name="house_number" id="addr-house-number">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;margin-bottom:12px">
                <div class="form-group" style="margin:0">
                    <label>PLZ <span style="color:var(--c-danger)">*</span></label>
                    <input type="text" name="zip" id="addr-zip" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Ort <span style="color:var(--c-danger)">*</span></label>
                    <input type="text" name="city" id="addr-city" required>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div class="form-group" style="margin:0">
                    <label>Telefon</label>
                    <input type="tel" name="phone" id="addr-phone">
                </div>
                <div class="form-group" style="margin:0">
                    <label>Lieferhinweis</label>
                    <input type="text" name="delivery_note" id="addr-delivery-note"
                           placeholder="z.B. Hintereingang">
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px">
                <input type="checkbox" name="is_default" id="addr-is-default" value="1">
                <label for="addr-is-default" style="margin:0;cursor:pointer">Als Standardadresse setzen</label>
            </div>

            <div style="display:flex;gap:8px">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <button type="button" onclick="addrCloseModal()" class="btn btn-outline">Abbrechen</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    const storeUrl = '{{ route('admin.customers.addresses.store', $customer) }}';

    function show(el) { el.style.display = 'flex'; }
    function hide(el) { el.style.display = 'none'; }

    function clearForm() {
        ['label','company','first-name','last-name','street','house-number','zip','city','phone','delivery-note'].forEach(function (f) {
            const el = document.getElementById('addr-' + f);
            if (el) el.value = '';
        });
        document.getElementById('addr-is-default').checked = false;
        document.getElementById('addr-type-delivery').checked = true;
        document.getElementById('addr-type-billing').checked  = false;
    }

    window.addrOpenAdd = function () {
        document.getElementById('addr-modal-title').textContent = 'Adresse hinzufügen';
        document.getElementById('addr-form').action = storeUrl;
        document.getElementById('addr-form-method').value = 'POST';
        clearForm();
        show(document.getElementById('addr-modal'));
    };

    window.addrOpenEdit = function (id, addr) {
        document.getElementById('addr-modal-title').textContent = 'Adresse bearbeiten';
        const base = storeUrl.replace(/\/addresses$/, '/addresses/' + id);
        document.getElementById('addr-form').action = base;
        document.getElementById('addr-form-method').value = 'PUT';
        document.getElementById('addr-type-delivery').checked = (addr.type === 'delivery');
        document.getElementById('addr-type-billing').checked  = (addr.type === 'billing');
        document.getElementById('addr-label').value         = addr.label         || '';
        document.getElementById('addr-company').value       = addr.company       || '';
        document.getElementById('addr-first-name').value    = addr.first_name    || '';
        document.getElementById('addr-last-name').value     = addr.last_name     || '';
        document.getElementById('addr-street').value        = addr.street        || '';
        document.getElementById('addr-house-number').value  = addr.house_number  || '';
        document.getElementById('addr-zip').value           = addr.zip           || '';
        document.getElementById('addr-city').value          = addr.city          || '';
        document.getElementById('addr-phone').value         = addr.phone         || '';
        document.getElementById('addr-delivery-note').value = addr.delivery_note || '';
        document.getElementById('addr-is-default').checked  = !!addr.is_default;
        show(document.getElementById('addr-modal'));
    };

    window.addrCloseModal = function () {
        hide(document.getElementById('addr-modal'));
    };

    document.getElementById('addr-modal').addEventListener('click', function (e) {
        if (e.target === this) addrCloseModal();
    });
})();
</script>
