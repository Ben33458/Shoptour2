@extends('employee.layout')

@section('title', 'Meine Aufgaben')

@push('head')
<style>
/* ── Modal ── */
.task-modal-overlay {
    display:none;position:fixed;inset:0;z-index:100;
    background:rgba(0,0,0,.55);align-items:center;justify-content:center;
}
.task-modal-overlay.open { display:flex; }
.task-modal {
    background:var(--c-surface);border:1px solid var(--c-border);border-radius:12px;
    box-shadow:0 8px 40px rgba(0,0,0,.25);width:min(520px,95vw);max-height:90vh;overflow-y:auto;
}
.task-modal-header {
    padding:16px 20px;border-bottom:1px solid var(--c-border);
    display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
}
.task-modal-title { font-size:15px;font-weight:600;color:var(--c-text);line-height:1.3; }
.task-modal-close {
    flex-shrink:0;background:none;border:none;cursor:pointer;
    color:var(--c-muted);font-size:20px;line-height:1;padding:0 2px;
}
.task-modal-body { padding:20px; }

/* ── History panel ── */
.history-panel { display:none; }
.history-panel.open { display:block; }
.history-entry {
    padding:10px 0;border-top:1px solid var(--c-border);font-size:13px;
}
.history-entry:first-child { border-top:none; }

/* ── Task rows ── */
.task-row-overdue  { background:color-mix(in srgb,var(--c-danger)  7%,var(--c-surface)); }
.task-row-today    { background:color-mix(in srgb,var(--c-warning)  7%,var(--c-surface)); }
.task-row-normal   { background:var(--c-surface); }
.task-row-done     { background:var(--c-surface);opacity:.55; }

/* ── Form inputs inherit theme ── */
textarea, input[type=text], input[type=file] {
    background:var(--c-bg);color:var(--c-text);border:1px solid var(--c-border);
    border-radius:6px;font-size:13px;font-family:inherit;
}
textarea:focus, input[type=text]:focus { outline:2px solid var(--c-primary);outline-offset:1px; }
</style>
@endpush

@section('content')

<div style="font-size:20px;font-weight:700;color:var(--c-text);margin-bottom:20px">
    Meine Aufgaben
    @if(!empty($zustaendigkeit))
    <span style="font-size:13px;font-weight:400;color:var(--c-muted);margin-left:8px">
        — {{ implode(', ', $zustaendigkeit) }}
    </span>
    @endif
</div>

@if(empty($zustaendigkeit))
<div style="background:color-mix(in srgb,var(--c-warning) 12%,var(--c-surface));border:1px solid var(--c-warning);border-radius:8px;padding:16px;color:var(--c-warning)">
    Für deinen Account ist noch keine Zuständigkeit eingetragen.
    Bitte einen Administrator, dir eine Zuständigkeit zuzuweisen.
</div>
@elseif($tasks->isEmpty())
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px;padding:32px;text-align:center;color:var(--c-muted)">
    Keine Aufgaben vorhanden.
</div>
@else

@php
    $overdueCount = $tasks->filter(fn($t) => $t->is_overdue && !($t->is_done_once ?? false))->count();
@endphp

@if($overdueCount > 0)
<div style="background:color-mix(in srgb,var(--c-danger) 12%,var(--c-surface));border:1px solid var(--c-danger);border-radius:8px;padding:12px 16px;margin-bottom:16px;color:var(--c-danger);font-size:13px;font-weight:500">
    ⚠️ {{ $overdueCount }} überfällige {{ $overdueCount === 1 ? 'Aufgabe' : 'Aufgaben' }}
</div>
@endif

