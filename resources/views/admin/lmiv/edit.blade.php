@extends('admin.layout')

@section('title', 'LMIV – ' . $product->artikelnummer)

@section('actions')
    <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline btn-sm">
        ← Produkt
    </a>
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline btn-sm">
        Alle Produkte
    </a>
@endsection

@section('content')

@php
    $data = $editVersion?->data_json ?? [];
    $d    = fn(string $key, mixed $default = '') => $data[$key] ?? $default;
@endphp

{{-- ── Version status banner ── --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-body" style="font-size:.9em;display:flex;gap:20px;align-items:center;flex-wrap:wrap">
        <div>
            <span style="color:var(--c-muted)">Produkt:</span>
            <strong>{{ $product->artikelnummer }}</strong> – {{ $product->produktname }}
        </div>
        @if($editVersion)
            <div>
                <span style="color:var(--c-muted)">Bearbeitete Version:</span>
                <strong>v{{ $editVersion->version_number }}</strong>
                <span class="badge {{ $editVersion->isActive() ? 'badge-success' : ($editVersion->isDraft() ? 'badge-warning' : '') }}">
                    {{ $editVersion->statusLabel() }}
                </span>
            </div>
            @if($editVersion->ean)
            <div>
                <span style="color:var(--c-muted)">Aktive EAN:</span>
                <code>{{ $editVersion->ean }}</code>
            </div>
            @endif
        @else
            <div class="alert alert-warning" style="margin:0">
                Keine LMIV-Version vorhanden. Speichern erstellt die erste Version.
            </div>
        @endif

        {{-- Draft activate button --}}
        @if($draftVersion)
            <form method="POST"
                  action="{{ route('admin.lmiv.activate', [$product, $draftVersion]) }}"
                  style="margin:0">
                @csrf
                <button type="submit" class="btn btn-success btn-sm"
                        onclick="return confirm('Entwurf v{{ $draftVersion->version_number }} aktivieren?')">
                    Entwurf aktivieren
                </button>
            </form>
        @endif

        {{-- New manual draft --}}
        <form method="POST" action="{{ route('admin.lmiv.new-version', $product) }}" style="margin:0">
            @csrf
            <input type="hidden" name="change_reason" value="Manueller Entwurf">
            <button type="submit" class="btn btn-outline btn-sm">
                + Neuer Entwurf
            </button>
        </form>
    </div>
</div>

{{-- ── OFF-data review notice ── --}}
@if(($editVersion?->data_json['source'] ?? null) === 'open_food_facts')
<div class="alert alert-warning" style="margin-bottom:16px">
    ⚠️ Diese LMIV-Daten wurden automatisch von <strong>Open Food Facts</strong> importiert.
    Bitte alle Felder (insbesondere Hersteller, Herkunftsland und Zutaten) gegen das echte Etikett prüfen, bevor der Entwurf aktiviert wird.
</div>
@endif

{{-- ── EAN-Change form ── --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">🔖 EAN ändern (löst Versions-Rollover aus)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.lmiv.update-ean', $product) }}"
              onsubmit="return confirm('EAN ändern und neue LMIV-Version erstellen?')">
            @csrf
            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                <div class="form-group" style="margin:0">
                    <label class="form-label">Neue EAN</label>
                    <input type="text" name="ean" class="form-control"
                           placeholder="z.B. 4006381333931"
                           value="{{ $editVersion?->ean ?? '' }}" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Änderungsgrund (optional)</label>
                    <input type="text" name="change_reason" class="form-control"
                           placeholder="z.B. Neue Rezeptur / Neues Gebinde" style="min-width:260px">
                </div>
                <button type="submit" class="btn btn-warning btn-sm">EAN speichern</button>
            </div>
        </form>
    </div>
</div>

{{-- ── LMIV data form ── --}}
<form method="POST" action="{{ route('admin.lmiv.update', $product) }}">
    @csrf

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px">

        {{-- ── Allgemeine Angaben ── --}}
        <div class="card">
            <div class="card-header">📋 Allgemeine Angaben</div>
            <div class="card-body">

                <div class="form-group">
                    <label class="form-label">Produktname (auf Etikett)</label>
                    <input type="text" name="lmiv[produktname]" class="form-control"
                           value="{{ old('lmiv.produktname', $d('produktname')) }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Hersteller</label>
                    <input type="text" name="lmiv[hersteller]" class="form-control"
                           value="{{ old('lmiv.hersteller', $d('hersteller')) }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Herstelleranschrift</label>
                    <textarea name="lmiv[herstelleranschrift]" class="form-control" rows="2">{{ old('lmiv.herstelleranschrift', $d('herstelleranschrift')) }}</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Nettofüllmenge</label>
                    <input type="text" name="lmiv[nettofuellmenge]" class="form-control"
                           placeholder="z.B. 0,5 l" value="{{ old('lmiv.nettofuellmenge', $d('nettofuellmenge')) }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Alkoholgehalt (% vol)</label>
                    <input type="number" name="lmiv[alkoholgehalt]" class="form-control"
                           step="0.1" min="0" max="100"
                           value="{{ old('lmiv.alkoholgehalt', $d('alkoholgehalt')) }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Herkunftsland</label>
                    <input type="text" name="lmiv[herkunftsland]" class="form-control"
                           value="{{ old('lmiv.herkunftsland', $d('herkunftsland')) }}">
                </div>
            </div>
        </div>

        {{-- ── Zutaten & Allergene ── --}}
        <div class="card">
            <div class="card-header">🌾 Zutaten &amp; Allergene</div>
            <div class="card-body">

                <div class="form-group">
                    <label class="form-label">Zutaten</label>
                    <textarea name="lmiv[zutaten]" class="form-control" rows="5">{{ old('lmiv.zutaten', $d('zutaten')) }}</textarea>
                    <small class="text-muted">Allergene im Text mit GROSSBUCHSTABEN hervorheben (EU-Konvention).</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Allergene (Kurzliste)</label>
                    <input type="text" name="lmiv[allergene]" class="form-control"
                           placeholder="z.B. Enthält Gluten, Sulfite"
                           value="{{ old('lmiv.allergene', $d('allergene')) }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Lagerhinweis</label>
                    <input type="text" name="lmiv[lagerhinweis]" class="form-control"
                           placeholder="z.B. Kühl und trocken lagern"
                           value="{{ old('lmiv.lagerhinweis', $d('lagerhinweis')) }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Weitere Informationen</label>
                    <textarea name="lmiv[zusatzinfos]" class="form-control" rows="2">{{ old('lmiv.zusatzinfos', $d('zusatzinfos')) }}</textarea>
                </div>
            </div>
        </div>

        {{-- ── Nährwerte je 100 ml/g ── --}}
        <div class="card">
            <div class="card-header">🔬 Nährwerte je 100 ml / 100 g</div>
            <div class="card-body">

                @php
                    $nwFields = [
                        'nw_energie_kj'      => 'Energie (kJ)',
                        'nw_energie_kcal'    => 'Energie (kcal)',
                        'nw_fett'            => 'Fett (g)',
                        'nw_fett_gesaettigt' => 'davon gesättigte Fettsäuren (g)',
                        'nw_kohlenhydrate'   => 'Kohlenhydrate (g)',
                        'nw_zucker'          => 'davon Zucker (g)',
                        'nw_ballaststoffe'   => 'Ballaststoffe (g)',
                        'nw_eiweiss'         => 'Eiweiß (g)',
                        'nw_salz'            => 'Salz (g)',
                    ];
                @endphp

                @foreach($nwFields as $key => $label)
                    <div class="form-group">
                        <label class="form-label">{{ $label }}</label>
                        <input type="number" name="lmiv[{{ $key }}]" class="form-control"
                               step="0.01" min="0"
                               value="{{ old("lmiv.{$key}", $d($key)) }}">
                    </div>
                @endforeach

                {{-- Mineral water section --}}
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--c-border,#e5e7eb)">
                    <p class="form-label" style="font-weight:600;margin-bottom:8px">💧 Mineralien (Mineralwasser)</p>
                    @php
                        $mineralFields = [
                            'nw_natrium'          => 'Natrium (mg)',
                            'nw_calcium'          => 'Calcium (mg)',
                            'nw_magnesium'        => 'Magnesium (mg)',
                            'nw_hydrogencarbonat' => 'Hydrogencarbonat (mg)',
                            'nw_kalium'           => 'Kalium (mg)',
                            'nw_chlorid'          => 'Chlorid (mg)',
                            'nw_sulfat'           => 'Sulfat (mg)',
                            'nw_fluorid'          => 'Fluorid (mg)',
                            'nw_kieselsaeure'     => 'Kieselsäure (mg)',
                        ];
                    @endphp
                    @foreach($mineralFields as $key => $label)
                        <div class="form-group">
                            <label class="form-label">{{ $label }}</label>
                            <input type="number" name="lmiv[{{ $key }}]" class="form-control"
                                   step="0.01" min="0"
                                   value="{{ old("lmiv.{$key}", $d($key)) }}">
                        </div>
                    @endforeach
                </div>

            </div>
        </div>

    </div>

    <div style="margin-top:20px">
        <button type="submit" class="btn btn-primary">💾 LMIV-Daten speichern</button>
        <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>

{{-- ── Version history sidebar ── --}}
@if($allVersions->isNotEmpty())
<div class="card" style="margin-top:24px">
    <div class="card-header">📜 Versionshistorie</div>
    <div class="card-body" style="padding:0">
        <table class="table" style="font-size:.875em">
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Status</th>
                    <th>EAN</th>
                    <th>Gültig von</th>
                    <th>Gültig bis</th>
                    <th>Ersteller</th>
                    <th>Grund</th>
                </tr>
            </thead>
            <tbody>
            @foreach($allVersions as $ver)
                <tr {{ $editVersion && $editVersion->id === $ver->id ? 'style=background:var(--c-bg-alt,#f4f6f9)' : '' }}>
                    <td><strong>v{{ $ver->version_number }}</strong></td>
                    <td>
                        <span class="badge {{ $ver->isActive() ? 'badge-success' : ($ver->isDraft() ? 'badge-warning' : '') }}">
                            {{ $ver->statusLabel() }}
                        </span>
                    </td>
                    <td><code>{{ $ver->ean ?? '–' }}</code></td>
                    <td>{{ $ver->effective_from?->format('d.m.Y H:i') ?? '–' }}</td>
                    <td>{{ $ver->effective_to?->format('d.m.Y H:i') ?? '–' }}</td>
                    <td>{{ $ver->createdBy?->name ?? 'System' }}</td>
                    <td>{{ $ver->change_reason ?? '–' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
