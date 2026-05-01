@extends('mein.layout')

@section('title', 'Dashboard')

@section('content')

{{-- Header --}}
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:8px;">
    <div>
        <h1 style="font-size:1.5rem; font-weight:700; color:var(--c-text);">
            Hallo, {{ $employee->first_name }}
        </h1>
        <div style="font-size:13px; color:var(--c-muted);">
            {{ now()->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
            @if($employee->role !== 'employee')
                &nbsp;·&nbsp; <span style="color:var(--c-primary); font-weight:600;">{{ ucfirst($employee->role) }}</span>
            @endif
        </div>
    </div>
</div>

{{-- Admin-Benachrichtigungen --}}
@if($notifications->count() > 0)
<div class="mein-card" style="border-left:3px solid var(--c-warning,#f59e0b);">
    <div class="mein-card-title" style="margin-bottom:10px;">Mitteilungen vom Admin</div>
    @foreach($notifications as $note)
    <div style="background:var(--c-bg);border:1px solid var(--c-border);border-radius:8px;padding:10px 14px;margin-bottom:8px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
            <div>
                <div style="font-weight:700;font-size:.9rem;color:var(--c-text);">{{ $note->title }}</div>
                @if($note->message)
                    <div style="font-size:.85rem;color:var(--c-muted);margin-top:4px;">{{ $note->message }}</div>
                @endif
                <div style="font-size:.75rem;color:var(--c-muted);margin-top:4px;">{{ $note->created_at->locale('de')->diffForHumans() }}</div>
            </div>
            <form method="POST" action="{{ route('mein.notification.read', $note) }}" style="flex-shrink:0;">
                @csrf
                <button type="submit" style="background:none;border:1px solid var(--c-border);border-radius:6px;padding:.2rem .6rem;font-size:.75rem;color:var(--c-muted);cursor:pointer;">
                    ✓ Gelesen
                </button>
            </form>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Stempeluhr-Status --}}
<div class="mein-card">
    <div class="mein-card-title">Stempeluhr</div>

    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
            <div style="font-size:2rem; font-weight:700; color:var(--c-primary);">
                @php
                    $h = intdiv((int)$netMinutesToday, 60);
                    $m = str_pad((int)$netMinutesToday % 60, 2, '0', STR_PAD_LEFT);
                @endphp
                {{ $h }}:{{ $m }}
            </div>
            <div style="font-size:12px; color:var(--c-muted);">Nettoarbeitszeit heute</div>
        </div>

        <div>
            @if($clockStatus === 'clocked_out')
                <span style="display:inline-block;padding:.3rem .9rem;border-radius:999px;background:#1e293b;color:#94a3b8;font-size:.8rem;font-weight:700;text-transform:uppercase;">
                    Ausgestempelt
                </span>
            @elseif($clockStatus === 'active')
                <span style="display:inline-block;padding:.3rem .9rem;border-radius:999px;background:#14532d;color:#4ade80;font-size:.8rem;font-weight:700;text-transform:uppercase;">
                    Eingestempelt seit {{ $activeEntry->clocked_in_at->format('H:i') }}
                </span>
            @elseif($clockStatus === 'on_break')
                <span style="display:inline-block;padding:.3rem .9rem;border-radius:999px;background:#78350f;color:#fbbf24;font-size:.8rem;font-weight:700;text-transform:uppercase;">
                    Pause
                </span>
            @endif
        </div>
    </div>

    <div style="display:flex; gap:.75rem; margin-top:1.25rem; flex-wrap:wrap;">
        @if($clockStatus === 'clocked_out')
            <form method="POST" action="{{ route('mein.timeclock.action') }}">
                @csrf
                <input type="hidden" name="action" value="clock_in">
                <button type="submit" style="background:#16a34a;color:#fff;border:none;padding:.6rem 1.2rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;">
                    Einstempeln
                </button>
            </form>
        @elseif($clockStatus === 'active')
            <form method="POST" action="{{ route('mein.timeclock.action') }}">
                @csrf
                <input type="hidden" name="action" value="clock_out">
                <button type="submit" style="background:#dc2626;color:#fff;border:none;padding:.6rem 1.2rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;">
                    Ausstempeln
                </button>
            </form>
            <form method="POST" action="{{ route('mein.timeclock.action') }}">
                @csrf
                <input type="hidden" name="action" value="break_start">
                <button type="submit" style="background:#d97706;color:#fff;border:none;padding:.6rem 1.2rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;">
                    Pause starten
                </button>
            </form>
        @elseif($clockStatus === 'on_break')
            <form method="POST" action="{{ route('mein.timeclock.action') }}">
                @csrf
                <input type="hidden" name="action" value="break_end">
                <button type="submit" style="background:#0284c7;color:#fff;border:none;padding:.6rem 1.2rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;">
                    Pause beenden
                </button>
            </form>
        @endif
    </div>
