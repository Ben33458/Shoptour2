<x-mail::message>

Sehr geehrte Damen und Herren,

vielen Dank für Ihren Auftrag **#{{ $order->order_number ?? $order->id }}**! Wir haben Ihre Bestellung erfolgreich erhalten und werden diese schnellstmöglich bearbeiten.

@if($order->items && $order->items->isNotEmpty())
## Getränke & Produkte

<x-mail::table>
| Artikel | Menge | Preis (netto) |
|:---|---:|---:|
@foreach($order->items as $item)
| {{ $item->product?->produktname ?? $item->product_name ?? '–' }} | {{ $item->qty ?? $item->quantity }} | {{ number_format($item->totalNetMilli() / 1_000_000, 2, ',', '.') }} € |
@endforeach
</x-mail::table>
@endif

@if($order->rentalBookingItems && $order->rentalBookingItems->isNotEmpty())
## Leihartikel

@if($order->desired_delivery_date || $order->desired_pickup_date)
Zeitraum: {{ $order->desired_delivery_date ? \Carbon\Carbon::parse($order->desired_delivery_date)->format('d.m.Y') : '–' }} bis {{ $order->desired_pickup_date ? \Carbon\Carbon::parse($order->desired_pickup_date)->format('d.m.Y') : '–' }}
@endif

<x-mail::table>
| Artikel | Menge | Preis (netto) |
|:---|---:|---:|
@foreach($order->rentalBookingItems as $item)
| {{ $item->rentalItem?->name ?? '–' }} | {{ $item->quantity }} | {{ number_format($item->total_price_net_milli / 1_000_000, 2, ',', '.') }} € |
@endforeach
</x-mail::table>

@if($order->event_location_name)
Veranstaltungsort: {{ $order->event_location_name }}@if($order->event_location_street), {{ $order->event_location_street }}@endif@if($order->event_location_zip || $order->event_location_city), {{ $order->event_location_zip }} {{ $order->event_location_city }}@endif
@endif
@endif

@if($order->total_net_milli)
**Gesamtbetrag (netto): {{ number_format($order->total_net_milli / 1_000_000, 2, ',', '.') }} €**
@endif

Bei Fragen stehen wir Ihnen gerne zur Verfügung.

Mit freundlichen Grüßen,
{{ config('app.name') }}

</x-mail::message>
