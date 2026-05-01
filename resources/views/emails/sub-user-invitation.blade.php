<x-mail::message>

# Einladung zum Kundenkonto

Hallo {{ $invitation->first_name }},

@php
$companyName = $parentCustomer->company_name
    ?: trim(($parentCustomer->first_name ?? '') . ' ' . ($parentCustomer->last_name ?? ''));
@endphp

Sie wurden eingeladen, im Namen von **{{ $companyName }}** Bestellungen aufzugeben.

Klicken Sie auf den folgenden Link, um Ihr Konto einzurichten. Der Link ist **48 Stunden** gültig.

<x-mail::button :url="$acceptUrl">
Einladung annehmen
</x-mail::button>

Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:
{{ $acceptUrl }}

---

Sie erhalten diese E-Mail, weil {{ $companyName }} Sie als Unterbenutzer eingeladen hat. Wenn Sie diese Einladung nicht erwartet haben, können Sie diese E-Mail ignorieren.

</x-mail::message>
