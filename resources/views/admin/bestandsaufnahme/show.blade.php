@extends('admin.layout')

@section('title', 'Bestandsaufnahme — ' . $session->warehouse->name)

@section('actions')
    @if(!$session->isAbgeschlossen())
        <form method="POST" action="{{ route('admin.bestandsaufnahme.pause', $session) }}" style="display:inline">
            @csrf
            <button class="btn btn-secondary">Pausieren</button>
        </form>
        <form method="POST" action="{{ route('admin.bestandsaufnahme.close', $session) }}" style="display:inline"
              onsubmit="return confirm('Session wirklich abschließen? Danach sind keine Änderungen mehr möglich.')">
            @csrf
            <button class="btn btn-danger">Abschließen</button>
        </form>
    @endif
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Status-Bar --}}
<div style="display:flex;gap:16px;align-items:center;margin-bottom:16px;padding:12px;background:#f8f9fa;border-radius:6px">
    <div><strong>Lager:</strong> {{ $session->warehouse->name }}</div>
    <div><strong>Status:</strong>
        @if($session->isOffen()) <span class="badge badge-success">Offen</span>
        @elseif($session->isPausiert()) <span class="badge badge-warning">Pausiert</span>
        @else <span class="badge badge-secondary">Abgeschlossen</span>
        @endif
    </div>
    <div><strong>Gestartet:</strong> {{ $session->gestartet_am->format('d.m.Y H:i') }}</div>
    <div><strong>Gezählt:</strong> {{ $gezaehlt->count() }} Positionen</div>
</div>

