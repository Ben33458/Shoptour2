<x-mail::message>
# Hallo {{ $user->first_name }},

willkommen bei **Kolabri Getraenke**! Wir freuen uns, dass du dich bei uns registriert hast.

Ab sofort kannst du bequem online bestellen und von unserem Heimdienst profitieren.

<x-mail::button :url="url('/mein-konto')">
Mein Konto ansehen
</x-mail::button>

Bei Fragen stehen wir dir jederzeit gerne zur Verfuegung.

Viele Gruesse,<br>
Dein Kolabri-Team
</x-mail::message>
