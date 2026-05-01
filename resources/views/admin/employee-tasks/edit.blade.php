@extends('admin.layout')

@section('title', 'Aufgabe bearbeiten')

@section('content')
<div class="page-header">
    <h1>Aufgabe bearbeiten</h1>
    <div class="page-actions">
        <a href="{{ route('admin.emp-tasks.show', $task) }}" class="btn btn-secondary">Abbrechen</a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $e) {{ $e }}<br> @endforeach
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.emp-tasks.update', $task) }}" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div>
                    <label class="form-label">Titel *</label>
                    <input type="text" name="title" value="{{ old('title', $task->title) }}" class="form-control" required maxlength="255">
                </div>
                <div>
                    <label class="form-label">Zugewiesen an</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">— Kein Mitarbeiter —</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" {{ old('assigned_to', $task->assigned_to) == $emp->id ? 'selected' : '' }}>
                                {{ $emp->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div>
                    <label class="form-label">Priorität *</label>
                    <select name="priority" class="form-control" required>
                        <option value="low" {{ old('priority', $task->priority) === 'low' ? 'selected' : '' }}>Niedrig</option>
                        <option value="medium" {{ old('priority', $task->priority) === 'medium' ? 'selected' : '' }}>Mittel</option>
                        <option value="high" {{ old('priority', $task->priority) === 'high' ? 'selected' : '' }}>Hoch</option>
                        <option value="urgent" {{ old('priority', $task->priority) === 'urgent' ? 'selected' : '' }}>Dringend</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-control" required>
                        <option value="open" {{ old('status', $task->status) === 'open' ? 'selected' : '' }}>Offen</option>
                        <option value="in_progress" {{ old('status', $task->status) === 'in_progress' ? 'selected' : '' }}>In Arbeit</option>
                        <option value="done" {{ old('status', $task->status) === 'done' ? 'selected' : '' }}>Erledigt</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Fällig am</label>
                    <input type="date" name="due_date" value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}" class="form-control">
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <label class="form-label">Kurzbeschreibung</label>
                <input type="text" name="description" value="{{ old('description', $task->description) }}" class="form-control" maxlength="500">
            </div>

            <div style="margin-bottom:1rem;">
                <label class="form-label">Detaillierte Beschreibung</label>
                <textarea name="body" class="form-control" rows="6">{{ old('body', $task->body) }}</textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div>
                    <label class="form-label">Übergeordnete Aufgabe</label>
                    <select name="parent_task_id" class="form-control">
                        <option value="">— Keine —</option>
                        @foreach($parentTasks as $pt)
                            <option value="{{ $pt->id }}" {{ old('parent_task_id', $task->parent_task_id) == $pt->id ? 'selected' : '' }}>
                                {{ $pt->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Abhängig von</label>
                    <select name="depends_on_task_id" class="form-control">
                        <option value="">— Keine —</option>
                        @foreach($parentTasks as $pt)
                            <option value="{{ $pt->id }}" {{ old('depends_on_task_id', $task->depends_on_task_id) == $pt->id ? 'selected' : '' }}>
                                {{ $pt->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Existing images --}}
            @if($task->images && count($task->images) > 0)
            <div style="margin-bottom:1rem;">
                <label class="form-label">Vorhandene Bilder</label>
                <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
                    @foreach($task->images as $img)
                        <a href="{{ asset('storage/' . $img) }}" target="_blank">
                            <img src="{{ asset('storage/' . $img) }}" style="width:80px;height:60px;object-fit:cover;border-radius:4px;border:1px solid var(--c-border,#e2e8f0);">
                        </a>
                    @endforeach
                </div>
                <div style="font-size:.75rem;color:var(--c-muted,#64748b);margin-top:.25rem;">Neue Bilder werden zu vorhandenen hinzugefügt.</div>
            </div>
            @endif

            <div style="margin-bottom:1.5rem;">
                <label class="form-label">Weitere Bilder anhängen</label>
                <input type="file" name="images[]" class="form-control" multiple accept="image/*">
            </div>

            <button type="submit" class="btn btn-primary">Speichern</button>
            <a href="{{ route('admin.emp-tasks.show', $task) }}" class="btn btn-secondary">Abbrechen</a>
        </form>
    </div>
</div>
@endsection