</div>

{{-- Aufgaben --}}
@if(!empty($employee->zustaendigkeit))
<div class="mein-card">
    <div class="mein-card-title">Aufgaben</div>
    @if($openTasks > 0)
        <div style="font-size:2rem; font-weight:700; color:var(--c-warning, #f59e0b);">{{ $openTasks }}</div>
        <div style="font-size:12px; color:var(--c-muted); margin-bottom:12px;">
            offene {{ $openTasks === 1 ? 'Aufgabe' : 'Aufgaben' }}
        </div>
    @else
        <div style="font-size:.9rem; color:var(--c-muted); margin-bottom:12px;">Keine offenen Aufgaben</div>
    @endif
    <a href="{{ route('mein.aufgaben') }}"
       style="font-size:.85rem; color:var(--c-primary); text-decoration:none; font-weight:600;">
        Alle Aufgaben →
    </a>
</div>
@endif

{{-- ── Schichtbericht ─────────────────────────────────────────────────── --}}
<div class="mein-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <div class="mein-card-title" style="margin:0;">Schichtbericht</div>
        @if($shiftReport?->is_submitted)
            <span style="padding:.2rem .7rem;border-radius:6px;background:#14532d;color:#4ade80;font-size:.8rem;font-weight:700;">
                Abgeschlossen ✓
            </span>
        @elseif($shiftReport)
            <span style="padding:.2rem .7rem;border-radius:6px;background:#1e2b40;color:#94a3b8;font-size:.8rem;font-weight:700;">
                Entwurf
            </span>
        @endif
    </div>

    @if($shiftReport?->is_submitted)
        {{-- Read-only view when submitted --}}
        <div style="font-size:.9rem; color:var(--c-muted); margin-bottom:10px;">
            Heute, {{ $shiftReport->submitted_at?->format('H:i') ?? '' }} Uhr abgeschlossen.
        </div>
        @if($shiftReport->summary)
            <div style="background:var(--c-bg);border:1px solid var(--c-border);border-radius:8px;padding:12px 14px;font-size:.9rem;color:var(--c-text);white-space:pre-wrap;margin-bottom:10px;">{{ $shiftReport->summary }}</div>
        @endif
        @if($shiftReport->customer_count !== null || $shiftReport->cash_difference !== null)
            <div style="display:flex;gap:20px;font-size:.85rem;color:var(--c-muted);">
                @if($shiftReport->customer_count !== null)
                    <span>Kunden: <strong style="color:var(--c-text);">{{ $shiftReport->customer_count }}</strong></span>
                @endif
                @if($shiftReport->cash_difference !== null)
                    <span>Kasse: <strong style="color:{{ $shiftReport->cash_difference >= 0 ? '#4ade80' : '#f87171' }};">{{ number_format($shiftReport->cash_difference, 2, ',', '.') }} €</strong></span>
                @endif
            </div>
        @endif
        @if($shiftReport->incident_level !== 'none')
            <div style="margin-top:10px;font-size:.85rem;color:#f87171;">
                Vorfall: {{ $shiftReport->incident_level === 'major' ? 'Schwerwiegend' : 'Gering' }}
                @if($shiftReport->incident_notes) — {{ $shiftReport->incident_notes }} @endif
            </div>
        @endif

    @else
        {{-- Editable form --}}
        <form method="POST" action="{{ route('mein.schicht.save') }}" id="report-form">
            @csrf

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:.78rem;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.3rem;">Wie lief die Schicht?</label>
                <textarea name="summary" rows="8"
                          style="width:100%;background:var(--c-bg);border:1px solid var(--c-border);border-radius:6px;color:var(--c-text);font-size:.92rem;padding:.6rem .85rem;outline:none;resize:vertical;"
                          placeholder="Zusammenfassung, Übergabe-Infos, Besonderheiten…">{{ old('summary', $shiftReport?->summary) }}</textarea>
            </div>

            <input type="hidden" name="customer_count" value="{{ old('customer_count', $shiftReport?->customer_count) }}">

            <div style="margin-bottom:14px;">
                <div class="form-group">
                    <label style="font-size:.78rem;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.3rem;">Kassendifferenz (€)</label>
                    <input type="number" name="cash_difference" step="0.01" placeholder="0.00"
                           value="{{ old('cash_difference', $shiftReport?->cash_difference) }}"
                           style="width:100%;background:var(--c-bg);border:1px solid var(--c-border);border-radius:6px;color:var(--c-text);font-size:.92rem;padding:.6rem .85rem;outline:none;">
                    <small style="color:var(--c-muted);font-size:.75rem;">+ Überschuss · − Fehlbetrag</small>
                </div>
            </div>

            @if($reportTemplates->count() > 0)
            <div style="margin-bottom:14px;">
                <div style="font-size:.78rem;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;">Checklisten</div>
                @foreach($reportTemplates as $template)
                    <div style="margin-bottom:10px;">
                        <div style="font-size:.85rem;font-weight:600;color:var(--c-text);margin-bottom:4px;">{{ $template->name }}</div>
                        @foreach($template->items as $item)
                            @php
                                $isChecked = $shiftReport?->checklistItems->contains('id', $item->id)
                                    ? (bool)($shiftReport->checklistItems->firstWhere('id', $item->id)?->pivot?->is_checked)
                                    : false;
                            @endphp
                            <label style="display:flex;align-items:center;gap:10px;padding:.4rem .5rem;border-radius:6px;cursor:pointer;">
                                <input type="checkbox" name="checklist[{{ $item->id }}]" value="1"
                                       style="width:15px;height:15px;accent-color:var(--c-primary);flex-shrink:0;"
                                       {{ $isChecked ? 'checked' : '' }}>
                                <span style="font-size:.88rem;color:var(--c-text);">{{ $item->label }}</span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
            @endif

            <details {{ old('incident_level', $shiftReport?->incident_level ?? 'none') !== 'none' ? 'open' : '' }}
                     style="margin-bottom:14px;">
                <summary style="cursor:pointer;font-size:.85rem;font-weight:600;color:var(--c-muted);padding:.4rem 0;user-select:none;list-style:none;display:flex;align-items:center;gap:.4rem;">
                    <span>▸</span> Vorfall melden
                </summary>
                <div style="margin-top:8px;padding:12px 14px;background:var(--c-bg);border-radius:8px;border:1px solid var(--c-border);">
                    <div style="margin-bottom:10px;">
                        <label style="font-size:.78rem;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.3rem;">Vorfall-Stufe</label>
                        <select name="incident_level"
                                style="width:100%;background:var(--c-bg);border:1px solid var(--c-border);border-radius:6px;color:var(--c-text);font-size:.9rem;padding:.5rem .75rem;outline:none;">
                            <option value="none"  {{ old('incident_level', $shiftReport?->incident_level ?? 'none') === 'none'  ? 'selected' : '' }}>Kein Vorfall</option>
                            <option value="minor" {{ old('incident_level', $shiftReport?->incident_level) === 'minor' ? 'selected' : '' }}>Gering</option>
                            <option value="major" {{ old('incident_level', $shiftReport?->incident_level) === 'major' ? 'selected' : '' }}>Schwerwiegend</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:.78rem;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.3rem;">Beschreibung</label>
                        <textarea name="incident_notes" rows="2"
                                  placeholder="Was ist passiert?"
                                  style="width:100%;background:var(--c-bg);border:1px solid var(--c-border);border-radius:6px;color:var(--c-text);font-size:.9rem;padding:.55rem .75rem;outline:none;resize:vertical;">{{ old('incident_notes', $shiftReport?->incident_notes) }}</textarea>
                    </div>
                </div>
            </details>

            <script>
            document.getElementById('report-form').addEventListener('submit', function() {
                var sel = this.querySelector('[name="incident_level"]');
                if (!sel || sel.disabled) {
                    var h = document.createElement('input');
                    h.type = 'hidden'; h.name = 'incident_level'; h.value = 'none';
                    this.appendChild(h);
                }
            });
            </script>

            <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:4px;align-items:center;">
                <button type="submit" name="submit" value="0"
                        style="background:var(--c-primary);color:#fff;border:none;padding:.6rem 1.2rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;">
                    Speichern
                </button>
                <button type="submit" name="submit" value="1"
                        style="background:#16a34a;color:#fff;border:none;padding:.6rem 1.2rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;"
                        onclick="return confirm('Schichtbericht abschließen? Danach keine Änderungen mehr möglich.')">
                    Abschließen ✓
                </button>
                <span id="report-autosave-indicator" style="font-size:12px;color:var(--c-muted);display:none;"></span>
            </div>
        </form>
        <script>
        (function() {
            var form = document.getElementById('report-form');
            if (!form) return;
            var timer = null;
            var ind = document.getElementById('report-autosave-indicator');
            function show(msg) { ind.textContent = msg; ind.style.display = 'inline'; }
            function save() {
                var params = new URLSearchParams();
                params.set('_token', form.querySelector('[name=_token]').value);
                ['summary','customer_count','cash_difference','incident_notes'].forEach(function(n) {
                    var el = form.querySelector('[name="' + n + '"]');
                    if (el && !el.disabled) params.set(n, el.value || '');
                });
                var sel = form.querySelector('[name="incident_level"]');
                params.set('incident_level', (sel && !sel.disabled) ? sel.value : 'none');
                form.querySelectorAll('[name^="checklist["]').forEach(function(cb) {
                    if (cb.checked) params.append(cb.name, '1');
                });
                params.set('submit', '0');
                show('Speichert…');
                fetch(form.action, {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': form.querySelector('[name=_token]').value, 'Content-Type': 'application/x-www-form-urlencoded'},
                    body: params.toString()
                }).then(function(r) {
                    show(r.ok || r.redirected ? '✓ Automatisch gespeichert' : 'Fehler');
                    setTimeout(function() { ind.style.display = 'none'; }, 2500);
                }).catch(function() { ind.style.display = 'none'; });
            }
            function reschedule() { clearTimeout(timer); show('Änderungen erkannt…'); timer = setTimeout(save, 10000); }
            form.addEventListener('input', reschedule);
            form.addEventListener('change', reschedule);
        })();
        </script>
    @endif
