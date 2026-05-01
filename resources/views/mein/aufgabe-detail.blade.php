@extends('mein.layout')

@section('title', $task->title)

@section('content')

<div style="margin-bottom:16px;">
    <a href="{{ route('mein.aufgaben') }}" style="color:var(--c-muted);text-decoration:none;font-size:13px;">← Zurück zu Aufgaben</a>
</div>

{{-- Task header --}}
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:20px;margin-bottom:16px;box-shadow:var(--shadow)">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;color:var(--c-text);margin:0 0 8px;">{{ $task->title }}</h1>
            @php
                $statusLabels = ['open'=>'Offen','in_progress'=>'In Arbeit','done'=>'Erledigt'];
                $statusColors = ['open'=>'#6b7280','in_progress'=>'#d97706','done'=>'#16a34a'];
                $prioLabels = ['urgent'=>'Dringend','high'=>'Hoch','medium'=>'Mittel','low'=>'Niedrig'];
                $prioColors = ['urgent'=>'#dc2626','high'=>'#d97706','medium'=>'#2563eb','low'=>'#6b7280'];
            @endphp
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
                <span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:{{ ($statusColors[$task->status] ?? '#6b7280') }}20;color:{{ $statusColors[$task->status] ?? '#6b7280' }};">
                    {{ $statusLabels[$task->status] ?? $task->status }}
                </span>
                <span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:{{ ($prioColors[$task->priority] ?? '#6b7280') }}20;color:{{ $prioColors[$task->priority] ?? '#6b7280' }};">
                    {{ $prioLabels[$task->priority] ?? $task->priority }}
                </span>
                @if($task->due_date)
                @php $isOverdue = $task->due_date->lt(today()) && $task->status !== 'done'; @endphp
                <span style="font-size:12px;color:{{ $isOverdue ? 'var(--c-danger)' : 'var(--c-muted)' }};font-weight:{{ $isOverdue ? '600' : '400' }};">
                    Fällig: {{ $task->due_date->format('d.m.Y') }}{{ $isOverdue ? ' (überfällig)' : '' }}
                </span>
                @endif
            </div>

            @if($task->parent)
            <div style="font-size:12px;color:var(--c-muted);">
                Unteraufgabe von: <a href="{{ route('mein.aufgabe.show', $task->parent) }}" style="color:var(--c-primary);">{{ $task->parent->title }}</a>
            </div>
            @endif
            @if($task->dependsOn)
            <div style="font-size:12px;color:{{ $task->dependsOn->status !== 'done' ? 'var(--c-warning)' : 'var(--c-muted)' }};margin-top:4px;">
                Abhängig von: {{ $task->dependsOn->title }}
                @if($task->dependsOn->status !== 'done') (noch offen) @else (erledigt) @endif
            </div>
            @endif
        </div>

        @if($task->status === 'open')
        {{-- Start button --}}
        <form method="POST" action="{{ route('mein.aufgabe.start', $task) }}">
            @csrf
            <button type="submit" style="padding:8px 16px;background:var(--c-primary);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                ▶ Aufgabe starten
            </button>
        </form>
        @elseif($task->status === 'in_progress')
        {{-- Running timer + complete button --}}
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="background:color-mix(in srgb,var(--c-warning) 15%,var(--c-surface));border:1px solid var(--c-warning);border-radius:8px;padding:6px 14px;font-size:14px;font-weight:700;color:var(--c-warning);font-variant-numeric:tabular-nums;">
                ⏱ <span id="timer-display">0m 0s</span>
            </div>
            <button type="button" onclick="openCompleteModal()"
                    style="padding:8px 16px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                ■ Aufgabe abschließen
            </button>
        </div>
        @elseif($task->status === 'done')
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            @if($task->time_spent_seconds)
            @php $tsMin = intdiv($task->time_spent_seconds, 60); $tsSec = $task->time_spent_seconds % 60; @endphp
            <span style="font-size:12px;color:var(--c-muted);">
                Bearbeitungszeit: <strong>{{ $tsMin }} Min{{ $tsSec > 0 ? " {$tsSec} Sek" : '' }}</strong>
            </span>
            @endif
            <form method="POST" action="{{ route('mein.aufgabe.reopen', $task) }}" style="margin:0;">
                @csrf
                <button type="submit"
                        style="padding:6px 14px;background:none;border:1px solid var(--c-border);border-radius:6px;font-size:12px;color:var(--c-muted);cursor:pointer;">
                    ↩ Wieder öffnen
                </button>
            </form>
        </div>
        @endif
    </div>

    @if($task->description)
    <p style="color:var(--c-muted);font-size:13px;margin:12px 0 0;">{{ $task->description }}</p>
    @endif
