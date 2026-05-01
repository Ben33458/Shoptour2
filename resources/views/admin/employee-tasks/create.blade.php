@extends('admin.layout')

@section('title', 'Neue Aufgabe erstellen')

@section('content')
<div class="page-header">
    <h1>Neue Aufgabe</h1>
    <div class="page-actions">
        <a href="{{ route('admin.emp-tasks.index') }}" class="btn btn-secondary">Abbrechen</a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $e) {{ $e }}<br> @endforeach
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.emp-tasks.store') }}" enctype="multipart/form-data">
            @csrf

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div>
                    <label class="form-label">Titel *</label>
                    <input type="text" name="title" value="{{ old('title') }}" class="form-control" required maxlength="255">
                </div>
                <div>
                    <label class="form-label">Zugewiesen an</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">— Kein Mitarbeiter —</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" {{ old('assigned_to') == $emp->id ? 'selected' : '' }}>
                                {{ $emp->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div>
                    <label class="form-label">Priorität *</label>
                    <select name="priority" class="form-control" required>
                        <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Niedrig</option>
                        <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>Mittel</option>
                        <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>Hoch</option>
                        <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Dringend</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Fällig am</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}" class="form-control">
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <label class="form-label">Kurzbeschreibung</label>
                <input type="text" name="description" value="{{ old('description') }}" class="form-control" maxlength="500" placeholder="Kurze Zusammenfassung (max. 500 Zeichen)">
            </div>

            <div style="margin-bottom:1rem;">
                <label class="form-label">Detaillierte Beschreibung</label>
                <textarea name="body" class="form-control" rows="6" placeholder="Genaue Anleitung, Hintergrundinformationen, Checklisten…">{{ old('body') }}</textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div>
                    <label class="form-label">Übergeordnete Aufgabe (optional)</label>
                    <select name="parent_task_id" class="form-control">
                        <option value="">— Keine —</option>
                        @foreach($parentTasks as $pt)
                            <option value="{{ $pt->id }}" {{ old('parent_task_id') == $pt->id ? 'selected' : '' }}>
                                {{ $pt->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Abhängig von (optional)</label>
                    <select name="depends_on_task_id" class="form-control">
                        <option value="">— Keine Abhängigkeit —</option>
                        @foreach($parentTasks as $pt)
                            <option value="{{ $pt->id }}" {{ old('depends_on_task_id') == $pt->id ? 'selected' : '' }}>
                                {{ $pt->title }}
                            </option>
                        @endforeach
                    </select>
                    <div style="font-size:.75rem;color:var(--c-muted,#64748b);margin-top:.25rem;">Diese Aufgabe soll erst erledigt werden, nachdem die abhängige Aufgabe abgeschlossen ist.</div>
                </div>
            </div>

            <div style="margin-bottom:1.5rem;">
                <label class="form-label">Bilder anhängen (optional)</label>
                <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                <div style="font-size:.75rem;color:var(--c-muted,#64748b);margin-top:.25rem;">Mehrere Bilder auswählbar. Max. 5 MB pro Bild.</div>
            </div>

            <button type="submit" class="btn btn-primary">Aufgabe erstellen</button>
        </form>
    </div>
</div>
@endsection
