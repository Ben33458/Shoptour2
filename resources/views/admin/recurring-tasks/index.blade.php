@extends('admin.layout')

@section('title', 'Wiederkehrende Aufgaben')

@section('content')

{{-- Mitarbeiter-Zuständigkeiten --}}
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;margin-bottom:24px;overflow:hidden">
    <div style="padding:14px 20px;border-bottom:1px solid var(--c-border)">
        <h2 style="font-size:14px;font-weight:600;color:var(--c-text);margin:0">Mitarbeiter-Zuständigkeiten</h2>
    </div>
    <div style="padding:16px 20px">
        <p style="font-size:13px;color:var(--c-muted);margin:0 0 12px">
            Legt fest, welche Aufgaben ein Mitarbeiter in seiner Mitarbeiteransicht sieht.
            Ein Mitarbeiter kann mehrere Zuständigkeitsbereiche haben.
        </p>
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr style="background:var(--c-bg);border-bottom:1px solid var(--c-border)">
                    <th style="padding:8px 12px;text-align:left;font-weight:500;color:var(--c-muted)">Mitarbeiter</th>
                    <th style="padding:8px 12px;text-align:left;font-weight:500;color:var(--c-muted)">Rolle</th>
                    <th style="padding:8px 12px;text-align:left;font-weight:500;color:var(--c-muted)">Zuständigkeiten</th>
                </tr>
            </thead>
            <tbody>
                @foreach($employees as $emp)
                <tr style="border-bottom:1px solid var(--c-border)">
                    <td style="padding:10px 12px;font-weight:500;color:var(--c-text)">{{ $emp->name }}</td>
                    <td style="padding:10px 12px;color:var(--c-muted)">{{ ucfirst($emp->role) }}</td>
                    <td style="padding:10px 12px">
                        <form method="POST" action="{{ route('admin.recurring-tasks.update-user', $emp) }}">
                            @csrf
                            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                                @foreach($zustaendigkeitValues as $z)
                                <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer;color:var(--c-text)">
                                    <input type="checkbox" name="zustaendigkeit[]" value="{{ $z }}"
                                           @checked(is_array($emp->zustaendigkeit) && in_array($z, $emp->zustaendigkeit))
                                           style="accent-color:var(--c-primary)">
                                    {{ $z }}
                                </label>
                                @endforeach
                                <button type="submit"
                                        style="padding:4px 10px;background:var(--c-primary);color:#fff;border:none;border-radius:6px;font-size:12px;cursor:pointer;margin-left:4px">
                                    Speichern
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Wiederholungseinstellungen --}}
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;margin-bottom:24px;overflow:hidden">
    <div style="padding:14px 20px;border-bottom:1px solid var(--c-border);display:flex;align-items:center;justify-content:space-between">
        <h2 style="font-size:14px;font-weight:600;color:var(--c-text);margin:0">Wiederholungseinstellungen</h2>
        <button onclick="document.getElementById('task-settings-body').style.display = document.getElementById('task-settings-body').style.display==='none'?'block':'none'"
                style="font-size:12px;color:var(--c-primary);background:none;border:none;cursor:pointer">
            Ein-/Ausblenden
        </button>
    </div>
    <div id="task-settings-body" style="display:none">
        <div style="padding:10px 20px 4px;font-size:12px;color:var(--c-muted)">
            <strong>auto</strong> = Ninox-Einstellung verwenden
            (<em>ab Erledigung</em> oder <em>Fester Tag</em>) ·
            <strong>Ab Erledigung</strong> = Fälligkeit immer vom Erledigungsdatum aus ·
            <strong>Fester Termin</strong> = Fälligkeit vom letzten Fälligkeitsdatum aus (Termin bleibt fix)
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead style="background:var(--c-bg);border-bottom:1px solid var(--c-border)">
                <tr>
                    <th style="padding:8px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Aufgabe</th>
                    <th style="padding:8px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Bereich</th>
                    <th style="padding:8px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Ninox-Basis</th>
                    <th style="padding:8px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Intervall</th>
                    <th style="padding:8px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Override</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allTasks as $t)
                @php
                    $rep = array_filter([
                        !empty($t->alle_x_tage)     ? $t->alle_x_tage     . ' Tage'     : null,
                        !empty($t->alle_x_wochen)   ? $t->alle_x_wochen   . ' Wo.'      : null,
                        !empty($t->alle_x_monate)   ? $t->alle_x_monate   . ' Mon.'     : null,
                        !empty($t->alle_x_quartale) ? $t->alle_x_quartale . ' Qu.'      : null,
                        !empty($t->alle_x_jahre)    ? $t->alle_x_jahre    . ' Jahr(e)'  : null,
                    ]);
                    $currentBasis = $taskSettings->get($t->ninox_id, 'auto');
                @endphp
                <tr style="border-bottom:1px solid var(--c-border)">
                    <td style="padding:7px 16px;color:var(--c-text)">{{ $t->name }}</td>
                    <td style="padding:7px 16px;color:var(--c-muted);font-size:12px">{{ $t->zustaendigkeit }}</td>
                    <td style="padding:7px 16px;color:var(--c-muted);font-size:12px">{{ $t->ab_wann_wiederholen ?? '—' }}</td>
                    <td style="padding:7px 16px;color:var(--c-muted);font-size:12px">{{ implode(', ', $rep) ?: '—' }}</td>
                    <td style="padding:7px 16px">
                        <form method="POST" action="{{ route('admin.recurring-tasks.update-setting') }}">
                            @csrf
                            <input type="hidden" name="ninox_task_id" value="{{ $t->ninox_id }}">
                            <select name="recurrence_basis" onchange="this.form.submit()"
                                    style="font-size:12px;padding:3px 6px;border:1px solid var(--c-border);border-radius:5px;background:var(--c-surface);color:var(--c-text)">
                                <option value="auto"             @selected($currentBasis==='auto')>auto (Ninox)</option>
                                <option value="from_completion"  @selected($currentBasis==='from_completion')>Ab Erledigung</option>
                                <option value="fixed_schedule"   @selected($currentBasis==='fixed_schedule')>Fester Termin</option>
                            </select>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Überfällige Aufgaben --}}
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;overflow:hidden">
    <div style="padding:14px 20px;border-bottom:1px solid var(--c-border);display:flex;align-items:center;justify-content:space-between">
        <h2 style="font-size:14px;font-weight:600;color:var(--c-text);margin:0">
            Überfällige Aufgaben
            @if($overdue->isNotEmpty())
            <span style="background:#fee2e2;color:#991b1b;border-radius:10px;padding:2px 8px;font-size:11px;margin-left:6px">
                {{ $overdue->count() }}
            </span>
            @endif
        </h2>
        <a href="{{ route('employee.tasks.index') }}"
           style="font-size:12px;color:var(--c-primary)">Mitarbeiteransicht →</a>
    </div>

    @if($overdue->isEmpty())
    <div style="padding:32px;text-align:center;color:var(--c-muted);font-size:13px">
        Keine überfälligen Aufgaben.
    </div>
    @else
    @foreach($byZustaendigkeit as $z => $group)
    <div style="padding:8px 20px 0;background:var(--c-bg);border-top:1px solid var(--c-border)">
        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--c-muted)">
            {{ $z ?: 'Ohne Zuständigkeit' }}
        </span>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <tbody>
            @foreach($group as $task)
            <tr style="border-bottom:1px solid var(--c-border)">
                <td style="padding:8px 16px;font-weight:500;color:var(--c-text);width:40%">{{ $task->name }}</td>
                <td style="padding:8px 16px">
                    <span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600">
                        ⚠️ {{ \Carbon\Carbon::parse($task->naechste_faelligkeit)->format('d.m.Y') }}
                    </span>
                </td>
                <td style="padding:8px 16px;color:var(--c-muted)">
                    @if($task->prioritaet)
                    <span style="font-weight:600;color:{{ (int)$task->prioritaet >= 90 ? '#dc2626' : ((int)$task->prioritaet >= 70 ? '#ea580c' : 'var(--c-muted)') }}">
                        P{{ $task->prioritaet }}
                    </span>
                    @else —
                    @endif
                </td>
                <td style="padding:8px 16px;color:var(--c-muted)">{{ $task->kategorie ?: '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endforeach
    @endif
</div>

{{-- Erledigungs-Log --}}
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;overflow:hidden;margin-top:24px">
    <div style="padding:14px 20px;border-bottom:1px solid var(--c-border)">
        <h2 style="font-size:14px;font-weight:600;color:var(--c-text);margin:0">Erledigungsprotokoll (letzte 50)</h2>
    </div>
    @if($recentCompletions->isEmpty())
    <div style="padding:24px;text-align:center;color:var(--c-muted);font-size:13px">Noch keine Erledigungen.</div>
    @else
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead style="background:var(--c-bg);border-bottom:1px solid var(--c-border)">
            <tr>
                <th style="padding:8px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Datum</th>
                <th style="padding:8px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Mitarbeiter</th>
                <th style="padding:8px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Aufgabe (ID)</th>
                <th style="padding:8px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Nächste Fälligkeit</th>
                <th style="padding:8px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Notiz</th>
                <th style="padding:8px 16px;text-align:left;font-weight:500;color:var(--c-muted)">Bilder</th>
            </tr>
        </thead>
        <tbody>
            @foreach($recentCompletions as $c)
            <tr style="border-bottom:1px solid var(--c-border)">
                <td style="padding:8px 16px;color:var(--c-muted);white-space:nowrap">{{ $c->completed_at->format('d.m.Y H:i') }}</td>
                <td style="padding:8px 16px;font-weight:500;color:var(--c-text)">{{ $c->user->name ?? '—' }}</td>
                <td style="padding:8px 16px;color:var(--c-muted);font-family:monospace;font-size:12px">#{{ $c->ninox_task_id }}</td>
                <td style="padding:8px 16px">
                    @if($c->next_due_date)
                    <span style="color:var(--c-primary);font-size:12px">{{ $c->next_due_date->format('d.m.Y') }}</span>
                    @else <span style="color:var(--c-muted)">—</span>
                    @endif
                </td>
                <td style="padding:8px 16px;color:var(--c-text);max-width:300px">
                    @if($c->note)
                    <div style="white-space:pre-line;font-size:12px">{{ Str::limit($c->note, 120) }}</div>
                    @else <span style="color:var(--c-muted)">—</span>
                    @endif
                </td>
                <td style="padding:8px 16px">
                    @if($c->images && count($c->images) > 0)
                    <div style="display:flex;gap:4px;flex-wrap:wrap">
                        @foreach($c->images as $img)
                        <a href="{{ Storage::url($img) }}" target="_blank">
                            <img src="{{ Storage::url($img) }}" alt="Bild"
                                 style="width:40px;height:40px;object-fit:cover;border-radius:4px;border:1px solid var(--c-border)">
                        </a>
                        @endforeach
                    </div>
                    @else <span style="color:var(--c-muted)">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

@endsection
