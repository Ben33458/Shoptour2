@extends('admin.layout')

@section('title', 'Ansicht — Shopeinstellungen')

@section('content')

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:16px;padding:12px 16px;background:var(--c-success-bg,#d1fae5);border:1px solid var(--c-success,#10b981);border-radius:6px;color:#065f46">
        {{ session('success') }}
    </div>
@endif

<form method="POST" action="{{ route('admin.settings.shop_display.update') }}">
    @csrf

    {{-- ── 1. Verfügbare Ansichten ─────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">Verfügbare Ansichten</div>
        <div style="padding:20px">
            <p style="font-size:13px;color:var(--c-muted);margin-bottom:16px">
                Bestimme, welche Ansichten Kunden im Shop auswählen können. Mindestens eine muss aktiv sein.
                Die <strong>Standardansicht</strong> wird für neue Kunden und Gäste verwendet.
            </p>

            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead>
                    <tr style="border-bottom:2px solid var(--c-border)">
                        <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--c-muted)">Aktiv</th>
                        <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--c-muted)">Ansicht</th>
                        <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--c-muted)">Standard</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($viewModes as $id => $label)
                    <tr style="border-bottom:1px solid var(--c-border)">
                        <td style="padding:10px 12px">
                            <input type="checkbox"
                                   name="available_views[]"
                                   value="{{ $id }}"
                                   id="view_{{ $id }}"
                                   {{ in_array($id, $settings['available_views']) ? 'checked' : '' }}>
                        </td>
                        <td style="padding:10px 12px">
                            <label for="view_{{ $id }}" style="cursor:pointer;font-weight:500">{{ $label }}</label>
                        </td>
                        <td style="padding:10px 12px">
                            <input type="radio"
                                   name="default_view"
                                   value="{{ $id }}"
                                   {{ $settings['default_view'] === $id ? 'checked' : '' }}>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── 2. Standard-Listeneinstellungen ────────────────────────────────── --}}
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">Standard-Listeneinstellungen</div>
        <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:20px">

            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Produkte pro Seite (Standard)</label>
                <select name="default_items_per_page" style="width:100%;padding:8px 10px;border:1px solid var(--c-border);border-radius:6px;font-size:13px">
                    @foreach($itemsPerPageOptions as $n)
                        <option value="{{ $n }}" {{ $settings['default_items_per_page'] == $n ? 'selected' : '' }}>{{ $n }} Produkte</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Standard-Sortierung</label>
                <select name="default_sort" style="width:100%;padding:8px 10px;border:1px solid var(--c-border);border-radius:6px;font-size:13px">
                    @foreach($sortOptions as $val => $label)
                        <option value="{{ $val }}" {{ $settings['default_sort'] === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

        </div>
    </div>

    {{-- ── 3. Produktkarten-Inhalte ─────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">Angezeigte Inhalte</div>
        <div style="padding:20px">
            <p style="font-size:13px;color:var(--c-muted);margin-bottom:16px">
                Diese Einstellungen gelten für alle Kunden und Gäste — unabhängig von deren persönlicher Ansichtswahl.
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:12px;border:1px solid var(--c-border);border-radius:6px">
                    <input type="checkbox" name="show_article_number" value="1" {{ $settings['show_article_number'] ? 'checked' : '' }} style="margin-top:2px">
                    <div>
                        <div style="font-size:13px;font-weight:600">Artikelnummer anzeigen</div>
                        <div style="font-size:12px;color:var(--c-muted)">Zeigt die interne Artikelnummer auf Produktkarten und in der Liste</div>
                    </div>
                </label>

                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:12px;border:1px solid var(--c-border);border-radius:6px">
                    <input type="checkbox" name="show_deposit_separately" value="1" {{ $settings['show_deposit_separately'] ? 'checked' : '' }} style="margin-top:2px">
                    <div>
                        <div style="font-size:13px;font-weight:600">Pfand separat ausweisen</div>
                        <div style="font-size:12px;color:var(--c-muted)">Zeigt den Pfandbetrag als eigene Zeile unter dem Produktpreis</div>
                    </div>
                </label>

                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:12px;border:1px solid var(--c-border);border-radius:6px">
                    <input type="checkbox" name="hide_unavailable" value="1" {{ $settings['hide_unavailable'] ? 'checked' : '' }} style="margin-top:2px">
                    <div>
                        <div style="font-size:13px;font-weight:600">Nicht lieferbare Produkte ausblenden</div>
                        <div style="font-size:12px;color:var(--c-muted)">Produkte ohne Lagerbestand werden im Shop nicht angezeigt (statt ausgegraut)</div>
                    </div>
                </label>

                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:12px;border:1px solid var(--c-border);border-radius:6px">
                    <input type="checkbox" name="show_stammsortiment_badge" value="1" {{ $settings['show_stammsortiment_badge'] ? 'checked' : '' }} style="margin-top:2px">
                    <div>
                        <div style="font-size:13px;font-weight:600">Stammsortiment-Badge anzeigen</div>
                        <div style="font-size:12px;color:var(--c-muted)">Hebt eigene Stammsortiment-Produkte mit einem Symbol hervor</div>
                    </div>
                </label>

                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:12px;border:1px solid var(--c-border);border-radius:6px">
                    <input type="checkbox" name="show_new_badge" value="1" {{ $settings['show_new_badge'] ? 'checked' : '' }} style="margin-top:2px">
                    <div>
                        <div style="font-size:13px;font-weight:600">„Neu"-Badge anzeigen</div>
                        <div style="font-size:12px;color:var(--c-muted)">Markiert Produkte, die in den letzten 30 Tagen hinzugefügt wurden</div>
                    </div>
                </label>

                <div style="padding:12px;border:1px solid var(--c-border);border-radius:6px">
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px">Produktbeschreibung</label>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        @foreach($descriptionModes as $val => $label)
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                            <input type="radio" name="description_mode" value="{{ $val }}" {{ $settings['description_mode'] === $val ? 'checked' : '' }}>
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Einstellungen speichern</button>

</form>

@endsection
