@extends('admin.layout')

@section('title', 'Urlaubsverwaltung')

@section('content')
<div class="page-header">
    <h1>Urlaubsverwaltung</h1>
    <div class="page-actions">
        <a href="{{ route('admin.employees.dashboard') }}" class="btn btn-secondary">Dashboard</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $e) {{ $e }}<br> @endforeach
    </div>
@endif

{{-- Jahreswechsel-Hinweis --}}
@if(now()->month === 1)
<div style="background:rgba(59,130,246,.1);border:1px solid #3b82f6;border-radius:8px;padding:12px 16px;margin-bottom:1.5rem;font-size:.9rem;">
    ℹ <strong>Jahreswechsel:</strong> Bitte Urlaubskonten für {{ now()->year }} anlegen und Überträge aus {{ now()->year - 1 }} eintragen (Abschnitt „Urlaubskonto pflegen" unten).
</div>
@endif

{{-- Ausstehende Anträge --}}
<div class="card" style="margin-bottom:2rem;">
    <div class="card-header">
        <h2 style="margin:0;font-size:1.1rem;">
            Ausstehende Anträge
            @if($pending->count() > 0)
                <span class="badge badge-warning" style="margin-left:.5rem;">{{ $pending->count() }}</span>
            @endif
        </h2>
    </div>
    <div class="card-body" style="padding:0;">
        @if($pending->isEmpty())
            <p style="padding:1.5rem;color:var(--c-muted,#64748b);">Keine ausstehenden Urlaubsanträge.</p>
        @else
        <table class="table">
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Von</th>
                    <th>Bis</th>
                    <th>Arbeitstage</th>
                    <th>Notiz</th>
                    <th>Eingereicht</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pending as $req)
                <tr>
                    <td>{{ $req->employee->full_name }}</td>
                    <td>{{ $req->start_date->format('d.m.Y') }}</td>
                    <td>{{ $req->end_date->format('d.m.Y') }}</td>
                    <td>{{ $req->days_requested }}</td>
                    <td>{{ $req->notes ?? '—' }}</td>
                    <td>{{ $req->created_at->format('d.m.Y H:i') }}</td>
                    <td style="white-space:nowrap;">
                        {{-- Genehmigen --}}
                        <details style="display:inline-block;margin-right:.3rem;">
                            <summary class="btn btn-sm btn-success" style="cursor:pointer;list-style:none;">Genehmigen</summary>
                            <div style="position:absolute;z-index:10;background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.75rem;margin-top:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);min-width:220px;">
                                <div style="font-size:.85rem;margin-bottom:.5rem;">
                                    <strong>{{ $req->employee->full_name }}</strong><br>
                                    {{ $req->start_date->format('d.m.Y') }} – {{ $req->end_date->format('d.m.Y') }}<br>
                                    {{ $req->days_requested }} Arbeitstage
                                </div>
                                <form method="POST" action="{{ route('admin.vacation.approve', $req) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">✓ Bestätigen</button>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="this.closest('details').removeAttribute('open')">Abbrechen</button>
                                </form>
                            </div>
                        </details>
                        {{-- Ablehnen --}}
                        <details style="display:inline-block;">
                            <summary class="btn btn-sm btn-danger" style="cursor:pointer;list-style:none;">Ablehnen</summary>
                            <div style="position:absolute;z-index:10;background:var(--c-surface,#fff);border:1px solid var(--c-border,#e2e8f0);border-radius:6px;padding:.75rem;margin-top:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);min-width:260px;">
                                <form method="POST" action="{{ route('admin.vacation.reject', $req) }}">
                                    @csrf
                                    <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Ablehnungsgrund (optional)</label>
                                    <input type="text" name="decision_notes" maxlength="500"
                                           placeholder="Begründung…"
                                           style="width:100%;border:1px solid var(--c-border);border-radius:6px;padding:.35rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;margin-bottom:.4rem;">
                                    <button type="submit" class="btn btn-sm btn-danger">Ablehnen</button>
                                </form>
                            </div>
                        </details>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- Urlaub direkt eintragen --}}
