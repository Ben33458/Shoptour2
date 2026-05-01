@extends('mein.layout')

@section('title', 'Schichtbericht')

@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:8px;">
    <div>
        <h1 style="font-size:1.3rem; font-weight:700; margin:0;">Schichtbericht</h1>
        <div style="font-size:13px; color:var(--c-muted); margin-top:2px;">
            {{ $todayShift->planned_start->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
            @if($todayShift->shiftArea)
                &nbsp;·&nbsp; {{ $todayShift->shiftArea->name }}
            @endif
        </div>
    </div>
    @if($report?->is_submitted)
        <span class="badge" style="background:#14532d;color:#4ade80;padding:.3rem .8rem;border-radius:6px;font-size:.8rem;font-weight:700;">
            Abgeschlossen
        </span>
    @endif
</div>

<div class="mein-card">
    <form method="POST" action="{{ route('mein.schicht.save') }}">
        @csrf
        <input type="hidden" name="shift_id" value="{{ $todayShift->id }}">

        {{-- Zusammenfassung --}}
        <div class="form-group" style="margin-bottom:16px;">
            <label>Wie lief die Schicht?</label>
            <textarea name="summary" rows="8"
                      placeholder="Wie lief die Schicht? Besonderheiten, Übergabe-Infos…"
                      {{ $report?->is_submitted ? 'readonly' : '' }}>{{ old('summary', $report?->summary) }}</textarea>
        </div>

        {{-- Kassendifferenz (Kundenzahl ausgeblendet) --}}
        <input type="hidden" name="customer_count" value="{{ old('customer_count', $report?->customer_count) }}">
        <div class="form-group" style="margin-bottom:16px;">
            <label>Kassendifferenz (€)</label>
            <input type="number" name="cash_difference" step="0.01"
                   value="{{ old('cash_difference', $report?->cash_difference) }}"
                   placeholder="0.00"
                   {{ $report?->is_submitted ? 'readonly' : '' }}>
            <small style="color:var(--c-muted);">Positiv = Überschuss · Negativ = Fehlbetrag</small>
        </div>

        {{-- Checklisten --}}
        @if($templates->count() > 0)
        <div style="margin-bottom:16px;">
            <div class="mein-card-title">Checklisten</div>
            @foreach($templates as $template)
                <div style="margin-bottom:14px;">
                    <div style="font-size:.9rem; font-weight:600; color:var(--c-text); margin-bottom:6px;">
                        {{ $template->name }}
                    </div>
                    @foreach($template->items as $item)
                        @php
                            $isChecked = $report?->checklistItems->contains('id', $item->id)
                                ? (bool)($report->checklistItems->firstWhere('id', $item->id)?->pivot?->is_checked)
                                : false;
                        @endphp
                        <label style="display:flex; align-items:center; gap:10px; padding:.45rem .5rem; border-radius:6px; cursor:pointer; transition:background .1s;"
                               onmouseover="this.style.background='var(--c-bg)'"
                               onmouseout="this.style.background=''">
                            <input type="checkbox"
                                   name="checklist[{{ $item->id }}]"
                                   value="1"
                                   style="width:15px;height:15px;accent-color:var(--c-primary);flex-shrink:0;"
                                   {{ $isChecked ? 'checked' : '' }}
                                   {{ $report?->is_submitted ? 'disabled' : '' }}>
                            <span style="font-size:.9rem;">{{ $item->label }}</span>
                        </label>
                    @endforeach
                </div>
            @endforeach
        </div>
        @endif

        {{-- Vorfall --}}
        <details {{ old('incident_level', $report?->incident_level ?? 'none') !== 'none' ? 'open' : '' }}
                 style="margin-bottom:16px;">
            <summary style="cursor:pointer; font-size:.9rem; font-weight:600; color:var(--c-muted); padding:.5rem 0; user-select:none; list-style:none; display:flex; align-items:center; gap:.5rem;">
                <span>▸</span> Vorfall melden
            </summary>
            <div style="margin-top:10px; padding:14px; background:var(--c-bg); border-radius:8px; border:1px solid var(--c-border);">
                <div class="form-group" style="margin-bottom:12px;">
                    <label>Vorfall-Stufe</label>
                    <select name="incident_level" {{ $report?->is_submitted ? 'disabled' : '' }}>
                        <option value="none"  {{ old('incident_level', $report?->incident_level ?? 'none') === 'none'  ? 'selected' : '' }}>Kein Vorfall</option>
                        <option value="minor" {{ old('incident_level', $report?->incident_level) === 'minor' ? 'selected' : '' }}>Gering</option>
                        <option value="major" {{ old('incident_level', $report?->incident_level) === 'major' ? 'selected' : '' }}>Schwerwiegend</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Beschreibung</label>
                    <textarea name="incident_notes" rows="3"
                              placeholder="Was ist passiert?"
                              {{ $report?->is_submitted ? 'readonly' : '' }}>{{ old('incident_notes', $report?->incident_notes) }}</textarea>
                </div>
            </div>
        </details>

        {{-- Hidden fallback for incident_level if details not opened --}}
        @unless($report?->is_submitted)
            <script>
            document.querySelector('form').addEventListener('submit', function() {
                if (!this.querySelector('[name="incident_level"]') || this.querySelector('[name="incident_level"]').disabled) {
                    var h = document.createElement('input');
                    h.type = 'hidden'; h.name = 'incident_level'; h.value = 'none';
                    this.appendChild(h);
                }
            });
            </script>

            <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-top:4px;">
                <button type="submit" name="submit" value="0" class="btn btn-primary">
                    Speichern
                </button>
                <button type="submit" name="submit" value="1" class="btn btn-success"
                        onclick="return confirm('Schichtbericht abschließen? Danach keine Änderungen mehr möglich.')">
                    Abschließen ✓
                </button>
                <a href="{{ route('mein.dashboard') }}" class="btn btn-secondary">Abbrechen</a>
            </div>
        @else
            <a href="{{ route('mein.dashboard') }}" class="btn btn-secondary">← Zurück</a>
        @endunless

    </form>
</div>

@unless($report?->is_submitted)
<script>
(function() {
    var form = document.querySelector('form');
    if (!form) return;
    var saveTimer = null;
    var indicator = document.createElement('div');
    indicator.id = 'autosave-indicator';
    indicator.style.cssText = 'position:fixed;bottom:16px;right:16px;background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px;padding:8px 14px;font-size:12px;color:var(--c-muted);display:none;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.15);';
    document.body.appendChild(indicator);

    function showIndicator(msg) {
        indicator.textContent = msg;
        indicator.style.display = 'block';
    }
    function hideIndicator() {
        indicator.style.display = 'none';
    }

    function autoSave() {
        var data = new FormData(form);
        data.set('submit', '0');
        data.delete('submit'); // don't accidentally submit
        // Build URLSearchParams from form fields (excluding files)
        var params = new URLSearchParams();
        params.set('_token', document.querySelector('[name=_token]').value);
        params.set('_method', '');
        ['shift_id','summary','customer_count','cash_difference','incident_level','incident_notes'].forEach(function(name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (el && !el.disabled) params.set(name, el.value || '');
        });
        // checklist
        form.querySelectorAll('[name^="checklist["]').forEach(function(cb) {
            if (cb.checked) params.append(cb.name, '1');
        });
        params.set('submit', '0');

        showIndicator('Speichert…');
        fetch(form.action, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('[name=_token]').value, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'},
            body: params.toString()
        }).then(function(r) {
            if (r.ok || r.redirected) {
                showIndicator('✓ Automatisch gespeichert');
                setTimeout(hideIndicator, 2000);
            } else {
                showIndicator('Fehler beim Speichern');
                setTimeout(hideIndicator, 3000);
            }
        }).catch(function() {
            hideIndicator();
        });
    }

    form.addEventListener('change', function() {
        clearTimeout(saveTimer);
        showIndicator('Änderungen erkannt…');
        saveTimer = setTimeout(autoSave, 10000);
    });
    form.addEventListener('input', function() {
        clearTimeout(saveTimer);
        showIndicator('Änderungen erkannt…');
        saveTimer = setTimeout(autoSave, 10000);
    });
})();
</script>
@endunless

@endsection