</div>

{{-- Feedback / Fehler melden --}}
<div class="mein-card">
    <div class="mein-card-title">Feedback / Fehler melden</div>

    <div id="feedback-success" style="display:none;background:#14532d;color:#4ade80;border-radius:8px;padding:12px 16px;font-size:.9rem;font-weight:600;margin-bottom:12px;">
        ✓ Danke! Dein Feedback wurde übermittelt.
    </div>

    <form id="feedback-form" method="POST" action="{{ route('mein.feedback.store') }}">
        @csrf
        <div style="margin-bottom:12px;">
            <label style="font-size:.78rem;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.3rem;">Kategorie</label>
            <select name="category"
                    style="width:100%;background:var(--c-bg);border:1px solid var(--c-border);border-radius:6px;color:var(--c-text);font-size:.9rem;padding:.5rem .75rem;outline:none;">
                <option value="bug">Fehler</option>
                <option value="improvement">Verbesserungsvorschlag</option>
                <option value="other">Sonstiges</option>
            </select>
        </div>
        <div style="margin-bottom:12px;">
            <label style="font-size:.78rem;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.3rem;">Betreff</label>
            <input type="text" name="subject" maxlength="200" required
                   placeholder="Kurze Zusammenfassung…"
                   style="width:100%;background:var(--c-bg);border:1px solid var(--c-border);border-radius:6px;color:var(--c-text);font-size:.9rem;padding:.55rem .75rem;outline:none;">
        </div>
        <div style="margin-bottom:14px;">
            <label style="font-size:.78rem;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.3rem;">Beschreibung</label>
            <textarea name="body" rows="3" maxlength="2000" required
                      placeholder="Was ist passiert? Was soll besser werden?"
                      style="width:100%;background:var(--c-bg);border:1px solid var(--c-border);border-radius:6px;color:var(--c-text);font-size:.9rem;padding:.55rem .75rem;outline:none;resize:vertical;"></textarea>
        </div>
        <button type="submit" id="feedback-submit-btn"
                style="background:var(--c-primary);color:#fff;border:none;padding:.6rem 1.2rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;">
            Absenden
        </button>
    </form>

    <script>
    (function() {
        var form = document.getElementById('feedback-form');
        var success = document.getElementById('feedback-success');
        var btn = document.getElementById('feedback-submit-btn');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            btn.disabled = true;
            btn.textContent = 'Sendet…';
            var params = new URLSearchParams(new FormData(form));
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': form.querySelector('[name=_token]').value,
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: params.toString()
            }).then(function(r) {
                if (r.ok) {
                    success.style.display = 'block';
                    form.reset();
                    setTimeout(function() { success.style.display = 'none'; }, 4000);
                } else {
                    alert('Fehler beim Senden. Bitte erneut versuchen.');
                }
                btn.disabled = false;
                btn.textContent = 'Absenden';
            }).catch(function() {
                alert('Netzwerkfehler. Bitte erneut versuchen.');
                btn.disabled = false;
                btn.textContent = 'Absenden';
            });
        });
    })();
    </script>
</div>

{{-- Team-Übersicht (nur Schichtleiter/Manager) --}}
@if($teamEntries && $teamEntries->count() > 0)
<div class="mein-card">
    <div class="mein-card-title">Team — Aktuell eingestempelt</div>
    <div style="display:flex; flex-wrap:wrap; gap:8px;">
        @foreach($teamEntries as $te)
            <span style="background:var(--c-surface-2, #1e293b);border:1px solid var(--c-border);padding:.3rem .8rem;border-radius:999px;font-size:.85rem;color:var(--c-text);">
                {{ $te->employee->full_name }}
                <span style="color:var(--c-muted);font-size:.75rem;"> seit {{ $te->clocked_in_at->format('H:i') }}</span>
            </span>
        @endforeach
    </div>
</div>
@endif

@endsection