<div class="card" style="margin-bottom:2rem;">
    <div class="card-header">
        <h2 style="margin:0;font-size:1.1rem;">Urlaub direkt eintragen</h2>
    </div>
    <div class="card-body">
        <p style="font-size:.85rem;color:var(--c-muted);margin:0 0 1rem;">Urlaub wird sofort als genehmigt gebucht und der Mitarbeiter wird benachrichtigt.</p>
        <form method="POST" action="{{ route('admin.vacation.store') }}"
              style="display:grid;grid-template-columns:2fr 1fr 1fr 2fr auto;gap:.75rem;align-items:end;flex-wrap:wrap;">
            @csrf
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Mitarbeiter</label>
                <select name="employee_id" required
                        style="width:100%;border:1px solid var(--c-border);border-radius:6px;padding:.45rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;">
                    <option value="">— auswählen —</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ old('employee_id') == $emp->id ? 'selected' : '' }}>
                            {{ $emp->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Von</label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" required
                       style="width:100%;border:1px solid var(--c-border);border-radius:6px;padding:.45rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Bis</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" required
                       style="width:100%;border:1px solid var(--c-border);border-radius:6px;padding:.45rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Notiz (optional)</label>
                <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500"
                       placeholder="z.B. Betriebsurlaub"
                       style="width:100%;border:1px solid var(--c-border);border-radius:6px;padding:.45rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;">
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Eintragen</button>
            </div>
        </form>
    </div>
</div>

{{-- Urlaubskonto pflegen --}}
<div class="card" style="margin-bottom:2rem;">
    <div class="card-header">
        <h2 style="margin:0;font-size:1.1rem;">Urlaubskonto pflegen</h2>
    </div>
    <div class="card-body">
        {{-- Saldo-Vorschau laden --}}
        <form method="GET" action="{{ route('admin.vacation.index') }}"
              style="display:grid;grid-template-columns:2fr 1fr auto;gap:.75rem;align-items:end;margin-bottom:1.25rem;">
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Mitarbeiter</label>
                <select name="balance_employee_id" onchange="this.form.submit()"
                        style="width:100%;border:1px solid var(--c-border);border-radius:6px;padding:.45rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;">
                    <option value="">— auswählen —</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ request('balance_employee_id') == $emp->id ? 'selected' : '' }}>
                            {{ $emp->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Jahr</label>
                <select name="balance_year" onchange="this.form.submit()"
                        style="width:100%;border:1px solid var(--c-border);border-radius:6px;padding:.45rem .6rem;font-size:.85rem;background:var(--c-bg,#f8fafc);color:inherit;">
                    @for($y = now()->year + 1; $y >= 2024; $y--)
                        <option value="{{ $y }}" {{ (request('balance_year', now()->year) == $y) ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-secondary">Anzeigen</button>
            </div>
        </form>

        @if($balancePreview)
        <div style="background:var(--c-bg,#f8fafc);border:1px solid var(--c-border);border-radius:8px;padding:1rem;margin-bottom:1rem;">
            <div style="font-weight:600;margin-bottom:.5rem;">
                {{ $balancePreview->employee->full_name }} — {{ $balancePreview->year }}
            </div>
            <div style="display:flex;gap:2rem;font-size:.9rem;margin-bottom:.75rem;">
                <span>Gesamt: <strong>{{ $balancePreview->total_days }}</strong></span>
                <span>Übertrag: <strong>{{ $balancePreview->carried_over }}</strong></span>
                <span>Verbraucht: <strong>{{ $balancePreview->used_days }}</strong></span>
                <span>Verbleibend: <strong style="color:{{ $balancePreview->remaining_days >= 0 ? 'var(--c-success,#16a34a)' : 'var(--c-danger,#dc2626)' }}">{{ $balancePreview->remaining_days }}</strong></span>
            </div>
            <form method="POST" action="{{ route('admin.vacation.balance') }}"
                  style="display:grid;grid-template-columns:1fr 1fr auto;gap:.75rem;align-items:end;">
                @csrf @method('PATCH')
                <input type="hidden" name="employee_id" value="{{ $balancePreview->employee->id }}">
                <input type="hidden" name="year" value="{{ $balancePreview->year }}">
                <div>
                    <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Jahresanspruch (Tage)</label>
                    <input type="number" name="total_days" min="0" max="365"
                           value="{{ $balancePreview->total_days }}"
                           style="width:100%;border:1px solid var(--c-border);border-radius:6px;padding:.45rem .6rem;font-size:.85rem;background:var(--c-surface,#fff);color:inherit;">
                </div>
                <div>
                    <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Übertrag aus Vorjahr (Tage)</label>
                    <input type="number" name="carried_over" min="0" max="100"
                           value="{{ $balancePreview->carried_over }}"
                           style="width:100%;border:1px solid var(--c-border);border-radius:6px;padding:.45rem .6rem;font-size:.85rem;background:var(--c-surface,#fff);color:inherit;">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
        @else
            <p style="color:var(--c-muted);font-size:.9rem;">Mitarbeiter und Jahr auswählen, um das Konto anzuzeigen oder zu bearbeiten.</p>
        @endif
    </div>
</div>

{{-- Entschiedene Anträge --}}
<div class="card">
    <div class="card-header">
        <h2 style="margin:0;font-size:1.1rem;">Entschiedene Anträge (letzte 20)</h2>
    </div>
    <div class="card-body" style="padding:0;">
        @if($recent->isEmpty())
            <p style="padding:1.5rem;color:var(--c-muted,#64748b);">Noch keine entschiedenen Anträge.</p>
        @else
        <table class="table">
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Von</th>
                    <th>Bis</th>
                    <th>Tage</th>
                    <th>Status</th>
                    <th>Entschieden am</th>
                    <th>Anmerkung</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recent as $req)
                <tr>
                    <td>{{ $req->employee->full_name }}</td>
                    <td>{{ $req->start_date->format('d.m.Y') }}</td>
                    <td>{{ $req->end_date->format('d.m.Y') }}</td>
                    <td>{{ $req->days_requested }}</td>
                    <td>
                        @if($req->status === 'approved')
                            <span class="badge badge-success">Genehmigt</span>
                        @elseif($req->status === 'rejected')
                            <span class="badge badge-danger">Abgelehnt</span>
                        @else
                            <span class="badge badge-secondary">Zurückgezogen</span>
                        @endif
                    </td>
                    <td>{{ $req->decided_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td>{{ $req->decision_notes ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
