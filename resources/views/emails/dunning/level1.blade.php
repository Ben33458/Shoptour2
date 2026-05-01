<x-mail::message>

@if($customer->isKehr())
<x-mail::panel>
**Hinweis:** Ihre Geschäftsbeziehung wurde von *Getränke Kehr* auf *Kolabri Getränke* übertragen. Bitte überweisen Sie den offenen Betrag an das Konto von Kolabri Getränke.
</x-mail::panel>

@endif
Sehr geehrte Damen und Herren,

wir möchten Sie freundlich daran erinnern, dass folgende Rechnungen noch ausstehen.
Im Anhang finden Sie das Mahnschreiben als PDF sowie die zugehörigen Rechnungen.

Falls die Zahlung bereits erfolgt ist — vielen Dank! Sie können diese E-Mail in diesem Fall ignorieren.

<x-mail::table>
| Belegnummer | Rechnungsdatum | Fälligkeit | Rechnungsbetrag | Offener Betrag |
|:---|:---|:---|---:|---:|
@foreach($vouchers as $v)
| {{ $v->voucher_number ?? '–' }} | {{ $v->voucher_date?->format('d.m.Y') ?? '–' }} | {{ $v->due_date?->format('d.m.Y') ?? '–' }} | {{ number_format($v->total_gross_amount / 1_000_000, 2, ',', '.') }} € | **{{ number_format($v->open_amount / 1_000_000, 2, ',', '.') }} €** |
@endforeach
</x-mail::table>

**Gesamtbetrag offen: {{ number_format($item->total_open_milli / 1_000_000, 2, ',', '.') }} €**
@if($item->interest_milli > 0)
zzgl. Verzugszinsen: {{ number_format($item->interest_milli / 1_000_000, 2, ',', '.') }} €
@endif
@if($item->flat_fee_milli > 0)
zzgl. Verzugspauschale: {{ number_format($item->flat_fee_milli / 1_000_000, 2, ',', '.') }} €
@endif

Bei Fragen stehen wir Ihnen gerne zur Verfügung.

Mit freundlichen Grüßen,
{{ $senderName }}

</x-mail::message>
