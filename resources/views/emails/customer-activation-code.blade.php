<x-mail::message>

Hallo {{ $customer->first_name ?? 'Kunde' }},

Sie haben beantragt, Ihr Kundenkonto bei Kolabri Getränke zu aktivieren.

Ihr Bestätigungscode lautet:

# {{ $code }}

Dieser Code ist **15 Minuten** gültig.

Bitte geben Sie diesen Code auf der Aktivierungsseite ein. Geben Sie den Code nicht an Dritte weiter.

Falls Sie keine Aktivierung beantragt haben, können Sie diese E-Mail ignorieren. Ihr Konto bleibt unverändert.

Mit freundlichen Grüßen,<br>
{{ config('app.name') }}

</x-mail::message>
