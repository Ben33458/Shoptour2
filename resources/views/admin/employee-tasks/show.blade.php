@extends('admin.layout')

@section('title', 'Aufgabe: ' . $task->title)

@section('content')
<div class="page-header">
    <h1 style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
        {{ $task->title }}
        @php $s = ['open'=>['Offen','secondary'], 'in_progress'=>['In Arbeit','warning'], 'done'=>['Erledigt','success']][$task->status] ?? ['?','secondary'] @endphp
        <span class="badge badge-{{ $s[1] }}" style="font-size:.75rem;">{{ $s[0] }}</span>
        @php $prio = ['urgent'=>['Dringend','danger'], 'high'=>['Hoch','warning'], 'medium'=>['Mittel','info'], 'low'=>['Niedrig','secondary']][$task->priority] ?? ['?','secondary'] @endphp
        <span class="badge badge-{{ $prio[1] }}" style="font-size:.75rem;">{{ $prio[0] }}</span>
    </h1>
    <div class="page-actions">
        <a href="{{ route('admin.emp-tasks.edit', $task) }}" class="btn btn-secondary">Bearbeiten</a>
        @if($task->status !== 'done')
        <form method="POST" action="{{ route('admin.emp-tasks.complete', $task) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-success">Als erledigt markieren</button>
        </form>
        @endif
        <a href="{{ route('admin.emp-tasks.index') }}" class="btn btn-secondary">← Zurück</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;align-items:start;">

    {{-- Main content --}}
    <div>
        {{-- Meta info --}}
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;font-size:.9rem;">
                    <div>
                        <div style="font-size:.75rem;color:var(--c-muted,#64748b);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Zugewiesen an</div>
                        <div>{{ $task->assignee?->full_name ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:var(--c-muted,#64748b);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Fälligkeit</div>
                        <div style="{{ $task->due_date && $task->due_date->lt(today()) && $task->status !== 'done' ? 'color:var(--c-danger,#dc2626);font-weight:600;' : '' }}">
                            {{ $task->due_date?->format('d.m.Y') ?? '—' }}
                        </div>
                    </div>
                    @if($task->parent)
                    <div>
                        <div style="font-size:.75rem;color:var(--c-muted,#64748b);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Übergeordnete Aufgabe</div>
                        <div><a href="{{ route('admin.emp-tasks.show', $task->parent) }}">{{ $task->parent->title }}</a></div>
                    </div>
                    @endif
                    @if($task->dependsOn)
                    <div>
                        <div style="font-size:.75rem;color:var(--c-muted,#64748b);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Abhängig von</div>
                        <div>
                            <a href="{{ route('admin.emp-tasks.show', $task->dependsOn) }}">{{ $task->dependsOn->title }}</a>
                            @if($task->dependsOn->status !== 'done')
                                <span class="badge badge-warning" style="font-size:.7rem;margin-left:.3rem;">noch offen</span>
                            @else
                                <span class="badge badge-success" style="font-size:.7rem;margin-left:.3rem;">erledigt</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Description --}}
        @if($task->description || $task->body)
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-header"><h2 style="margin:0;font-size:1rem;">Beschreibung</h2></div>
            <div class="card-body">
                @if($task->description)
                    <p style="color:var(--c-muted,#64748b);font-size:.9rem;">{{ $task->description }}</p>
                @endif
                @if($task->body)
                    <div style="white-space:pre-wrap;line-height:1.6;">{{ $task->body }}</div>
                @endif
            </div>
        </div>
        @endif

        {{-- Task images --}}
        @if($task->images && count($task->images) > 0)
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-header"><h2 style="margin:0;font-size:1rem;">Bilder</h2></div>
            <div class="card-body">
                <div style="display:flex;flex-wrap:wrap;gap:.75rem;">
                    @foreach($task->images as $img)
                        <a href="{{ asset('storage/' . $img) }}" target="_blank">
                            <img src="{{ asset('storage/' . $img) }}" style="width:120px;height:90px;object-fit:cover;border-radius:6px;border:1px solid var(--c-border,#e2e8f0);">
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Subtasks --}}
        @if($task->subtasks->count() > 0)
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 style="margin:0;font-size:1rem;">Unteraufgaben ({{ $task->subtasks->where('status','done')->count() }}/{{ $task->subtasks->count() }})</h2>
                <a href="{{ route('admin.emp-tasks.create') }}?parent_task_id={{ $task->id }}" class="btn btn-sm btn-secondary">+ Unteraufgabe</a>
            </div>
            <div class="card-body" style="padding:0;">
                <table class="table">
                    <tbody>
                        @foreach($task->subtasks as $sub)
                        <tr>
                            <td style="width:2rem;">
                                @if($sub->status === 'done') ✅ @else ⬜ @endif
                            </td>
                            <td><a href="{{ route('admin.emp-tasks.show', $sub) }}">{{ $sub->title }}</a></td>
                            <td style="color:var(--c-muted,#64748b);font-size:.85rem;">{{ $sub->assignee?->full_name ?? '—' }}</td>
                            <td style="font-size:.85rem;">{{ $sub->due_date?->format('d.m.Y') ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Comments / Updates --}}
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-header"><h2 style="margin:0;font-size:1rem;">Updates & Kommentare</h2></div>
            <div class="card-body">
                @forelse($task->comments->sortByDesc('created_at') as $comment)
                <div style="border-bottom:1px solid var(--c-border,#e2e8f0);padding-bottom:1rem;margin-bottom:1rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                        <div style="font-weight:600;font-size:.9rem;">
                            {{ $comment->author_name }}
                            @if($comment->is_liveblog)
                                <span class="badge badge-info" style="font-size:.7rem;margin-left:.4rem;">📢 Liveblog</span>
                            @endif
                        </div>
                        <div style="font-size:.8rem;color:var(--c-muted,#64748b);">{{ $comment->created_at->format('d.m.Y H:i') }}</div>
                    </div>
                    <div style="white-space:pre-wrap;line-height:1.5;">{{ $comment->body }}</div>
                    @if($comment->images && count($comment->images) > 0)
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.75rem;">
                            @foreach($comment->images as $img)
                                <a href="{{ asset('storage/' . $img) }}" target="_blank">
                                    <img src="{{ asset('storage/' . $img) }}" style="width:100px;height:75px;object-fit:cover;border-radius:4px;border:1px solid var(--c-border,#e2e8f0);">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
                @empty
                    <p style="color:var(--c-muted,#64748b);margin:0;">Noch keine Kommentare.</p>
                @endforelse
            </div>
        </div>

        {{-- Add Comment Form --}}
        <div class="card">
            <div class="card-header"><h2 style="margin:0;font-size:1rem;">Kommentar / Update hinzufügen</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.emp-tasks.comment', $task) }}" enctype="multipart/form-data">
                    @csrf
                    <div style="margin-bottom:1rem;">
                        <textarea name="body" class="form-control" rows="4" required placeholder="Update-Text…"></textarea>
                    </div>
                    <div style="margin-bottom:1rem;">
                        <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                        <div style="font-size:.75rem;color:var(--c-muted,#64748b);margin-top:.25rem;">Vorher/Nachher-Bilder anhängen (optional)</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                            <input type="checkbox" name="is_liveblog" value="1">
                            <span style="font-size:.9rem;">📢 Als Liveblog-Post für alle Mitarbeiter veröffentlichen</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">Kommentar senden</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div>
        <div class="card">
            <div class="card-header"><h2 style="margin:0;font-size:1rem;">Aktionen</h2></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:.5rem;">
                <a href="{{ route('admin.emp-tasks.edit', $task) }}" class="btn btn-secondary" style="width:100%;text-align:center;">Bearbeiten</a>
                @if($task->status !== 'done')
                <form method="POST" action="{{ route('admin.emp-tasks.complete', $task) }}">
                    @csrf
                    <button type="submit" class="btn btn-success" style="width:100%;">✓ Als erledigt markieren</button>
                </form>
                @endif
                <a href="{{ route('admin.emp-tasks.create') }}?parent_task_id={{ $task->id }}" class="btn btn-secondary" style="width:100%;text-align:center;">+ Unteraufgabe erstellen</a>
                <hr style="margin:.5rem 0;">
                <form method="POST" action="{{ route('admin.emp-tasks.destroy', $task) }}" onsubmit="return confirm('Aufgabe wirklich löschen?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger" style="width:100%;">Löschen</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
