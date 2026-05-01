<x-mail::message>

## Mehrfachtreffer bei Konto-Aktivierung

**Zeitpunkt:** {{ now()->format('d.m.Y H:i:s') }}

Unter der folgenden E-Mail-Adresse wurden mehrere Kundenkonten gefunden, die noch keinem Benutzer zugeordnet sind:

**E-Mail:** {{ $email }}

### Betroffene Kundenkonten

<x-mail::table>
| Kundennr. | Name / Firma | Kunden-ID |
|:---------|:------------|----------:|
@foreach($customers as $customer)
| {{ $customer->customer_number }} | {{ $customer->displayName() }} | {{ $customer->id }} |
@endforeach
</x-mail::table>

**Aktion erforderlich:** Bitte prüfen Sie die oben genannten Konten manuell und klären Sie, welches Konto für den Nutzer aktiviert werden soll.

Der Nutzer wurde darüber informiert, dass eine manuelle Prüfung erforderlich ist.

</x-mail::message>