<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;overflow:hidden;box-shadow:var(--shadow)">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead style="background:var(--c-bg);border-bottom:1px solid var(--c-border)">
            <tr>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted);width:32%">Aufgabe</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Fälligkeit</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Prio</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Kategorie</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Zuletzt erledigt</th>
                <th style="padding:10px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Wiederholung</th>
                <th style="padding:10px 16px"></th>
            </tr>
        </thead>
        <tbody>
        @foreach($tasks as $task)
        @php
            $rowClass = ($task->is_done_once ?? false)
            ? 'task-row-done'
            : ($task->is_overdue ? 'task-row-overdue' : ($task->is_due_today ? 'task-row-today' : 'task-row-normal'));
            $history  = $allCompletions->get($task->ninox_id, collect());
            $latest   = $task->latest_done;
        @endphp
        <tr class="{{ $rowClass }}" style="border-bottom:1px solid var(--c-border)">
            {{-- Name + Beschreibung --}}
            <td style="padding:10px 16px">
                <div style="font-weight:500;color:var(--c-text)">{{ $task->name }}</div>
                @if($task->beschreibung)
                <div style="font-size:12px;color:var(--c-muted);margin-top:3px;white-space:pre-line">{{ $task->beschreibung }}</div>
                @endif
            </td>

            {{-- Fälligkeit --}}
            <td style="padding:10px 16px">
                @if($task->is_done_once ?? false)
                    <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;
                                 background:color-mix(in srgb,var(--c-success) 15%,var(--c-surface));
                                 color:var(--c-success)">✓ Erledigt</span>
                @elseif($task->due_date)
                @php
                    $dueBg    = $task->is_overdue ? 'color-mix(in srgb,var(--c-danger) 20%,var(--c-surface))' : ($task->is_due_today ? 'color-mix(in srgb,var(--c-warning) 20%,var(--c-surface))' : 'color-mix(in srgb,var(--c-success) 15%,var(--c-surface))');
                    $dueColor = $task->is_overdue ? 'var(--c-danger)' : ($task->is_due_today ? 'var(--c-warning)' : 'var(--c-success)');
                @endphp
                <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:{{ $dueBg }};color:{{ $dueColor }}">
                    {{ $task->is_overdue ? '⚠️ ' : ($task->is_due_today ? '📅 ' : '') }}{{ $task->due_date->format('d.m.Y') }}
                </span>
                @else
                <span style="color:var(--c-muted)">—</span>
                @endif
            </td>

            {{-- Priorität --}}
            <td style="padding:10px 16px">
                @if($task->prioritaet)
                @php $p = (int)$task->prioritaet; @endphp
                <span style="font-weight:600;color:{{ $p >= 90 ? 'var(--c-danger)' : ($p >= 70 ? 'var(--c-warning)' : 'var(--c-muted)') }}">
                    {{ $p }}
                </span>
                @else <span style="color:var(--c-muted)">—</span>
                @endif
            </td>

            {{-- Kategorie --}}
            <td style="padding:10px 16px;color:var(--c-muted)">{{ $task->kategorie ?: '—' }}</td>

            {{-- Zuletzt erledigt --}}
            <td style="padding:10px 16px">
                @if($latest)
                <div style="font-size:12px;color:var(--c-muted)">
                    {{ $latest->completed_at->format('d.m.Y H:i') }}<br>
                    <span style="color:var(--c-text)">{{ $latest->user->name ?? '—' }}</span>
                </div>
                @if($history->count() > 1)
                <button onclick="toggleHistory('hist-{{ $task->ninox_id }}')"
                        style="font-size:11px;color:var(--c-primary);background:none;border:none;cursor:pointer;padding:2px 0;margin-top:2px">
                    {{ $history->count() }}× — Verlauf
                </button>
                @endif
                @else
                <span style="color:var(--c-muted);font-size:12px">—</span>
                @endif
            </td>

            {{-- Wiederholung --}}
            <td style="padding:10px 16px;color:var(--c-muted);font-size:12px">
                @php
                    $rep = [];
                    if ($task->alle_x_tage)     $rep[] = 'alle ' . $task->alle_x_tage . 'd';
                    if ($task->alle_x_wochen)   $rep[] = 'alle ' . $task->alle_x_wochen . 'w';
                    if ($task->alle_x_monate)   $rep[] = 'alle ' . $task->alle_x_monate . 'M';
                    if ($task->alle_x_quartale) $rep[] = 'alle ' . $task->alle_x_quartale . 'Q';
                    if ($task->alle_x_jahre)    $rep[] = 'alle ' . $task->alle_x_jahre . 'J';
                @endphp
                {{ $rep ? implode(', ', $rep) : ($task->ab_wann_wiederholen ?: '—') }}
            </td>

            {{-- Aktionen --}}
            <td style="padding:10px 16px;white-space:nowrap">
                <button onclick="openModal({{ $task->ninox_id }}, {{ json_encode($task->name) }})"
                        style="padding:5px 12px;background:var(--c-primary);color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer">
                    Erledigen
                </button>
            </td>
        </tr>

        {{-- History row (collapsed by default) --}}
        @if($history->count() > 0)
        <tr id="hist-{{ $task->ninox_id }}" style="display:none;background:var(--c-bg)">
            <td colspan="7" style="padding:0 16px 0 32px">
                <div class="history-panel open" style="padding:10px 0">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--c-muted);margin-bottom:6px">
                        Erledigungsverlauf
                    </div>
                    @foreach($history->take(10) as $entry)
                    <div class="history-entry">
                        <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
                            <span style="font-weight:500;color:var(--c-text)">{{ $entry->user->name ?? '—' }}</span>
                            <span style="color:var(--c-muted)">{{ $entry->completed_at->format('d.m.Y H:i') }}</span>
                            @if($entry->next_due_date)
                            <span style="color:var(--c-primary);font-size:12px">→ nächste Fälligkeit: {{ $entry->next_due_date->format('d.m.Y') }}</span>
                            @endif
                        </div>
                        @if($entry->note)
                        <div style="margin-top:4px;color:var(--c-text);white-space:pre-line">{{ $entry->note }}</div>
                        @endif
                        @if($entry->images && count($entry->images) > 0)
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
                            @foreach($entry->images as $img)
                            <a href="{{ Storage::url($img) }}" target="_blank">
                                <img src="{{ Storage::url($img) }}" alt="Bild"
                                     style="width:64px;height:64px;object-fit:cover;border-radius:4px;border:1px solid var(--c-border)">
                            </a>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </td>
        </tr>
        @endif

        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ── Erledigungs-Modal ── --}}
