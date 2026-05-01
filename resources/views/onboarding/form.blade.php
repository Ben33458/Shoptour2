<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meine Daten – Kolabri Onboarding</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #0f172a; color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; padding: 2rem 1rem; }
    .container { max-width: 680px; margin: 0 auto; }
    .logo-bar { text-align: center; margin-bottom: 1.5rem; font-size: 1.2rem; font-weight: 700; color: #94a3b8; }
    h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: .35rem; }
    .intro { color: #94a3b8; font-size: .92rem; margin-bottom: 2rem; }
    .section { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem 1.75rem; margin-bottom: 1.25rem; }
    .section-title { font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; color: #60a5fa; font-weight: 700; margin-bottom: 1.25rem; }
    .form-row { display: grid; gap: 1rem; grid-template-columns: 1fr; }
    .form-row.two { grid-template-columns: 1fr 1fr; }
    .form-row.three { grid-template-columns: 1fr 1fr 1fr; }
    .field label { display: block; font-size: .78rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .3rem; }
    .field label .req { color: #f87171; margin-left: 2px; }
    .field input, .field select, .field textarea {
        width: 100%; background: #0f172a; border: 1px solid #334155; border-radius: 6px;
        color: #f1f5f9; font-size: .95rem; padding: .6rem .85rem; outline: none; transition: border-color .15s;
    }
    .field input:focus, .field select:focus, .field textarea:focus { border-color: #60a5fa; }
    .field input.is-invalid, .field select.is-invalid { border-color: #f87171; }
    .field-error { color: #f87171; font-size: .8rem; margin-top: .3rem; }
    .field textarea { resize: vertical; min-height: 80px; }
    .readonly-field { background: #0d1826; color: #64748b; cursor: not-allowed; }
    .hint { font-size: .78rem; color: #475569; margin-top: .3rem; }
    .pin-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
    .pin-row input { font-size: 1.5rem; text-align: center; letter-spacing: .25em; font-family: monospace; }
    .checkbox-field { display: flex; align-items: flex-start; gap: .75rem; padding: .75rem; border: 1px solid #334155; border-radius: 8px; cursor: pointer; }
    .checkbox-field input[type=checkbox] { width: 18px; height: 18px; margin-top: .1rem; flex-shrink: 0; accent-color: #1d4ed8; cursor: pointer; }
    .checkbox-field label { font-size: .9rem; color: #cbd5e1; cursor: pointer; }
    .checkbox-field label a { color: #60a5fa; }
    .alert-error { background: #7f1d1d; border: 1px solid #dc2626; color: #fecaca; border-radius: 8px; padding: .75rem 1rem; font-size: .9rem; margin-bottom: 1.25rem; }
    .btn-submit { width: 100%; padding: 1rem; background: #16a34a; color: #fff; border: none; border-radius: 10px; font-size: 1.05rem; font-weight: 700; cursor: pointer; margin-top: .5rem; transition: opacity .15s; }
    .btn-submit:hover { opacity: .85; }
    @media (max-width: 520px) {
        .form-row.two, .form-row.three, .pin-row { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>
<div class="container">
    <div class="logo-bar"><img src="{{ asset('images/kolabri_logo.png') }}" alt="Kolabri Getränke" style="height:50px;width:auto"></div>

    <h1>Hallo, {{ $employee->first_name }}!</h1>
    <p class="intro">Bitte prüfe und ergänze deine Daten. Mit * markierte Felder sind Pflichtfelder.</p>

    @if($errors->any())
        <div class="alert-error">
            @foreach($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('onboarding.post-form') }}" novalidate>
        @csrf

        {{-- ── 1. Persönliche Daten ── --}}
        <div class="section">
            <div class="section-title">Persönliche Daten</div>
            <div class="form-row two">
                <div class="field">
                    <label>Vorname <span class="req">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name', $employee->first_name) }}" required>
                    @error('first_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label>Nachname <span class="req">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name', $employee->last_name) }}" required>
                    @error('last_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="form-row two" style="margin-top:1rem">
                <div class="field">
                    <label>Geburtsdatum <span class="req">*</span></label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', $employee->birth_date?->format('Y-m-d')) }}" required>
                    @error('birth_date')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label>Rufname (optional)</label>
                    <input type="text" name="nickname" value="{{ old('nickname', $employee->nickname) }}" placeholder="z.B. Michi">
                </div>
            </div>
        </div>

        {{-- ── 2. Kontakt ── --}}
        <div class="section">
            <div class="section-title">Kontakt</div>
            <div class="form-row two">
                <div class="field">
                    <label>E-Mail <span class="req">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $employee->email) }}" required>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label>Mobilnummer <span class="req">*</span></label>
                    <input type="tel" name="phone" value="{{ old('phone', $employee->phone) }}" placeholder="+49 …" required>
                    @error('phone')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ── 3. Anschrift ── --}}
        <div class="section">
            <div class="section-title">Anschrift</div>
            <div class="field" style="margin-bottom:1rem">
                <label>Straße und Hausnummer <span class="req">*</span></label>
                <input type="text" name="address_street" value="{{ old('address_street', $employee->address_street) }}" required>
                @error('address_street')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-row two">
                <div class="field">
                    <label>PLZ <span class="req">*</span></label>
                    <input type="text" name="address_zip" value="{{ old('address_zip', $employee->address_zip) }}" required>
                    @error('address_zip')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label>Ort <span class="req">*</span></label>
                    <input type="text" name="address_city" value="{{ old('address_city', $employee->address_city) }}" required>
                    @error('address_city')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ── 4. Bankdaten ── --}}
        <div class="section">
            <div class="section-title">Bankdaten</div>
            <div class="field">
                <label>IBAN <span class="req">*</span></label>
                <input type="text" name="iban" value="{{ old('iban', $employee->iban) }}" placeholder="DE89 3704 0044 0532 0130 00" required>
                <div class="hint">Leerzeichen werden automatisch entfernt.</div>
                @error('iban')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- ── 5. Notfallkontakt ── --}}
        <div class="section">
            <div class="section-title">Notfallkontakt</div>
            <p style="font-size:.85rem;color:#64748b;margin-bottom:1.25rem;line-height:1.6">
                Bitte gib eine Person an, die wir im Notfall erreichen können – zum Beispiel bei einem
                Arbeitsunfall oder einem gesundheitlichen Notfall während der Arbeitszeit.
                Diese Daten werden ausschließlich für Notfälle genutzt und vertraulich behandelt.
                Sie werden nicht an Dritte weitergegeben.
            </p>
            <div class="form-row two">
                <div class="field">
                    <label>Name <span class="req">*</span></label>
                    <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}" required>
                    @error('emergency_contact_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label>Telefon <span class="req">*</span></label>
                    <input type="tel" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}" required>
                    @error('emergency_contact_phone')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ── 6. Optionale Angaben ── --}}
        <div class="section">
            <div class="section-title">Optionale Angaben</div>
            <div class="form-row three">
                <div class="field">
                    <label>Kleidergröße</label>
                    <input type="text" name="clothing_size" value="{{ old('clothing_size', $employee->clothing_size) }}" placeholder="M, XL, 42 …">
                </div>
                <div class="field">
                    <label>Schuhgröße</label>
                    <input type="text" name="shoe_size" value="{{ old('shoe_size', $employee->shoe_size) }}" placeholder="42">
                </div>
                <div class="field">
                    <label>Führerscheinklasse</label>
                    <input type="text" name="drivers_license_class" value="{{ old('drivers_license_class', $employee->drivers_license_class) }}" placeholder="B, BE …">
                </div>
            </div>
            <div class="form-row two" style="margin-top:1rem">
                <div class="field">
                    <label>Führerschein gültig bis</label>
                    <input type="date" name="drivers_license_expiry" value="{{ old('drivers_license_expiry', $employee->drivers_license_expiry?->format('Y-m-d')) }}">
                </div>
            </div>
            <div class="field" style="margin-top:1rem">
                <label>Bemerkungen</label>
                <textarea name="notes_employee" placeholder="Sonstige Hinweise …">{{ old('notes_employee', $employee->notes_employee) }}</textarea>
            </div>
        </div>

        {{-- ── 7. Personalnummer + PIN ── --}}
        <div class="section">
            <div class="section-title">Personalnummer und PIN</div>
            <p style="font-size:.88rem;color:#94a3b8;margin-bottom:1.25rem">
                Wähle eine 4-stellige Personalnummer und eine 4-stellige PIN für die Stempeluhr.
                PIN und Personalnummer dürfen nicht identisch sein.
            </p>
            <div class="pin-row">
                <div class="field">
                    <label>Personalnummer <span class="req">*</span></label>
                    <input type="text" name="employee_number" inputmode="numeric"
                           maxlength="4" pattern="\d{4}" placeholder="····"
                           value="{{ old('employee_number') }}" required>
                    @error('employee_number')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label>PIN <span class="req">*</span></label>
                    <input type="password" name="pin" inputmode="numeric"
                           maxlength="4" pattern="\d{4}" placeholder="····"
                           autocomplete="new-password" required>
                    @error('pin')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label>PIN wiederholen <span class="req">*</span></label>
                    <input type="password" name="pin_confirmation" inputmode="numeric"
                           maxlength="4" pattern="\d{4}" placeholder="····"
                           autocomplete="new-password" required>
                    @error('pin_confirmation')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
            @error('credentials')<div class="field-error" style="margin-top:.5rem">{{ $message }}</div>@enderror
        </div>

        {{-- ── 8. Zustimmungen ── --}}
        <div class="section">
            <div class="section-title">Zustimmungen</div>
            <div style="display:flex;flex-direction:column;gap:.75rem">
                <div class="checkbox-field">
                    <input type="checkbox" name="privacy_accepted" id="privacy" value="1" {{ old('privacy_accepted') ? 'checked' : '' }}>
                    <label for="privacy">
                        Ich habe die <a href="{{ route('page.show', 'datenschutz') }}" target="_blank">Datenschutzerklärung</a> gelesen und stimme der Verarbeitung meiner personenbezogenen Daten zu. <span class="req">*</span>
                    </label>
                </div>
                @error('privacy_accepted')<div class="field-error">{{ $message }}</div>@enderror

                <div class="checkbox-field">
                    <input type="checkbox" name="data_correct" id="data_correct" value="1" {{ old('data_correct') ? 'checked' : '' }}>
                    <label for="data_correct">
                        Ich bestätige, dass alle meine Angaben wahrheitsgemäß und vollständig sind. <span class="req">*</span>
                    </label>
                </div>
                @error('data_correct')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="btn-submit">Angaben einreichen →</button>
        <p style="text-align:center;font-size:.82rem;color:#475569;margin-top:.75rem">
            Deine Angaben werden intern geprüft. Danach erhältst du Zugang zur Stempeluhr.
        </p>
    </form>
</div>
</body>
</html>
