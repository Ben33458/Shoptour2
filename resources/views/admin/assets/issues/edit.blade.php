@extends('admin.layout')

@section('title', 'Mangel bearbeiten')

@section('content')
<div class="card">
    <div class="card-header">Mangel #{{ $issue->id }} bearbeiten</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.assets.issues.update', $issue) }}">
            @csrf @method('PUT')

            <div class="form-group">
                <label>Asset</label>
                <input type="text" class="form-control" value="{{ $issue->asset_type === 'vehicle' ? 'Fahrzeug' : 'Mieteinheit' }} #{{ $issue->asset_id }}" disabled>
            </div>

            <div class="form-group">
                <label>Titel <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $issue->title) }}" required maxlength="255">
            </div>

            <div class="form-group">
                <label>Beschreibung</label>
                <textarea name="description" class="form-control" rows="4">{{ old('description', $issue->description) }}</textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Priorität <span style="color:var(--c-danger)">*</span></label>
                    <select name="priority" class="form-control" required>
                        @foreach(['low' => 'Niedrig', 'medium' => 'Mittel', 'high' => 'Hoch', 'critical' => 'Kritisch'] as $val => $label)
                            <option value="{{ $val }}" {{ old('priority', $issue->priority) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Status <span style="color:var(--c-danger)">*</span></label>
                    <select name="status" class="form-control" required id="statusSelect" onchange="toggleResolution()">
                        @foreach(['open' => 'Offen', 'scheduled' => 'Geplant', 'in_progress' => 'In Bearbeitung', 'resolved' => 'Behoben', 'closed' => 'Geschlossen'] as $val => $label)
                            <option value="{{ $val }}" {{ old('status', $issue->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Schwere <span style="color:var(--c-danger)">*</span></label>
                    <select name="severity" class="form-control" required>
                        @foreach(['minor' => 'Gering', 'moderate' => 'Mittel', 'major' => 'Schwer'] as $val => $label)
                            <option value="{{ $val }}" {{ old('severity', $issue->severity) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Zugewiesen an</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">– niemanden –</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('assigned_to', $issue->assigned_to) == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Geschätzte Kosten (€)</label>
                    <input type="number" name="estimated_cost_eur" class="form-control"
                           value="{{ old('estimated_cost_eur', $issue->estimated_cost_milli ? number_format($issue->estimated_cost_milli / 1000000, 2, '.', '') : '') }}"
                           step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Werkstatt</label>
                    <input type="text" name="workshop_name" class="form-control" value="{{ old('workshop_name', $issue->workshop_name) }}" maxlength="255">
                </div>
            </div>

            <div class="form-group">
                <label>Fällig bis</label>
                <input type="date" name="due_date" class="form-control" value="{{ old('due_date', $issue->due_date?->format('Y-m-d')) }}">
            </div>

            <div id="resolutionNotes" style="display:none">
                <div class="form-group">
                    <label>Behebungsnotiz</label>
                    <textarea name="resolution_notes" class="form-control" rows="3">{{ old('resolution_notes', $issue->resolution_notes) }}</textarea>
                </div>
            </div>

            <div style="display:flex;gap:16px;margin-top:8px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="blocks_usage" value="1" {{ old('blocks_usage', $issue->blocks_usage) ? 'checked' : '' }}>
                    Sperrt Nutzung
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="blocks_rental" value="1" {{ old('blocks_rental', $issue->blocks_rental) ? 'checked' : '' }}>
                    Sperrt Vermietung
                </label>
            </div>

            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.assets.issues.index') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleResolution() {
    const s = document.getElementById('statusSelect').value;
    document.getElementById('resolutionNotes').style.display = ['resolved','closed'].includes(s) ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleResolution);
</script>
@endsection
