@extends('admin.layout')

@section('title', 'Zeitmodell bearbeiten')

@section('content')
<div class="card">
    <div class="card-header">Zeitmodell bearbeiten: {{ $timeModel->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rental.time-models.update', $timeModel) }}">
            @csrf @method('PUT')
            <div class="form-group">
                <label>Name <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $timeModel->name) }}" required maxlength="255">
            </div>
            <div class="form-group">
                <label>Regel-Typ <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="rule_type" class="form-control" value="{{ old('rule_type', $timeModel->rule_type) }}" required maxlength="50">
            </div>
            <div class="form-group">
                <label>Mindestdauer (Stunden) <span style="color:var(--c-danger)">*</span></label>
                <input type="number" name="min_duration_hours" class="form-control" value="{{ old('min_duration_hours', $timeModel->min_duration_hours) }}" required min="1">
            </div>
            <div class="form-group">
                <label>Metadaten (JSON)</label>
                <textarea name="metadata" class="form-control" rows="4">{{ old('metadata', $timeModel->metadata ? json_encode($timeModel->metadata, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="default_for_events" value="1" {{ old('default_for_events', $timeModel->default_for_events) ? 'checked' : '' }}>
                    Standard-Zeitmodell für Veranstaltungsbestellungen
                </label>
            </div>
            <div style="margin-top:24px;display:flex;gap:12px">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('admin.rental.time-models.index') }}" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection
