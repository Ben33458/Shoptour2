@extends('admin.layout')

@section('title', $employee->exists ? 'Mitarbeiter bearbeiten' : 'Neuer Mitarbeiter')

@section('content')
<div class="page-header">
    <h1>{{ $employee->exists ? 'Mitarbeiter: ' . $employee->full_name : 'Neuer Mitarbeiter' }}</h1>
    <div class="page-actions">
        <a href="{{ route('admin.employees.index') }}" class="btn btn-secondary">Zurück zur Liste</a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0;padding-left:1.2rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form method="POST"
      action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}">
    @csrf
    @if($employee->exists)
        @method('PATCH')
    @endif

    {{-- ── Grunddaten ────────────────────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header" style="font-weight:600">Grunddaten</div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

                <div class="form-group">
                    <label for="employee_number">Personalnummer <span style="color:red">*</span></label>
                    <input type="text" id="employee_number" name="employee_number" class="form-control"
                           value="{{ old('employee_number', $employee->employee_number) }}" required maxlength="20">
                </div>

                <div class="form-group">
                    <label for="first_name">Vorname <span style="color:red">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control"
                           value="{{ old('first_name', $employee->first_name) }}" required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="last_name">Nachname <span style="color:red">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control"
                           value="{{ old('last_name', $employee->last_name) }}" required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="email">E-Mail</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="{{ old('email', $employee->email) }}" maxlength="255">
                </div>

                <div class="form-group">
                    <label for="phone">Telefon</label>
                    <input type="text" id="phone" name="phone" class="form-control"
                           value="{{ old('phone', $employee->phone) }}" maxlength="30">
                </div>

                <div class="form-group">
                    <label for="birth_date">Geburtsdatum</label>
                    <input type="date" id="birth_date" name="birth_date" class="form-control"
                           value="{{ old('birth_date', $employee->birth_date?->format('Y-m-d')) }}">
                </div>

                <div class="form-group">
                    <label for="hire_date">Einstellungsdatum <span style="color:red">*</span></label>
                    <input type="date" id="hire_date" name="hire_date" class="form-control"
                           value="{{ old('hire_date', $employee->hire_date?->format('Y-m-d')) }}" required>
                </div>

                <div class="form-group">
                    <label for="leave_date">Austrittsdatum</label>
                    <input type="date" id="leave_date" name="leave_date" class="form-control"
                           value="{{ old('leave_date', $employee->leave_date?->format('Y-m-d')) }}">
                </div>

                <div class="form-group">
                    <label for="role">Rolle <span style="color:red">*</span></label>
                    <select id="role" name="role" class="form-control" required>
                        @foreach(['admin' => 'Admin', 'manager' => 'Manager', 'teamleader' => 'Teamleiter', 'employee' => 'Mitarbeiter'] as $val => $label)
                            <option value="{{ $val }}" {{ old('role', $employee->role) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="employment_type">Beschäftigungsart <span style="color:red">*</span></label>
                    <select id="employment_type" name="employment_type" class="form-control" required>
                        @foreach(['full_time' => 'Vollzeit', 'part_time' => 'Teilzeit', 'mini_job' => 'Minijob', 'intern' => 'Praktikant'] as $val => $label)
                            <option value="{{ $val }}" {{ old('employment_type', $employee->employment_type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="weekly_hours">Wochenstunden <span style="color:red">*</span></label>
                    <input type="number" id="weekly_hours" name="weekly_hours" class="form-control"
                           value="{{ old('weekly_hours', $employee->weekly_hours ?? 40) }}" required min="1" max="60">
                </div>

                <div class="form-group">
                    <label for="vacation_days_per_year">Urlaubstage/Jahr <span style="color:red">*</span></label>
                    <input type="number" id="vacation_days_per_year" name="vacation_days_per_year" class="form-control"
                           value="{{ old('vacation_days_per_year', $employee->vacation_days_per_year ?? 24) }}" required min="0" max="60">
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        <input type="checkbox" name="work_on_saturdays" value="1"
                               {{ old('work_on_saturdays', $employee->work_on_saturdays ?? false) ? 'checked' : '' }}>
                        <span>Samstag ist Arbeitstag (für Urlaubsberechnung)</span>
                    </label>
                </div>

                <div class="form-group">
                    <label for="pin">PIN (4 Ziffern)</label>
                    <input type="password" id="pin" name="pin" class="form-control"
                           placeholder="{{ $employee->exists ? 'Leer lassen = unverändert' : '4-stellige PIN' }}"
                           maxlength="4" pattern="\d{4}" inputmode="numeric">
                    <small style="color:var(--c-muted)">Wird für das Stempelgerät benötigt.</small>
                </div>

                @if($employee->exists)
                <div class="form-group" style="display:flex;align-items:center;gap:.5rem;margin-top:1.5rem;">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                           {{ old('is_active', $employee->is_active) ? 'checked' : '' }}>
                    <label for="is_active" style="margin:0;">Mitarbeiter aktiv</label>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Spitzname & Adresse ───────────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header" style="font-weight:600">Spitzname &amp; Adresse</div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

                <div class="form-group">
                    <label for="nickname">Spitzname / Rufname</label>
                    <input type="text" id="nickname" name="nickname" class="form-control"
                           value="{{ old('nickname', $employee->nickname) }}" maxlength="100"
                           @if(empty($employee->nickname) && ($ninox?->spitzname ?? ''))
                               placeholder="Ninox: {{ $ninox->spitzname }}"
                           @endif>
                </div>

                <div class="form-group">
                    <label for="address_street">Straße &amp; Hausnummer</label>
                    <input type="text" id="address_street" name="address_street" class="form-control"
                           value="{{ old('address_street', $employee->address_street) }}" maxlength="255">
                </div>

                <div class="form-group">
                    <label for="address_zip">PLZ</label>
                    <input type="text" id="address_zip" name="address_zip" class="form-control"
                           value="{{ old('address_zip', $employee->address_zip) }}" maxlength="20">
                </div>

                <div class="form-group">
                    <label for="address_city">Ort</label>
                    <input type="text" id="address_city" name="address_city" class="form-control"
                           value="{{ old('address_city', $employee->address_city) }}" maxlength="100">
                </div>
            </div>
        </div>
    </div>

    {{-- ── Notfallkontakt ────────────────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header" style="font-weight:600">Notfallkontakt</div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label for="emergency_contact_name">Name</label>
                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control"
                           value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}" maxlength="255">
                </div>
                <div class="form-group">
                    <label for="emergency_contact_phone">Telefon</label>
                    <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control"
                           value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}" maxlength="50">
                </div>
            </div>
        </div>
    </div>

    {{-- ── Ausstattung & Führerschein ────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header" style="font-weight:600">Ausstattung &amp; Führerschein</div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label for="clothing_size">Kleidungsgröße</label>
                    <input type="text" id="clothing_size" name="clothing_size" class="form-control"
                           value="{{ old('clothing_size', $employee->clothing_size) }}" maxlength="20"
                           placeholder="M, XL, 52 …">
                </div>
                <div class="form-group">
                    <label for="shoe_size">Schuhgröße</label>
                    <input type="text" id="shoe_size" name="shoe_size" class="form-control"
                           value="{{ old('shoe_size', $employee->shoe_size) }}" maxlength="20"
                           placeholder="42 …">
                </div>
                <div class="form-group">
                    <label for="drivers_license_class">Führerscheinklasse</label>
                    <input type="text" id="drivers_license_class" name="drivers_license_class" class="form-control"
                           value="{{ old('drivers_license_class', $employee->drivers_license_class) }}" maxlength="50"
                           placeholder="B, BE, C1 …">
                </div>
                <div class="form-group">
                    <label for="drivers_license_expiry">Führerschein gültig bis</label>
                    <input type="date" id="drivers_license_expiry" name="drivers_license_expiry" class="form-control"
                           value="{{ old('drivers_license_expiry', $employee->drivers_license_expiry?->format('Y-m-d')) }}">
                </div>
            </div>
        </div>
    </div>

    {{-- ── Bankverbindung & Notizen ─────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header" style="font-weight:600">Bankverbindung &amp; Notizen</div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label for="iban">IBAN</label>
                    <input type="text" id="iban" name="iban" class="form-control"
                           value="{{ old('iban', $employee->iban) }}" maxlength="50"
                           placeholder="DE00 1234 5678 9012 3456 78">
                </div>
                <div class="form-group" style="grid-column:1 / -1">
                    <label for="notes_employee">Interne Notizen</label>
                    <textarea id="notes_employee" name="notes_employee" class="form-control"
                              rows="3" maxlength="2000"
                              style="resize:vertical">{{ old('notes_employee', $employee->notes_employee) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Zuständigkeitsbereiche ───────────────────────────────────────── --}}
    @php
        $allZ     = \Illuminate\Support\Facades\DB::table('ninox_77_regelmaessige_aufgaben')
                        ->select('zustaendigkeit')->distinct()->whereNotNull('zustaendigkeit')
                        ->where('zustaendigkeit', '!=', '')->orderBy('zustaendigkeit')->pluck('zustaendigkeit');
        $currentZ = old('zustaendigkeit', $employee->zustaendigkeit ?? []);
    @endphp
    @if($allZ->isNotEmpty())
    <div class="card" style="margin-bottom:20px">
        <div class="card-header" style="font-weight:600">Zuständigkeitsbereiche</div>
        <div class="card-body">
            <p style="font-size:13px;color:var(--c-muted);margin-bottom:12px">Aufgaben im Mitarbeiterportal, für die dieser Mitarbeiter zuständig ist.</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach($allZ as $z)
                <label style="display:flex;align-items:center;gap:6px;padding:5px 12px;border:1px solid var(--c-border);border-radius:6px;cursor:pointer;font-size:13px;background:var(--c-bg);">
                    <input type="checkbox" name="zustaendigkeit[]" value="{{ $z }}"
                           style="accent-color:var(--c-primary);"
                           {{ in_array($z, $currentZ) ? 'checked' : '' }}>
                    {{ $z }}
                </label>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ── Speichern ────────────────────────────────────────────────────── --}}
    <div style="margin-bottom:20px;display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
        <button type="submit" class="btn btn-primary">
            {{ $employee->exists ? 'Speichern' : 'Mitarbeiter anlegen' }}
        </button>
        <a href="{{ route('admin.employees.index') }}" class="btn btn-secondary">Abbrechen</a>
    </div>

</form>

{{-- Reset-Onboarding außerhalb des Hauptformulars (HTML erlaubt keine verschachtelten Formulare) --}}
@if($employee->exists && $employee->onboarding_status !== 'pending')
<form method="POST" action="{{ route('admin.employees.reset-onboarding', $employee) }}"
      style="margin-bottom:20px"
      onsubmit="return confirm('Onboarding für {{ $employee->full_name }} wirklich zurücksetzen?')">
    @csrf
    <button type="submit" class="btn btn-sm"
            style="background:#f59e0b;color:#fff;border:none">
        Onboarding zurücksetzen
    </button>
</form>
@endif

{{-- ── Ninox-Verknüpfung (readonly, außerhalb des Formulars) ─────────────── --}}
@if($employee->exists && ($ninox || $ninoxAlt))
<div class="card" style="border-color:#10b981;margin-bottom:20px">
    <div class="card-header" style="font-weight:600;display:flex;justify-content:space-between;align-items:center">
        <span style="color:#10b981">
            Ninox-Verknüpfung
            @if($ninox) — Kehr #{{ $ninox->ninox_id }} @endif
            @if($ninoxAlt) — Alt #{{ $ninoxAlt->_ninox_id }} @endif
        </span>
        <form method="POST" action="{{ route('admin.employees.sync-ninox', $employee) }}"
              onsubmit="return syncNinoxConfirm(this)">
            @csrf
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <button type="submit" name="force" value="0" class="btn btn-sm btn-outline"
                        style="border-color:#10b981;color:#10b981">
                    Leere Felder füllen
                </button>
                <label style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--c-muted);cursor:pointer;">
                    <input type="checkbox" id="ninox-force" style="accent-color:#10b981;">
                    Vorhandene Daten überschreiben
                </label>
            </div>
        </form>
        <script>
        function syncNinoxConfirm(form) {
            var force = document.getElementById('ninox-force').checked;
            if (force) {
                if (!confirm('Achtung: Vorhandene Felder werden mit Ninox-Daten überschrieben. Shoptour2-Änderungen gehen verloren. Fortfahren?')) {
                    return false;
                }
                form.querySelector('[name="force"]').value = '1';
            }
            return true;
        }
        </script>
    </div>
    <div class="card-body">

        @if($ninox)
        {{-- Kehr DB (basic: Spitzname, Status, Profilbild) --}}
        <div style="font-size:11px;font-weight:600;color:var(--c-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">
            Kehr-Datenbank (aktuell)
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;font-size:13px;margin-bottom:20px">
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">ID</div>
                <div style="font-family:monospace">{{ $ninox->ninox_id }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Spitzname</div>
                <div>{{ $ninox->spitzname ?: '—' }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Status</div>
                <div style="color:{{ $ninox->status === 'Aktiv' ? '#10b981' : 'var(--c-muted)' }};font-weight:500">
                    {{ $ninox->status ?: '—' }}
                </div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Profilbild</div>
                <div style="font-size:12px;color:var(--c-muted)" title="{{ $ninox->profilbild ?? '' }}">
                    {{ $ninox->profilbild ? '📷 ' . $ninox->profilbild : '—' }}
                </div>
            </div>
        </div>
        @endif

        @if($ninoxAlt)
        {{-- Alt DB (comprehensive: email, address, IBAN, etc.) --}}
        @php
            $na = (array) $ninoxAlt;
            $altGeb = $na['Geburtsdatum'] ?? '';
            $altStr = trim(($na['Strasse'] ?? '') . ' ' . ($na['Hausnummer'] ?? ''));
        @endphp
        <div style="font-size:11px;font-weight:600;color:var(--c-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">
            Alt-Datenbank (umfassend)
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;font-size:13px">
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">E-Mail</div>
                <div>{{ $na['E-Mail'] ?? '—' }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Telefon</div>
                <div>{{ $na['Telefon'] ?? '—' }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Geburtsdatum</div>
                <div>{{ $altGeb ? \Carbon\Carbon::parse($altGeb)->format('d.m.Y') : '—' }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Spitzname</div>
                <div>{{ $na['Spitzname'] ?? '—' }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Adresse</div>
                <div>{{ $altStr ?: '—' }}<br>{{ $na['PLZ'] ?? '' }} {{ $na['Ort'] ?? '' }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">IBAN</div>
                <div style="font-family:monospace;font-size:12px">{{ $na['IBAN'] ?? '—' }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">T-Shirt / Schuhe</div>
                <div>{{ $na['T-Shirt Größe'] ?? '—' }} / {{ $na['Schuhgröße'] ?? '—' }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Planstunden/Woche</div>
                <div>{{ $na['Planstunden pro Woche'] ?? '—' }} h</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Beschäftigungsart</div>
                <div>{{ $na['Art der Anstellung'] ?? '—' }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Beschäftigt seit</div>
                <div>{{ isset($na['beschäftigt seit']) ? \Carbon\Carbon::parse($na['beschäftigt seit'])->format('d.m.Y') : '—' }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Position</div>
                <div>{{ $na['Position'] ?? '—' }}</div>
            </div>
            <div>
                <div style="color:var(--c-muted);font-size:11px;margin-bottom:2px">Alt-ID</div>
                <div style="font-family:monospace">{{ $na['_ninox_id'] ?? '—' }}</div>
            </div>
        </div>
        @endif

    </div>
</div>
@elseif($employee->exists)
<div class="card">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between">
        <span style="color:var(--c-muted);font-size:13px">Kein Ninox-Datensatz verknüpft.</span>
        <a href="{{ route('admin.reconcile.employees') }}" class="btn btn-sm btn-outline">
            Zum Ninox-Abgleich
        </a>
    </div>
</div>
@endif

{{-- ── Versendete E-Mails ─────────────────────────────────────────────── --}}
@if($employee->exists && isset($sentEmails) && $sentEmails->count() > 0)
<div class="card" style="margin-top:20px">
    <div class="card-header" style="font-weight:600">Versendete E-Mails ({{ $sentEmails->count() }})</div>
    <div class="card-body" style="padding:0">
        <table class="table" style="margin:0">
            <thead>
                <tr>
                    <th style="width:160px">Datum</th>
                    <th style="width:100px">Typ</th>
                    <th>Betreff</th>
                    <th>An</th>
                    <th style="width:80px">Status</th>
                    <th>Auslöser</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sentEmails as $mail)
                <tr>
                    <td style="font-size:12px;color:var(--c-muted)">
                        {{ $mail->created_at->format('d.m.Y H:i') }}
                    </td>
                    <td>
                        <span class="badge badge-info" style="font-size:11px">{{ $mail->type }}</span>
                    </td>
                    <td style="font-size:13px">
                        {{ $mail->subject }}
                        @if($mail->body_preview)
                        <div style="font-size:11px;color:var(--c-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px"
                             title="{{ $mail->body_preview }}">
                            {{ $mail->body_preview }}
                        </div>
                        @endif
                    </td>
                    <td style="font-size:12px;font-family:monospace">{{ $mail->to_address }}</td>
                    <td>
                        @if($mail->status === 'sent')
                            <span style="color:#10b981;font-weight:500;font-size:12px">✓ Gesendet</span>
                        @else
                            <span style="color:#ef4444;font-weight:500;font-size:12px" title="{{ $mail->error_message }}">✗ Fehler</span>
                        @endif
                    </td>
                    <td style="font-size:12px;color:var(--c-muted)">{{ $mail->triggered_by ?: '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
