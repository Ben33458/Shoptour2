@extends('admin.layout')

@section('title', 'Neues Zeitmodell')

@section('content')
<div class="card">
    <div class="card-header">Neues Zeitmodell anlegen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rental.time-models.store') }}">
            @csrf
            <div class="form-group">
                <label>Name <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="255">
            </div>
            <div class="form-group">
                <label>Regel-Typ <span style="color:var(--c-danger)">*</span></label>
                <input type="text" name="rule_type" class="form-control" value="{{ old('rule_type') }}" required maxlength="50"
                       placeholder="z.B. weekend, week, event, extension">
            </div>
            <div class="form-group">
                <label>Mindestdauer (Stunden) <span style="color:var(--c-danger)">*</span></label>
                <input type="number" name="min_duration_hours" class="form-control" value="{{ old('min_duration_hours', 24) }}" required min="1">
            </div>
            <div class="form-group">
                <label>Metadaten (JSON)</label>
                <textarea name="metadata" class="form-control" rows="4" placeholder='{"description": "..."}'>{{ old('metadata') }}</textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="default_for_events" value="1" {{ old('default_for_events') ? 'checked' : '' }}>
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