<div id="complete-modal" class="task-modal-overlay" onclick="if(event.target===this)closeModal()">
    <div class="task-modal">
        <div class="task-modal-header">
            <div>
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--c-muted);margin-bottom:4px">Aufgabe erledigen</div>
                <div id="modal-task-name" class="task-modal-title"></div>
            </div>
            <button class="task-modal-close" onclick="closeModal()" type="button">✕</button>
        </div>
        <div class="task-modal-body">
            <form method="POST" action="{{ route('employee.tasks.complete') }}"
                  enctype="multipart/form-data" id="complete-form">
                @csrf
                <input type="hidden" name="ninox_task_id" id="modal-task-id">

                <div style="margin-bottom:16px">
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--c-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em">
                        Notiz (optional)
                    </label>
                    <textarea name="note" rows="4"
                              style="width:100%;padding:8px 10px;box-sizing:border-box;resize:vertical;min-height:80px"
                              placeholder="Was wurde gemacht? Besonderheiten?"></textarea>
                </div>

                <div style="margin-bottom:20px">
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--c-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em">
                        Bilder (optional)
                    </label>
                    <input type="file" name="images[]" multiple accept="image/*"
                           style="width:100%;padding:6px;border-radius:6px;cursor:pointer">
                    <p style="font-size:11px;color:var(--c-muted);margin:4px 0 0">
                        Mehrere Bilder möglich (jpg, png, max. 8 MB je Bild)
                    </p>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end">
                    <button type="button" onclick="closeModal()"
                            style="padding:8px 16px;background:none;border:1px solid var(--c-border);border-radius:6px;font-size:13px;cursor:pointer;color:var(--c-muted)">
                        Abbrechen
                    </button>
                    <button type="submit"
                            style="padding:8px 20px;background:var(--c-primary);color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer">
                        Als erledigt markieren ✓
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openModal(taskId, taskName) {
    document.getElementById('modal-task-id').value   = taskId;
    document.getElementById('modal-task-name').textContent = taskName;
    document.getElementById('complete-form').querySelector('textarea').value = '';
    document.getElementById('complete-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('complete-modal').classList.remove('open');
    document.body.style.overflow = '';
}
function toggleHistory(id) {
    const el = document.getElementById(id);
    el.style.display = (el.style.display === '' || el.style.display === 'none') ? 'table-row' : 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// Initialise history rows: show only the currently active ones (all visible by default in table)
// They are always open; the "Verlauf" button just scrolls/reveals
</script>
@endpush
@endsection