</div>

@if(session('success'))
    <div style="background:color-mix(in srgb,var(--c-success,#16a34a) 12%,var(--c-surface));border:1px solid var(--c-success,#16a34a);border-radius:8px;padding:12px 16px;margin-bottom:16px;color:var(--c-success,#16a34a);font-size:13px;">
        {{ session('success') }}
    </div>
@endif

{{-- Detailed body --}}
@if($task->body)
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:20px;margin-bottom:16px;box-shadow:var(--shadow)">
    <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--c-muted);margin-bottom:12px;">Beschreibung</div>
    <div style="white-space:pre-wrap;line-height:1.6;font-size:14px;color:var(--c-text);">{{ $task->body }}</div>
</div>
@endif

{{-- Task images --}}
@if($task->images && count($task->images) > 0)
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:20px;margin-bottom:16px;box-shadow:var(--shadow)">
    <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--c-muted);margin-bottom:12px;">Bilder</div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;">
        @foreach($task->images as $img)
            <a href="{{ asset('storage/' . $img) }}" target="_blank">
                <img src="{{ asset('storage/' . $img) }}" style="width:100px;height:75px;object-fit:cover;border-radius:6px;border:1px solid var(--c-border);">
            </a>
        @endforeach
    </div>
</div>
@endif

{{-- Subtasks --}}
@if($task->subtasks->count() > 0)
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:20px;margin-bottom:16px;box-shadow:var(--shadow)">
    <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--c-muted);margin-bottom:12px;">
        Unteraufgaben ({{ $task->subtasks->where('status','done')->count() }}/{{ $task->subtasks->count() }} erledigt)
    </div>
    @foreach($task->subtasks as $sub)
    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--c-border);font-size:13px;">
        <span style="font-size:16px;">{{ $sub->status === 'done' ? '✅' : '⬜' }}</span>
        <div style="flex:1;">
            <a href="{{ route('mein.aufgabe.show', $sub) }}" style="color:var(--c-text);text-decoration:none;font-weight:{{ $sub->status === 'done' ? '400' : '500' }};{{ $sub->status === 'done' ? 'text-decoration:line-through;color:var(--c-muted);' : '' }}">
                {{ $sub->title }}
            </a>
            @if($sub->due_date)
                <span style="font-size:11px;color:var(--c-muted);margin-left:8px;">{{ $sub->due_date->format('d.m.') }}</span>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Comments --}}
@if($task->comments->count() > 0)
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:20px;margin-bottom:16px;box-shadow:var(--shadow)">
    <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--c-muted);margin-bottom:16px;">Updates & Kommentare</div>
    @foreach($task->comments->sortByDesc('created_at') as $comment)
    <div style="border-bottom:1px solid var(--c-border);padding-bottom:14px;margin-bottom:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <div style="font-size:13px;font-weight:600;color:var(--c-text);">
                {{ $comment->author_name }}
                @if($comment->is_liveblog)
                    <span style="font-size:10px;padding:2px 6px;border-radius:10px;background:#0ea5e920;color:#0ea5e9;font-weight:600;margin-left:4px;">📢 Liveblog</span>
                @endif
            </div>
            <div style="font-size:11px;color:var(--c-muted);">{{ $comment->created_at->format('d.m.Y H:i') }}</div>
        </div>
        <div style="white-space:pre-wrap;line-height:1.5;font-size:13px;color:var(--c-text);">{{ $comment->body }}</div>
        @if($comment->images && count($comment->images) > 0)
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
                @foreach($comment->images as $img)
                    <a href="{{ asset('storage/' . $img) }}" target="_blank">
                        <img src="{{ asset('storage/' . $img) }}" style="width:80px;height:60px;object-fit:cover;border-radius:4px;border:1px solid var(--c-border);">
                    </a>
                @endforeach
            </div>
        @endif
    </div>
    @endforeach
</div>
@endif

