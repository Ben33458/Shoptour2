<x-mail::message>
# Willkommen im Team, {{ $employee->first_name }}!

Du bist jetzt als **{{ $employee->full_name }}** im **Kolabri Portal** registriert.

**Deine Stammdaten:**
- Mitarbeiternummer: {{ $employee->employee_number }}
- Dabei seit: {{ $employee->hire_date?->format('d.m.Y') }}

Falls du Fragen hast oder etwas ändern möchtest, wende dich bitte an deine Teamleitung.

<x-mail::button :url="config('app.url') . '/mein'">
Zum Kolabri Mitarbeiterportal
</x-mail::button>

Viele Grüße,<br>
Dein Kolabri-Team
</x-mail::message>
