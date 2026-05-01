@extends('admin.layout')

@section('title', 'Regeln — Ladenhüter & MHD')

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

{{-- Ladenhüter-Schwellenwerte --}}
<div class="card" style="padding:16px">
    <h3 style="margin-top:0">Ladenhüter-Schwellenwerte</h3>
    <form method="POST" action="{{ route('admin.ladenhueter.update-regel') }}">
        @csrf

        <div class="form-group">
            <label>Kein Verkauf seit (Tage)</label>
            <input type="number" name="tage_ohne_verkauf" value="{{ old('tage_ohne_verkauf', $ladenhueterRegel->tage_ohne_verkauf ?? 90) }}" min="1" class="form-control" style="width:120px">
        </div>

        <div class="form-group">
            <label>Maximale Lagerdauer (Tage)</label>
            <input type="number" name="max_lagerdauer_tage" value="{{ old('max_lagerdauer_tage', $ladenhueterRegel->max_lagerdauer_tage ?? 180) }}" min="1" class="form-control" style="width:120px">
        </div>

        <div class="form-group">
            <label>Maximale Bestandsreichweite (Tage)</label>
            <input type="number" name="max_bestandsreichweite_tage" value="{{ old('max_bestandsreichweite_tage', $ladenhueterRegel->max_bestandsreichweite_tage ?? 180) }}" min="1" class="form-control" style="width:120px">
        </div>

        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>
</div>

{{-- MHD-Regel anlegen --}}
<div class="card" style="padding:16px">
    <h3 style="margin-top:0">MHD-Regel anlegen</h3>
    <p style="font-size:12px;color:#6c757d">Priorität: Artikel > Lager > Kategorie > Warengruppe > Default</p>
    <form method="POST" action="{{ route('admin.ladenhueter.store-mhd-regel') }}">
        @csrf

        <div class="form-group">
            <label>Bezugstyp <span class="text-danger">*</span></label>
            <select name="bezug_typ" id="bezug_typ" class="form-control" onchange="updateBezugId(this.value)">
                <option value="default">Default (global)</option>
                <option value="artikel">Artikel</option>
                <option value="lager">Lager</option>
                <option value="kategorie">Kategorie</option>
                <option value="warengruppe">Warengruppe</option>
            </select>
        </div>

        <div class="form-group" id="bezug_id_group" style="display:none">
            <label>Bezug</label>
            <select name="bezug_id" id="bezug_id" class="form-control">
                <option value="">— wählen —</option>
            </select>
        </div>

        <div class="form-group">
            <label>Modus <span class="text-danger">*</span></label>
            <select name="modus" class="form-control">
                <option value="optional">Optional</option>
                <option value="pflichtig">Pflichtig</option>
                <option value="nie">Nie</option>
            </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div class="form-group">
                <label>Warngrenze (Tage)</label>
                <input type="number" name="warnung_tage" value="30" min="0" class="form-control">
            </div>
            <div class="form-group">
                <label>Kritisch (Tage)</label>
                <input type="number" name="kritisch_tage" value="14" min="0" class="form-control">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>
</div>

</div>

{{-- Bestehende MHD-Regeln --}}
<h3 style="margin-top:24px">Bestehende MHD-Regeln</h3>
<table class="table">
    <thead>
        <tr>
            <th>Bezugstyp</th>
            <th>Bezug-ID</th>
            <th>Modus</th>
            <th>Warnung</th>
            <th>Kritisch</th>
            <th>Aktiv</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach($mhdRegeln as $regel)
        <tr>
            <td><span class="badge badge-secondary">{{ $regel->bezug_typ }}</span></td>
            <td>{{ $regel->bezug_id ?? '—' }}</td>
            <td>
                @if($regel->modus === 'pflichtig') <span class="badge badge-danger">Pflichtig</span>
                @elseif($regel->modus === 'optional') <span class="badge badge-warning">Optional</span>
                @else <span class="badge badge-secondary">Nie</span>
                @endif
            </td>
            <td>{{ $regel->warnung_tage }} Tage</td>
            <td>{{ $regel->kritisch_tage }} Tage</td>
            <td>{{ $regel->aktiv ? '✓' : '—' }}</td>
            <td>
                @if($regel->bezug_typ !== 'default')
                <form method="POST" action="{{ route('admin.ladenhueter.destroy-mhd-regel', $regel) }}"
                      onsubmit="return confirm('Regel löschen?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Löschen</button>
                </form>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

@endsection

@push('scripts')
<script>
const bezugOptions = {
    artikel:     @json($products->map(fn($p) => ['id' => $p->id, 'label' => $p->artikelnummer . ' – ' . $p->produktname])),
    lager:       @json($warehouses->map(fn($w) => ['id' => $w->id, 'label' => $w->name])),
    kategorie:   @json($categories->map(fn($c) => ['id' => $c->id, 'label' => $c->name])),
    warengruppe: @json($warengruppen->map(fn($g) => ['id' => $g->id, 'label' => $g->name])),
};

function updateBezugId(typ) {
    const group  = document.getElementById('bezug_id_group');
    const select = document.getElementById('bezug_id');
    select.innerHTML = '<option value="">— wählen —</option>';

    if (typ === 'default') {
        group.style.display = 'none';
        return;
    }

    group.style.display = '';
    (bezugOptions[typ] || []).forEach(function (opt) {
        const o = document.createElement('option');
        o.value = opt.id;
        o.textContent = opt.label;
        select.appendChild(o);
    });
}
</script>
@endpush
