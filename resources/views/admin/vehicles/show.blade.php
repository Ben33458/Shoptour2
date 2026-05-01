@extends('admin.layout')

@section('title', 'Fahrzeug: ' . $vehicle->internal_name)

@section('actions')
    <a href="{{ route('admin.vehicles.index') }}" class="btn btn-outline btn-sm">← Übersicht</a>
    <a href="{{ route('admin.vehicles.edit', $vehicle) }}" class="btn btn-primary btn-sm">Bearbeiten</a>
@endsection

@section('content')

{{-- Stammdaten --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>{{ $vehicle->internal_name }}</span>
        @if($vehicle->active)
            <span class="badge badge-delivered">aktiv</span>
        @else
            <span class="badge badge-cancelled">inaktiv</span>
        @endif
    </div>
    <div style="padding:20px">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
            <div>
                <div class="hint">Kennzeichen</div>
                <strong>{{ $vehicle->plate_number ?? '—' }}</strong>
            </div>
            <div>
                <div class="hint">Hersteller / Modell</div>
                <strong>{{ implode(' ', array_filter([$vehicle->manufacturer, $vehicle->model])) ?: '—' }}</strong>
            </div>
            <div>
                <div class="hint">Fahrzeugtyp</div>
                <strong>{{ $vehicle->vehicle_type ?? '—' }}</strong>
            </div>
            <div>
                <div class="hint">FIN / VIN</div>
                <code>{{ $vehicle->vin ?? '—' }}</code>
            </div>
            <div>
                <div class="hint">Erstzulassung</div>
                <strong>{{ $vehicle->first_registration?->format('d.m.Y') ?? '—' }}</strong>
            </div>
            <div>
                <div class="hint">Baujahr</div>
                <strong>{{ $vehicle->year ?? '—' }}</strong>
            </div>
            <div>
                <div class="hint">Standort</div>
                <strong>{{ $vehicle->location ?? '—' }}</strong>
            </div>
            <div>
                <div class="hint">Führerscheinklasse</div>
                <strong>{{ $vehicle->required_license_class ?? '—' }}</strong>
            </div>
            <div>
                <div class="hint">Sitzplätze</div>
                <strong>{{ $vehicle->seats ?? '—' }}</strong>
            </div>
        </div>

        @if($vehicle->notes)
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--c-border)">
                <div class="hint">Notizen</div>
                <p style="margin:4px 0 0;white-space:pre-line">{{ $vehicle->notes }}</p>
            </div>
        @endif
    </div>
</div>

{{-- Gewichte & Maße --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">Gewichte &amp; Maße</div>
    <div style="padding:20px">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
            <div>
                <div class="hint">Gesamtgewicht</div>
                <strong>{{ $vehicle->gross_vehicle_weight ? number_format($vehicle->gross_vehicle_weight) . ' kg' : '—' }}</strong>
            </div>
            <div>
                <div class="hint">Leergewicht</div>
                <strong>{{ $vehicle->empty_weight ? number_format($vehicle->empty_weight) . ' kg' : '—' }}</strong>
            </div>
            <div>
                <div class="hint">Nutzlast</div>
                <strong>{{ $vehicle->payload_weight ? number_format($vehicle->payload_weight) . ' kg' : '—' }}</strong>
            </div>
            <div>
                <div class="hint">Ladevolumen</div>
                <strong>{{ $vehicle->load_volume ? number_format($vehicle->load_volume) . ' l' : '—' }}</strong>
            </div>
            <div>
                <div class="hint">Ladelänge × Breite × Höhe</div>
                <strong>
                    {{ $vehicle->load_length ?? '?' }} ×
                    {{ $vehicle->load_width ?? '?' }} ×
                    {{ $vehicle->load_height ?? '?' }} cm
                </strong>
            </div>
            <div>
                <div class="hint">Max. Anhängelast</div>
                <strong>{{ $vehicle->max_trailer_load ? number_format($vehicle->max_trailer_load) . ' kg' : '—' }}</strong>
            </div>
            <div>
                <div class="hint">Max. VPE ohne Sackkarre</div>
                <strong>{{ $vehicle->max_vpe_without_hand_truck ?? '—' }}</strong>
            </div>
            <div>
                <div class="hint">Max. VPE mit Sackkarre</div>
                <strong>{{ $vehicle->max_vpe_with_hand_truck ?? '—' }}</strong>
            </div>
        </div>
        <div style="display:flex;gap:24px;margin-top:12px">
            <span>Anhängerkupplung: <strong>{{ $vehicle->trailer_hitch ? 'Ja' : 'Nein' }}</strong></span>
            <span>Kühlaggregat: <strong>{{ $vehicle->cooling_unit ? 'Ja' : 'Nein' }}</strong></span>
        </div>
    </div>
</div>

{{-- Wartung --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">Wartung</div>
    <div style="padding:20px">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
            @php
                $tuevDays = $vehicle->tuev_due_date ? now()->diffInDays($vehicle->tuev_due_date, false) : null;
            @endphp
            <div>
                <div class="hint">TÜV fällig</div>
                @if($vehicle->tuev_due_date)
                    <strong @if($tuevDays !== null && $tuevDays < 30) style="color:var(--c-danger)" @endif>
                        {{ $vehicle->tuev_due_date->format('d.m.Y') }}
                        @if($tuevDays !== null && $tuevDays < 30)
                            ({{ $tuevDays < 0 ? 'abgelaufen' : $tuevDays . ' Tage' }})
                        @endif
                    </strong>
                @else
                    <strong>—</strong>
                @endif
            </div>
            <div>
                <div class="hint">HU/AU fällig</div>
                <strong>{{ $vehicle->inspection_due_date?->format('d.m.Y') ?? '—' }}</strong>
            </div>
            <div>
                <div class="hint">Ölwechsel fällig</div>
                <strong>{{ $vehicle->oil_service_due_date?->format('d.m.Y') ?? '—' }}</strong>
            </div>
            <div>
                <div class="hint">Nächster Service bei</div>
                <strong>{{ $vehicle->next_service_km ? number_format($vehicle->next_service_km) . ' km' : '—' }}</strong>
            </div>
            <div>
                <div class="hint">Aktueller Kilometerstand</div>
                <strong>{{ $vehicle->current_mileage ? number_format($vehicle->current_mileage) . ' km' : '—' }}</strong>
            </div>
        </div>
    </div>
</div>

{{-- Dokumente --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">Dokumente</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Typ</th>
                    <th>Titel</th>
                    <th>Gültig bis</th>
                    <th>Datei</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($vehicle->documents ?? [] as $doc)
                <tr>
                    <td><code>{{ $doc->document_type }}</code></td>
                    <td>{{ $doc->title }}</td>
                    <td>{{ $doc->valid_until?->format('d.m.Y') ?? '—' }}</td>
                    <td>
                        @if($doc->file_path)
                            <a href="{{ $doc->file_path }}" target="_blank" class="btn btn-outline btn-sm">Download</a>
                        @else
                            <span style="color:var(--c-muted)">—</span>
                        @endif
                    </td>
                    <td style="text-align:right">
                        <form method="POST"
                              action="{{ route('admin.vehicles.documents.destroy', [$vehicle, $doc]) }}"
                              style="display:inline"
                              onsubmit="return confirm('Dokument wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm"
                                    style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--c-muted);padding:16px">
                        Noch keine Dokumente hochgeladen.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Dokument hochladen --}}
    <div style="padding:20px;border-top:1px solid var(--c-border)">
        <div style="font-weight:600;margin-bottom:12px">Dokument hochladen</div>
        <form method="POST" action="{{ route('admin.vehicles.documents.store', $vehicle) }}"
              enctype="multipart/form-data">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px">
                <div class="form-group">
                    <label>Dokumenttyp *</label>
                    <select name="document_type" class="form-control" required>
                        <option value="">— Typ wählen —</option>
                        <option value="fahrzeugschein">Fahrzeugschein</option>
                        <option value="pruefbericht">Prüfbericht</option>
                        <option value="versicherung">Versicherung</option>
                        <option value="hauptuntersuchung">Hauptuntersuchung</option>
                        <option value="sonstiges">Sonstiges</option>
                    </select>
                    @error('document_type')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Titel</label>
                    <input type="text" name="title" value="{{ old('title') }}"
                           class="form-control" maxlength="255" placeholder="Bezeichnung">
                    @error('title')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Gültig bis</label>
                    <input type="date" name="valid_until" value="{{ old('valid_until') }}"
                           class="form-control">
                    @error('valid_until')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div class="form-group">
                    <label>Datei *</label>
                    <input type="file" name="file" class="form-control" required>
                    @error('file')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Notizen</label>
                    <input type="text" name="notes" value="{{ old('notes') }}"
                           class="form-control" maxlength="500">
                    @error('notes')<div class="hint" style="color:var(--c-danger)">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Dokument speichern</button>
        </form>
    </div>
</div>

{{-- Offene Mängel --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Offene Mängel</span>
        <a href="{{ route('admin.assets.issues.create', ['asset_type' => 'vehicle', 'asset_id' => $vehicle->id]) }}"
           class="btn btn-primary btn-sm">+ Mangel erfassen</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Priorität</th>
                    <th>Titel</th>
                    <th>Status</th>
                    <th>Fällig</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($vehicle->assetIssues ?? [] as $issue)
                <tr>
                    <td>
                        @php
                            $prioClass = match($issue->priority) {
                                'critical' => 'badge-cancelled',
                                'high'     => 'badge-cancelled',
                                'medium'   => 'badge-pending',
                                default    => 'badge-default',
                            };
                        @endphp
                        <span class="badge {{ $prioClass }}">{{ $issue->priority }}</span>
                    </td>
                    <td>{{ $issue->title }}</td>
                    <td><span class="badge badge-pending">{{ $issue->status }}</span></td>
                    <td>{{ $issue->due_date?->format('d.m.Y') ?? '—' }}</td>
                    <td style="text-align:right">
                        <a href="{{ route('admin.assets.issues.edit', $issue) }}"
                           class="btn btn-outline btn-sm">Bearbeiten</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--c-muted);padding:16px">
                        Keine offenen Mängel.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Aktionen --}}
<div style="display:flex;gap:8px;margin-top:8px">
    <a href="{{ route('admin.vehicles.edit', $vehicle) }}" class="btn btn-primary">Bearbeiten</a>
    <form method="POST" action="{{ route('admin.vehicles.destroy', $vehicle) }}"
          onsubmit="return confirm('Fahrzeug \"{{ addslashes($vehicle->internal_name) }}\" wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-outline" style="color:var(--c-danger)">Fahrzeug löschen</button>
    </form>
</div>

@endsection
