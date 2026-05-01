<x-mail::message>

Sehr geehrte Damen und Herren,

anbei erhalten Sie unsere Bestellung **{{ $purchaseOrder->po_number }}** vom {{ $purchaseOrder->ordered_at?->format('d.m.Y') ?? now()->format('d.m.Y') }}.

@if($purchaseOrder->expected_at)
**Gewünschtes Lieferdatum:** {{ $purchaseOrder->expected_at->format('d.m.Y') }}
@endif

Bitte bestätigen Sie den Erhalt und das voraussichtliche Lieferdatum.

@if($purchaseOrder->notes)
**Bemerkung:** {{ $purchaseOrder->notes }}
@endif

Mit freundlichen Grüßen,
{{ config('app.name') }}

</x-mail::message>
