@extends('admin.layout')

@section('title', isset($report) ? 'Schichtbericht bearbeiten' : 'Schichtbericht erstellen')

@section('content')
<div class="page-header">
    <h1>
        Schichtbericht —
        {{ $shift->employee->full_name }},
        {{ $shift->planned_start->format('d.m.Y') }},
        {{ $shift->planned_start->format('H:i') }}–{{ $shift->planned_end->format('H:i') }} Uhr
    </h1>
    @if(isset($report) && $report->is_submitted)
        <span class="badge badge-success" style="font-size:.9rem;padding:.4rem .75rem;">Abgeschlossen</span>
    @endif
</div>

<div class="card" style="max-width:800px;">
    <div class="card-body">
        @if(isset($report))
            <form method="POST" action="{{ route('admin.shifts.reports.update', $report) }}">
                @method('PATCH')
        @else
            <form method="POST" action="{{ route('admin.shifts.reports.store', $shift) }}">
        @endif
        @csrf

        {{-- Zusammenfassung --}}
        <div class="form-group" style="margin-bottom:1.25rem;">
            <label for="summary" style="display:block;font-weight:600;margin-bottom:.4rem;">Zusammenfassung</label>
            <textarea id="summary" name="summary" rows="5"
                      style="width:100%;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.6rem .75rem;font-size:.9rem;background:var(--c-surface,#fff);color:inherit;resize:vertical;">{{ old('summary', $report->summary ?? '') }}</textarea>
        </div>

        {{-- Kundenanzahl --}}
        <div class="form-group" style="margin-bottom:1.25rem;">
            <label for="customer_count" style="display:block;font-weight:600;margin-bottom:.4rem;">Kundenanzahl</label>
            <input type="number" id="customer_count" name="customer_count" min="0"
                   value="{{ old('customer_count', $report->customer_count ?? '') }}"
                   style="border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.6rem .75rem;font-size:.9rem;background:var(--c-surface,#fff);color:inherit;width:180px;">
        </div>

        {{-- Kassendifferenz --}}
        <div class="form-group" style="margin-bottom:1.25rem;">
            <label for="cash_difference" style="display:block;font-weight:600;margin-bottom:.4rem;">Kassendifferenz (€)</label>
            <input type="number" id="cash_difference" name="cash_difference" step="0.01"
                   value="{{ old('cash_difference', isset($report) ? $report->cash_difference : '') }}"
                   style="border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.6rem .75rem;font-size:.9rem;background:var(--c-surface,#fff);color:inherit;width:180px;">
            <small style="display:block;margin-top:.3rem;color:var(--c-muted,#64748b);">Positiv = Überschuss, Negativ = Fehlbetrag</small>
        </div>

        {{-- Vorfall-Level --}}
        <div class="form-group" style="margin-bottom:1.25rem;">
            <label style="display:block;font-weight:600;margin-bottom:.6rem;">Vorfall-Level</label>
            @foreach(['none' => 'Kein Vorfall', 'minor' => 'Kleinerer Vorfall', 'major' => 'Schwerwiegender Vorfall'] as $value => $label)
            <label style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;cursor:pointer;">
                <input type="radio" name="incident_level" value="{{ $value }}"
                       {{ old('incident_level', $report->incident_level ?? 'none') === $value ? 'checked' : '' }}
                       onchange="toggleIncidentNotes(this.value)">
                {{ $label }}
            </label>
            @endforeach
        </div>

        {{-- Vorfall-Details --}}
        <div id="incident-notes-wrapper" style="margin-bottom:1.25rem;{{ old('incident_level', $report->incident_level ?? 'none') === 'none' ? 'display:none;' : '' }}">
            <label for="incident_notes" style="display:block;font-weight:600;margin-bottom:.4rem;">Vorfall-Details</label>
            <textarea id="incident_notes" name="incident_notes" rows="4"
                      style="width:100%;border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.6rem .75rem;font-size:.9rem;background:var(--c-surface,#fff);color:inherit;resize:vertical;">{{ old('incident_notes', $report->incident_notes ?? '') }}</textarea>
        </div>

        {{-- Checklisten --}}
        @if($templates->count() > 0)
        <div style="margin-bottom:1.5rem;">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem;border-bottom:1px solid var(--c-border,#e2e8f0);padding-bottom:.5rem;">Checkliste</h3>
            @foreach($templates as $template)
            <div style="margin-bottom:1.25rem;">
                <div style="font-weight:600;font-size:.9rem;color:var(--c-muted,#64748b);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.6rem;">
                    {{ $template->name }}
                </div>
                @foreach($template->items as $item)
                @php
                    $isChecked = false;
                    if (isset($report)) {
                        $pivotItem = $report->checklistItems->firstWhere('id', $item->id);
                        $isChecked = $pivotItem && $pivotItem->pivot->is_checked;
                    }
                    $isChecked = old('checklist.' . $item->id, $isChecked ? '1' : '') === '1';
                @endphp
                <label style="display:flex;align-items:center;gap:.6rem;margin-bottom:.5rem;cursor:pointer;padding:.4rem .6rem;border-radius:6px;transition:background .15s;" onmouseover="this.style.background='var(--c-surface-hover,#f1f5f9)'" onmouseout="this.style.background=''">
                    <input type="checkbox" name="checklist[{{ $item->id }}]" value="1"
                           {{ $isChecked ? 'checked' : '' }}
                           style="width:16px;height:16px;cursor:pointer;accent-color:var(--c-primary,#2563eb);">
                    <span style="font-size:.9rem;">{{ $item->label }}</span>
                    @if($item->is_required)
                        <span style="font-size:.75rem;color:var(--c-danger,#dc2626);font-weight:600;">*</span>
                    @endif
                </label>
                @endforeach
            </div>
            @endforeach
        </div>
        @endif

        {{-- Actions --}}
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;padding-top:.5rem;border-top:1px solid var(--c-border,#e2e8f0);">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="{{ route('admin.shifts.reports.index', ['date' => $shift->planned_start->format('Y-m-d')]) }}" class="btn btn-secondary">Abbrechen</a>
        </div>

        </form>

        {{-- Abschließen-Button (separate form, only if report exists and not yet submitted) --}}
        @if(isset($report) && !$report->is_submitted)
        <form method="POST" action="{{ route('admin.shifts.reports.submit', $report) }}" style="margin-top:1rem;">
            @csrf
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('Schichtbericht wirklich abschließen? Dieser kann danach nicht mehr bearbeitet werden.')">
                Abschließen
            </button>
        </form>
        @endif
    </div>
</div>

@push('scripts')
<script>
function toggleIncidentNotes(value) {
    document.getElementById('incident-notes-wrapper').style.display = (value === 'none') ? 'none' : '';
}
// Init on page load
document.addEventListener('DOMContentLoaded', function() {
    var checked = document.querySelector('input[name="incident_level"]:checked');
    if (checked) toggleIncidentNotes(checked.value);
});
</script>
@endpush
@endsection
