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

<div class="form-row">
    <div class="form-group">
        <label>Marke</label>
        <select name="brand_id" id="brand_id">
            <option value="">— keine —</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->id }}"
                    @selected(old('brand_id', $product?->brand_id) == $brand->id)>
                    {{ $brand->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label>Produktlinie</label>
        <select name="product_line_id" id="product_line_id">
            <option value="">— keine —</option>
            @foreach($productLines as $pl)
                <option value="{{ $pl->id }}"
                    data-brand="{{ $pl->brand_id }}"
                    data-gebinde="{{ $pl->gebinde_id }}"
                    data-brand-name="{{ $pl->brand?->name }}"
                    data-line-name="{{ $pl->name }}"
                    @selected(old('product_line_id', $product?->product_line_id) == $pl->id)>
                    {{ $pl->brand?->name ? $pl->brand->name . ' — ' : '' }}{{ $pl->name }}
                </option>
            @endforeach
        </select>
    </div>

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
        <label>Steuersatz</label>
        <select name="tax_rate_id">
            <option value="">— kein Steuersatz —</option>
            @foreach($taxRates as $rate)
                <option value="{{ $rate->id }}"
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
        <label>Netto-Preis (€) <span style="color:var(--c-danger)">*</span></label>
        <input type="number" name="base_price_net_eur" step="0.01" min="0" required
               value="{{ old('base_price_net_eur', $product ? number_format($product->base_price_net_milli / 1_000_000, 2, '.', '') : '') }}"
               placeholder="0.00">
        <div class="hint">Bruttopreis wird automatisch anhand des Steuersatzes berechnet.</div>
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
    const plSelect   = document.getElementById('product_line_id');
    const brandSel   = document.getElementById('brand_id');
    const gebindeSel = document.getElementById('gebinde_id');
    const nameInput  = document.getElementById('produktname');

    if (!plSelect) return;

    plSelect.addEventListener('change', function () {
        const opt = plSelect.options[plSelect.selectedIndex];

        // Auto-fill Marke
        const brandId = opt.dataset.brand;
        if (brandId && brandSel) {
            brandSel.value = brandId;
            // Trigger cascading product-line filter if present
            brandSel.dispatchEvent(new Event('change'));
        }

        // Auto-fill Gebinde (only if not already chosen by user)
        const gebindeId = opt.dataset.gebinde;
        if (gebindeId && gebindeSel && !gebindeSel.value) {
            gebindeSel.value = gebindeId;
        }

        // Auto-generate Produktname if field is still empty
        if (nameInput && !nameInput.value.trim()) {
            const brandName = opt.dataset.brandName || '';
            const lineName  = opt.dataset.lineName  || '';
            nameInput.value = brandName
                ? brandName + ' ' + lineName
                : lineName;
        }
    });

    // Filter product-line options by brand when brand changes
    if (brandSel) {
        brandSel.addEventListener('change', function () {
            const selectedBrand = brandSel.value;
            Array.from(plSelect.options).forEach(function (o) {
                if (!o.value) return; // keep "— keine —"
                o.hidden = selectedBrand ? (o.dataset.brand !== selectedBrand) : false;
            });
            // Reset product line if it no longer matches
            if (selectedBrand && plSelect.value) {
                const cur = plSelect.options[plSelect.selectedIndex];
                if (cur && cur.dataset.brand !== selectedBrand) {
                    plSelect.value = '';
                }
            }
        });
    }
})();
</script>
