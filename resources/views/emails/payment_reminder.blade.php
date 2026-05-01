<x-mail::message>

Sehr geehrte Damen und Herren,

für die Rechnung **{{ $invoice->invoice_number }}** steht noch ein offener Betrag aus.

<x-mail::table>
| | |
|:---|---:|
| **Offener Betrag** | **{{ number_format($invoice->balanceMilli() / 1_000_000, 2, ',', '.') }} €** |
</x-mail::table>

Bitte begleichen Sie den ausstehenden Betrag zeitnah.

Mit freundlichen Grüßen,

Benedikt Schneider<br>
Kolabri Getränke

</x-mail::message>
