<x-mail::message>

Sehr geehrte Damen und Herren,

anbei die 2. Mahnung zum Postversand. Bitte drucken und versenden Sie das beigefügte PDF an:

<x-mail::table>
| | |
|:---|:---|
| **Empfänger** | {{ $item->recipient_name }} |
| **Kundennr.** | {{ $customer->customer_number }} |
| **Offener Betrag** | {{ number_format($item->total_open_milli / 1_000_000, 2, ',', '.') }} € |
</x-mail::table>

Das Mahnschreiben ist dieser E-Mail als PDF beigefügt.

Vielen Dank.

</x-mail::message>
