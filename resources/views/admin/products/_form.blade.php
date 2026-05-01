{{--
    Shared product form partial.
    Variables:
      $product      – null for create, Product model for edit
      $brands       – Collection<Brand>
      $productLines – Collection<ProductLine>
      $categories   – Collection<Category>
      $gebindeList  – Collection<Gebinde>
      $taxRates     – Collection<TaxRate>
--}}

<div class="form-row">
    <div class="form-group">
        <label>Artikelnummer <span style="color:var(--c-danger)">*</span></label>
        <input type="text" name="artikelnummer" required
               value="{{ old('artikelnummer', $product?->artikelnummer) }}"
               placeholder="z.B. BEI-0001">
    </div>

    <div class="form-group" style="flex:2">
        <label>Produktname <span style="color:var(--c-danger)">*</span></label>
        <input type="text" name="produktname" id="produktname" required
               value="{{ old('produktname', $product?->produktname) }}"
               placeholder="z.B. Beiersdorfer Pils 0,5L">
    </div>
</div>

{{-- Marke und Produktlinie ausgeblendet (vereinfachtes Produktmodell) --}}
{{-- Hidden inputs preserve existing values for edit mode --}}
@if($product?->brand_id)
    <input type="hidden" name="brand_id" value="{{ $product->brand_id }}">
@endif
@if($product?->product_line_id)
    <input type="hidden" name="product_line_id" value="{{ $product->product_line_id }}">
@endif

