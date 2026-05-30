@extends('mein.layout')

@section('title', 'Schichtbericht nachtragen')

@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:8px;">
    <div>
        <h1 style="font-size:1.3rem; font-weight:700; margin:0;">Schichtbericht nachtragen</h1>
    </div>
    <a href="{{ route('mein.schichtberichte') }}" style="font-size:.85rem; color:var(--c-muted); text-decoration:none;">← Alle Berichte</a>
</div>

{{-- Date picker --}}
<div class="mein-card" style="padding:.75rem 1rem; margin-bottom:1rem;">
    <form method="GET" action="{{ route('mein.schicht.nachtrag') }}" style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;">
        <label style="font-size:.85rem; font-weight:600; color:var(--c-text); white-space:nowrap;">Datum wählen:</label>
        <input type="date" name="date" value="{{ $date }}"
               max="{{ today()->toDateString() }}"
               style="padding:.35rem .7rem; border:1px solid var(--c-border); border-radius:6px; font-size:.9rem; background:var(--c-bg); color:var(--c-text);">
        <button type="submit" class="btn btn-secondary btn-sm">Laden</button>
        @if($shift)
            <span style="font-size:.85rem; color:var(--c-muted);">
                {{ \Carbon\Carbon::parse($date)->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
                @if($shift->shiftArea) · {{ $shift->shiftArea->name }} @endif
            </span>
        @else
            <span style="font-size:.85rem; color:var(--c-muted);">{{ \Carbon\Carbon::parse($date)->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}</span>
        @endif
    </form>
</div>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:1rem;">
    @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
</div>
@endif

<div class="mein-card">
    <form method="POST" action="{{ route('mein.schicht.nachtrag.save') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">

        <div class="form-group" style="margin-bottom:16px;">
            <label>Wie lief die Schicht?</label>
            <textarea name="summary" rows="8"
                      placeholder="Besonderheiten, Übergabe-Infos…">{{ old('summary', $report?->summary) }}</textarea>
        </div>

        <div class="form-group" style="margin-bottom:16px;">
            <label>Kassendifferenz (€)</label>
            <input type="number" name="cash_difference" step="0.01"
                   value="{{ old('cash_difference', $report?->cash_difference ?? '0') }}"
                   placeholder="0.00">
            <small style="color:var(--c-muted);">Positiv = Überschuss · Negativ = Fehlbetrag</small>
        </div>

        @if($reportTemplates->count() > 0)
        <div style="margin-bottom:16px;">
            <div class="mein-card-title">Checklisten</div>
            @foreach($reportTemplates as $template)
                <div style="margin-bottom:14px;">
                    <div style="font-size:.9rem; font-weight:600; color:var(--c-text); margin-bottom:6px;">{{ $template->name }}</div>
                    @foreach($template->items as $item)
                        @php
                            $isChecked = $report?->checklistItems->contains('id', $item->id)
                                ? (bool)($report->checklistItems->firstWhere('id', $item->id)?->pivot?->is_checked)
                                : false;
                        @endphp
                        <label style="display:flex; align-items:center; gap:10px; padding:.45rem .5rem; border-radius:6px; cursor:pointer;"
                               onmouseover="this.style.background='var(--c-bg)'"
                               onmouseout="this.style.background=''">
                            <input type="checkbox" name="checklist[{{ $item->id }}]" value="1"
                                   style="width:15px;height:15px;accent-color:var(--c-primary);flex-shrink:0;"
                                   {{ $isChecked ? 'checked' : '' }}>
                            <span style="font-size:.9rem;">{{ $item->label }}</span>
                        </label>
                    @endforeach
                </div>
            @endforeach
        </div>
        @endif

        <details style="margin-bottom:16px;">
            <summary style="cursor:pointer; font-size:.9rem; font-weight:600; color:var(--c-muted); padding:.5rem 0; user-select:none;">
                ▸ Vorfall melden
            </summary>
            <div style="margin-top:10px; padding:14px; background:var(--c-bg); border-radius:8px; border:1px solid var(--c-border);">
                <div class="form-group" style="margin-bottom:12px;">
                    <label>Vorfall-Stufe</label>
                    <select name="incident_level">
                        <option value="0" {{ old('incident_level', $report?->incident_level ?? 0) == 0 ? 'selected' : '' }}>Kein Vorfall</option>
                        <option value="1" {{ old('incident_level', $report?->incident_level) == 1 ? 'selected' : '' }}>Stufe 1 — Gering</option>
                        <option value="2" {{ old('incident_level', $report?->incident_level) == 2 ? 'selected' : '' }}>Stufe 2 — Mittel</option>
                        <option value="3" {{ old('incident_level', $report?->incident_level) == 3 ? 'selected' : '' }}>Stufe 3 — Schwerwiegend</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Beschreibung</label>
                    <textarea name="incident_notes" rows="3"
                              placeholder="Was ist passiert?">{{ old('incident_notes', $report?->incident_notes) }}</textarea>
                </div>
            </div>
        </details>

        <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">Speichern & Abschließen</button>
            <a href="{{ route('mein.schichtberichte') }}" class="btn btn-outline">Abbrechen</a>
        </div>
    </form>
</div>

@endsection
