<x-mail::message>
# Hallo {{ $employee->first_name }}, bitte bestätige deine E-Mail!

Du hast das Onboarding im **Kolabri Portal** gestartet. Bitte bestätige deine E-Mail-Adresse mit einer der folgenden Methoden:

---

**Option 1 — Direkt-Link (empfohlen):**

<x-mail::button :url="$verifyUrl">
E-Mail bestätigen
</x-mail::button>

---

**Option 2 — Code eingeben:**

<x-mail::panel>
<div style="text-align:center;font-size:32px;font-weight:800;letter-spacing:.2em;font-family:monospace;color:#1e90d0">{{ $code }}</div>
<div style="text-align:center;font-size:12px;color:#64748b;margin-top:6px">Diesen Code auf der Onboarding-Seite eingeben</div>
</x-mail::panel>

Der Link und der Code sind **24 Stunden** gültig.

Falls du das Onboarding nicht gestartet hast, kannst du diese E-Mail ignorieren.

Viele Grüße,<br>
Dein Kolabri-Team
</x-mail::message>