<div class="form-row">
    <div class="form-group">
        <label>Kategorie</label>
        <select name="category_id">
            <option value="">— keine —</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}"
                    @selected(old('category_id', $product?->category_id) == $cat->id)>
                    {{ $cat->parent ? $cat->parent->name . ' → ' : '' }}{{ $cat->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Gebinde</label>
        <select name="gebinde_id" id="gebinde_id">
            <option value="">— keines —</option>
            @foreach($gebindeList as $g)
                <option value="{{ $g->id }}"
                    @selected(old('gebinde_id', $product?->gebinde_id) == $g->id)>
                    {{ $g->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Einheiten im Gebinde</label>
        <input type="number" name="gebinde_units" id="gebinde_units" min="1" max="9999" step="1"
               value="{{ old('gebinde_units', $product?->gebinde_units) }}"
               placeholder="z.B. 20">
        <div class="hint">Anzahl Flaschen/Dosen. 1 = Einzelflasche.</div>
    </div>

    <div class="form-group">
        <label>Inhalt/Einheit (L)</label>
        <input type="text" name="unit_volume_l" id="unit_volume_l"
               value="{{ old('unit_volume_l', $product?->unit_volume_ml ? number_format($product->unit_volume_ml / 1000, 3, ',', '') : '') }}"
               placeholder="z.B. 0,500">
        <div class="hint">Füllmenge einer Flasche/Dose in Liter.</div>
    </div>

    <div class="form-group">
        <label>Gesamtinhalt (L) <span style="color:var(--c-muted);font-weight:normal;font-size:.8em">— auto-berechnet</span></label>
        <input type="text" name="volume_l" id="volume_l"
               value="{{ old('volume_l', $product?->volume_ml ? number_format($product->volume_ml / 1000, 3, ',', '') : '') }}"
               placeholder="— aus Einheiten × Inhalt">
        <div class="hint" id="gebinde_preview" style="color:var(--c-success);font-weight:500"></div>
    </div>

    <div class="form-group">
        <label>Alkoholgehalt (% vol.)</label>
        <input type="number" name="alkoholgehalt_vol_percent" min="0" max="100" step="0.1"
               value="{{ old('alkoholgehalt_vol_percent', $product?->alkoholgehalt_vol_percent) }}"
               placeholder="z.B. 5.0 oder 40.0">
        <div class="hint">LMIV-Pflichtangabe ab 1,2 % vol. Leer lassen bei alkoholfreien Produkten.</div>
    </div>

    <div class="form-group">
        <label>Steuersatz</label>
        <select name="tax_rate_id" id="tax_rate_select">
            <option value="" data-factor="1.19">— kein Steuersatz —</option>
            @foreach($taxRates as $rate)
                <option value="{{ $rate->id }}"
                    data-factor="{{ 1 + $rate->rate_basis_points / 10000 }}"
                    @selected(old('tax_rate_id', $product?->tax_rate_id ?? $defaultTaxRateId ?? null) == $rate->id)>
                    {{ $rate->name }} ({{ number_format($rate->rate_basis_points / 100, 0) }} %)
                </option>
            @endforeach
        </select>
        <div class="hint">Wird für die automatische Bruttopreis-Berechnung verwendet.</div>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Netto-VK-Preis (€)</label>
        <input type="number" id="price_net_eur" name="base_price_net_eur" step="0.0001" min="0"
               value="{{ old('base_price_net_eur', $product ? number_format($product->base_price_net_milli / 1_000_000, 4, '.', '') : '') }}"
               placeholder="0.0000"
               oninput="priceFromNet(this)">
        <div class="hint">Netto eingeben → Brutto wird berechnet.</div>
    </div>
    <div class="form-group">
        <label>Brutto-VK-Preis (€)</label>
        <input type="number" id="price_gross_eur" name="base_price_gross_eur" step="0.01" min="0"
               value="{{ old('base_price_gross_eur', $product ? number_format($product->base_price_gross_milli / 1_000_000, 2, '.', '') : '') }}"
               placeholder="0.00"
               oninput="priceFromGross(this)">
        <div class="hint">Oder Brutto eingeben → Netto wird berechnet.</div>
    </div>

    <div class="form-group">
        <label>Verfügbarkeit <span style="color:var(--c-danger)">*</span></label>
        <select name="availability_mode" required>
            <option value="available"   @selected(old('availability_mode', $product?->availability_mode ?? 'available') === 'available')>Verfügbar</option>
            <option value="preorder"    @selected(old('availability_mode', $product?->availability_mode) === 'preorder')>Vorbestellung</option>
            <option value="out_of_stock"@selected(old('availability_mode', $product?->availability_mode) === 'out_of_stock')>Nicht vorrätig</option>
            <option value="stock_based" @selected(old('availability_mode', $product?->availability_mode) === 'stock_based')>Lagerabhängig</option>
            <option value="discontinued"@selected(old('availability_mode', $product?->availability_mode) === 'discontinued')>Eingestellt</option>
        </select>
    </div>

    <div class="form-group" style="display:flex;align-items:center;gap:10px;padding-top:24px">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" name="active" value="1" id="active_prod"
               @checked(old('active', $product?->active ?? true))>
        <label for="active_prod" style="margin:0;cursor:pointer">Aktiv</label>
    </div>
</div>

<script>
(function () {
    var units  = document.getElementById('gebinde_units');
    var unitL  = document.getElementById('unit_volume_l');
    var volL   = document.getElementById('volume_l');
    var prev   = document.getElementById('gebinde_preview');
    if (!units || !unitL || !volL || !prev) return;
    function parseL(val) {
        return parseFloat((val || '').replace(',', '.')) || 0;
    }
    function fmtL(l) {
        // Format with comma decimal, strip trailing zeros after 3 places
        var s = l.toFixed(3).replace('.', ',');
        return s.replace(/,?0+$/, '') || '0';
    }
    function update() {
        var u = parseInt(units.value) || 0;
        var l = parseL(unitL.value);
        if (u > 0 && l > 0) {
            var total = u * l;
            volL.value = fmtL(total);
            prev.textContent = u + ' × ' + fmtL(l) + ' L = ' + fmtL(total) + ' L';
        } else {
            prev.textContent = '';
        }
    }
    units.addEventListener('input', update);
    unitL.addEventListener('input', update);
    update();
})();

// ── Netto / Brutto mutual calculation ─────────────────────────────────────────
(function () {
    var netInput   = document.getElementById('price_net_eur');
    var grossInput = document.getElementById('price_gross_eur');
    var taxSel     = document.getElementById('tax_rate_select');
    if (!netInput || !grossInput || !taxSel) return;

    function getFactor() {
        var opt = taxSel.options[taxSel.selectedIndex];
        return parseFloat(opt?.dataset?.factor || 1.19);
    }

    window.priceFromNet = function (el) {
        var net = parseFloat(el.value);
        if (!isNaN(net) && net > 0) {
            grossInput.value = (net * getFactor()).toFixed(2);
        } else {
            grossInput.value = '';
        }
    };

    window.priceFromGross = function (el) {
        var gross = parseFloat(el.value);
        if (!isNaN(gross) && gross > 0) {
            netInput.value = (gross / getFactor()).toFixed(4);
        } else {
            netInput.value = '';
        }
    };

    // When tax rate changes, recalculate gross from net (net is the source of truth)
    taxSel.addEventListener('change', function () {
        if (netInput.value) {
            priceFromNet(netInput);
        } else if (grossInput.value) {
            priceFromGross(grossInput);
        }
    });
})();
</script>

