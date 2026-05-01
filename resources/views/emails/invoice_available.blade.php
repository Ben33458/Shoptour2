<x-mail::message>

Sehr geehrte Damen und Herren,

Ihre Rechnung **{{ $invoice->invoice_number }}** vom {{ $invoice->finalized_at?->format('d.m.Y') }} wurde erstellt.

<x-mail::table>
| | |
|:---|---:|
| Nettobetrag | {{ number_format($invoice->total_net_milli / 1_000_000, 2, ',', '.') }} € |
| MwSt. | {{ number_format($invoice->total_tax_milli / 1_000_000, 2, ',', '.') }} € |
| **Gesamtbetrag** | **{{ number_format($invoice->total_gross_milli / 1_000_000, 2, ',', '.') }} €** |
</x-mail::table>

Die Rechnung steht Ihnen im Kundenportal zum Download bereit.

<x-mail::button :url="config('app.url') . '/mein-konto/rechnungen'">
Rechnung herunterladen
</x-mail::button>

Mit freundlichen Grüßen,
{{ config('app.name') }}

</x-mail::message>