{{-- Add comment (immer sichtbar) --}}
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:20px;margin-bottom:16px;box-shadow:var(--shadow)">
    <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--c-muted);margin-bottom:16px;">Update / Kommentar hinzufügen</div>
    <form method="POST" action="{{ route('mein.aufgabe.comment', $task) }}" enctype="multipart/form-data">
        @csrf
        <div style="margin-bottom:12px;">
            <textarea name="body" rows="4" required placeholder="Was hast du gemacht? Probleme? Fortschritt?"
                style="width:100%;background:var(--c-bg);color:var(--c-text);border:1px solid var(--c-border);border-radius:8px;padding:10px 12px;font-size:13px;font-family:inherit;resize:vertical;box-sizing:border-box;"></textarea>
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:600;color:var(--c-muted);margin-bottom:6px;">Bilder anhängen (optional)</label>
            <input type="file" name="images[]" multiple accept="image/*"
                style="display:block;width:100%;font-size:12px;color:var(--c-muted);">
        </div>
        <button type="submit" style="padding:10px 20px;background:var(--c-primary);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
            Update senden
        </button>
    </form>
</div>

@if($task->status === 'in_progress')
{{-- Abschluss-Modal --}}
<div id="complete-modal" style="display:none;position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
    <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.25);width:min(460px,95vw);max-height:90vh;overflow-y:auto;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--c-border);display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:15px;font-weight:600;color:var(--c-text);">Aufgabe abschließen</div>
            <button type="button" onclick="closeCompleteModal()" style="background:none;border:none;cursor:pointer;color:var(--c-muted);font-size:20px;line-height:1;padding:0 2px;">✕</button>
        </div>
        <div style="padding:20px;">
            <form method="POST" action="{{ route('mein.aufgabe.complete', $task) }}" enctype="multipart/form-data">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--c-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.04em;">
                        Bearbeitungszeit
                    </label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="number" id="time-minutes" name="time_minutes" min="0" max="9999" value="0"
                               style="width:80px;background:var(--c-bg);color:var(--c-text);border:1px solid var(--c-border);border-radius:6px;padding:7px 10px;font-size:14px;font-weight:600;text-align:center;">
                        <span style="font-size:13px;color:var(--c-muted);">Min</span>
                        <input type="number" id="time-seconds" name="time_seconds" min="0" max="59" value="0"
                               style="width:70px;background:var(--c-bg);color:var(--c-text);border:1px solid var(--c-border);border-radius:6px;padding:7px 10px;font-size:14px;font-weight:600;text-align:center;">
                        <span style="font-size:13px;color:var(--c-muted);">Sek</span>
                    </div>
                    <div style="font-size:11px;color:var(--c-muted);margin-top:6px;">Automatisch vom Timer befüllt — kann manuell angepasst werden.</div>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--c-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em;">
                        Kommentar (optional)
                    </label>
                    <textarea name="comment" rows="3"
                              style="width:100%;background:var(--c-bg);color:var(--c-text);border:1px solid var(--c-border);border-radius:6px;padding:8px 10px;font-size:13px;font-family:inherit;resize:vertical;box-sizing:border-box;"
                              placeholder="Was wurde gemacht? Besonderheiten?"></textarea>
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--c-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em;">
                        Bilder anhängen (optional)
                    </label>
                    <input type="file" name="images[]" multiple accept="image/*"
                           style="display:block;width:100%;font-size:12px;color:var(--c-muted);">
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" onclick="closeCompleteModal()"
                            style="padding:8px 16px;background:none;border:1px solid var(--c-border);border-radius:6px;font-size:13px;cursor:pointer;color:var(--c-muted);">
                        Abbrechen
                    </button>
                    <button type="submit"
                            style="padding:8px 20px;background:#16a34a;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
                        ✓ Erledigt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const startedAt = {{ $task->timer_started_at?->timestamp ?? 'null' }};
    const display   = document.getElementById('timer-display');
    const mInput    = document.getElementById('time-minutes');
    const sInput    = document.getElementById('time-seconds');

    if (startedAt && display) {
        function tick() {
            const elapsed = Math.max(0, Math.floor(Date.now() / 1000) - startedAt);
            const m = Math.floor(elapsed / 60);
            const s = elapsed % 60;
            display.textContent = m + 'm ' + s + 's';
            if (mInput) mInput.value = m;
            if (sInput) sInput.value = s;
        }
        tick();
        setInterval(tick, 1000);
    }

    const modal = document.getElementById('complete-modal');

    window.openCompleteModal = function () {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeCompleteModal = function () {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeCompleteModal();
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeCompleteModal();
    });
})();
</script>
@endif

@endsection