{{-- Filter --}}
<form method="GET" action="{{ route('admin.bestandsaufnahme.show', $session) }}">
    <div class="filter-bar" style="flex-wrap:wrap;gap:8px">
        <div class="form-group">
            <label>Lieferant</label>
            <select name="lieferant_id">
                <option value="">Alle Lieferanten</option>
                @foreach($lieferanten as $l)
                    <option value="{{ $l->id }}" @selected($lieferantId == $l->id)>{{ $l->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Filter</label>
            <select name="filter">
                <option value="alle" @selected($filter === 'alle')>Alle Artikel</option>
                <option value="fehlbestand" @selected($filter === 'fehlbestand')>Nur Fehlbestand</option>
                <option value="negativ" @selected($filter === 'negativ')>Nur negative Bestände</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary" style="align-self:flex-end;margin-bottom:1px">Filtern</button>
    </div>
</form>

{{-- Artikelliste --}}
<div id="artikel-liste">
@forelse($products as $product)
    @php
        $stock      = $product->stocks->first();
        $istBestand = $stock?->quantity ?? 0;
        $vpes       = $product->verpackungseinheiten;
        $minbestand = $product->mindestbestaende->first();
        $stdLieferant = $product->supplierProducts->firstWhere('ist_standard_lieferant', true)?->supplier
                     ?? $product->supplierProducts->first()?->supplier;
        $letzteZaehlung = $gezaehlt[$product->id] ?? null;
    @endphp

    <div class="card artikel-karte" id="artikel-{{ $product->id }}" style="margin-bottom:12px;padding:16px;border:1px solid #dee2e6;border-radius:6px">
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">

            {{-- Kopfzeile --}}
            <div style="flex:1;min-width:250px">
                <div style="display:flex;gap:8px;align-items:center">
                    <strong>{{ $product->artikelnummer }}</strong>
                    <span>{{ $product->produktname }}</span>
                    @if($letzteZaehlung)
                        <span class="badge badge-success" title="Gezählt am {{ \Carbon\Carbon::parse($letzteZaehlung)->format('d.m.Y H:i') }}">✓ gezählt</span>
                    @endif
                </div>
                <div style="font-size:12px;color:#6c757d;margin-top:4px">
                    Lieferant: {{ $stdLieferant?->name ?? '—' }}
                    &nbsp;|&nbsp;
                    Ist: <strong>{{ number_format($istBestand, 2, ',', '.') }}</strong>
                    @if($minbestand)
                        &nbsp;|&nbsp; Min: {{ number_format($minbestand->mindestbestand_basiseinheit, 2, ',', '.') }}
                        @if($istBestand < $minbestand->mindestbestand_basiseinheit)
                            <span class="badge badge-danger" style="font-size:10px">Fehlbestand</span>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Eingabefelder --}}
            @if(!$session->isAbgeschlossen())
            <div style="flex:2;min-width:320px">
                <form class="position-form" data-product-id="{{ $product->id }}">
                    @csrf

                    @if($vpes->isNotEmpty())
                        <div class="vpe-inputs" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px">
                            @foreach($vpes as $vpe)
                            <div style="display:flex;flex-direction:column;min-width:130px">
                                <label style="font-size:11px;color:#6c757d;margin-bottom:2px">{{ $vpe->bezeichnung }}</label>
                                <input
                                    type="number"
                                    class="form-control vpe-input"
                                    name="vpe_{{ $vpe->id }}"
                                    data-vpe-id="{{ $vpe->id }}"
                                    data-faktor="{{ $vpe->faktor_basiseinheit }}"
                                    value="0"
                                    min="0"
                                    step="0.001"
                                    style="width:130px"
                                    @if($session->isAbgeschlossen()) disabled @endif
                                >
                            </div>
                            @endforeach
                        </div>
                        {{-- Ergebnis live --}}
                        <div style="font-size:12px;color:#495057;margin-bottom:8px">
                            Gesamt Basiseinheit: <strong class="summe-anzeige">0,000</strong>
                        </div>
                    @else
                        {{-- Fallback: direkte Basiseinheit-Eingabe --}}
                        <div class="form-group" style="margin-bottom:8px">
                            <label style="font-size:12px">Bestand (Basiseinheit)</label>
                            <input
                                type="number"
                                class="form-control vpe-input"
                                name="vpe_basis"
                                data-vpe-id=""
                                data-faktor="1"
                                value="0"
                                min="-99999"
                                step="0.001"
                                style="width:160px"
                            >
                        </div>
                        <div style="font-size:12px;color:#495057;margin-bottom:8px">
                            Gesamt: <strong class="summe-anzeige">0,000</strong>
                        </div>
                    @endif

                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <select name="korrekturgrund" class="form-control" style="width:200px" required>
                            <option value="">— Korrekturgrund —</option>
                            @foreach($korrekturgründe as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="kommentar" class="form-control" placeholder="Kommentar (optional)" style="flex:1;min-width:150px">
                        <button type="submit" class="btn btn-primary btn-sm">Buchen</button>
                    </div>

                    <div class="position-feedback" style="margin-top:6px;font-size:12px"></div>
                </form>
            </div>
            @endif

        </div>
    </div>
@empty
    <p class="text-muted">Keine Artikel gefunden.</p>
@endforelse
</div>

{{ $products->links() }}

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Live-Summe berechnen
    document.querySelectorAll('.artikel-karte').forEach(function (karte) {
        const inputs  = karte.querySelectorAll('.vpe-input');
        const anzeige = karte.querySelector('.summe-anzeige');

        function updateSumme() {
            let sum = 0;
            inputs.forEach(function (inp) {
                const val    = parseFloat(inp.value) || 0;
                const faktor = parseFloat(inp.dataset.faktor) || 1;
                sum += val * faktor;
            });
            if (anzeige) anzeige.textContent = sum.toLocaleString('de-DE', {minimumFractionDigits: 3, maximumFractionDigits: 3});
        }

        inputs.forEach(inp => inp.addEventListener('input', updateSumme));
    });

    // AJAX-Speichern
    document.querySelectorAll('.position-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const produktId  = form.dataset.productId;
            const feedback   = form.querySelector('.position-feedback');
            const eingaben   = [];

            form.querySelectorAll('.vpe-input').forEach(function (inp) {
                const menge = parseFloat(inp.value) || 0;
                eingaben.push({
                    verpackungseinheit_id: inp.dataset.vpeId || null,
                    menge_vpe:             menge,
                    faktor_basiseinheit:   parseFloat(inp.dataset.faktor) || 1,
                });
            });

            const payload = {
                _token:          document.querySelector('meta[name=csrf-token]').content,
                product_id:      produktId,
                korrekturgrund:  form.querySelector('[name=korrekturgrund]').value,
                kommentar:       form.querySelector('[name=kommentar]').value,
                eingaben:        eingaben,
            };

            feedback.textContent = 'Wird gespeichert…';
            feedback.style.color = '#6c757d';

            fetch('{{ route('admin.bestandsaufnahme.save-position', $session) }}', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': payload._token },
                body:    JSON.stringify(payload),
            })
            .then(r => r.json())
            .then(function (data) {
                if (data.success) {
                    feedback.textContent = '✓ Gebucht – Bestand: ' + data.gezaehlter_bestand_basiseinheit.toLocaleString('de-DE', {minimumFractionDigits: 3}) + ' | Δ: ' + data.differenz_basiseinheit.toLocaleString('de-DE', {minimumFractionDigits: 3});
                    feedback.style.color = '#28a745';
                    const karte = form.closest('.artikel-karte');
                    const badge = karte.querySelector('.badge-success[title]');
                    if (badge) badge.textContent = '✓ gezählt';
                } else {
                    feedback.textContent = data.error ?? 'Fehler beim Speichern.';
                    feedback.style.color = '#dc3545';
                }
            })
            .catch(function () {
                feedback.textContent = 'Netzwerkfehler.';
                feedback.style.color = '#dc3545';
            });
        });
    });
});
</script>
@endpush
