<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rechnung {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 1.5cm 1.5cm 2cm 1.5cm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            line-height: 1.4;
        }

        /* ── Header ── */
        .header {
            width: 100%;
            margin-bottom: 12mm;
        }
        .header-table {
            width: 100%;
        }
        .logo-cell {
            width: 40%;
            vertical-align: top;
        }
        .logo-cell img {
            max-height: 30mm;
            max-width: 100%;
        }
        .logo-placeholder {
            font-size: 16pt;
            font-weight: bold;
            color: #2563eb;
            letter-spacing: 1px;
        }
        .company-cell {
            width: 60%;
            vertical-align: top;
            text-align: right;
            font-size: 8pt;
            color: #444;
        }
        .company-cell strong {
            font-size: 10pt;
            color: #1a1a1a;
        }

        /* ── Address + Invoice info ── */
        .address-section {
            width: 100%;
            margin-bottom: 8mm;
        }
        .address-table {
            width: 100%;
        }
        .recipient-cell {
            width: 55%;
            vertical-align: top;
        }
        .recipient-label {
            font-size: 7pt;
            color: #888;
            border-bottom: 0.3pt solid #ccc;
            margin-bottom: 2mm;
            padding-bottom: 1mm;
        }
        .invoice-meta-cell {
            width: 45%;
            vertical-align: top;
        }
        .meta-table {
            width: 100%;
            font-size: 8.5pt;
        }
        .meta-table td {
            padding: 1pt 0;
        }
        .meta-label {
            color: #666;
            width: 45%;
        }
        .meta-value {
            font-weight: bold;
        }

        /* ── Title ── */
        .invoice-title {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 6mm;
            color: #1a1a1a;
            border-bottom: 1pt solid #2563eb;
            padding-bottom: 2mm;
        }

        /* ── Line items table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
            font-size: 8.5pt;
        }
        .items-table thead tr {
            background-color: #f1f5f9;
        }
        .items-table th {
            padding: 3pt 5pt;
            text-align: left;
            font-weight: bold;
            font-size: 8pt;
            color: #444;
            border-bottom: 1pt solid #cbd5e1;
        }
        .items-table th.text-right,
        .items-table td.text-right {
            text-align: right;
        }
        .items-table td {
            padding: 3pt 5pt;
            border-bottom: 0.3pt solid #e2e8f0;
            vertical-align: top;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .line-deposit td {
            background-color: #f8fafc;
            color: #374151;
        }
        .line-adjustment td {
            color: #6b7280;
            font-style: italic;
        }
        .pos-col  { width: 5%; }
        .desc-col { width: 40%; }
        .qty-col  { width: 8%; text-align: right; }
        .unit-col { width: 7%; }
        .net-col  { width: 12%; text-align: right; }
        .gross-col{ width: 13%; text-align: right; }
        .total-col{ width: 15%; text-align: right; }

        /* ── Totals block ── */
        .totals-section {
            width: 100%;
            margin-bottom: 8mm;
        }
        .totals-table {
            width: 55%;
            margin-left: 45%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        .totals-table td {
            padding: 2pt 5pt;
        }
        .totals-table .label-cell {
            text-align: right;
            color: #555;
            width: 60%;
        }
        .totals-table .value-cell {
            text-align: right;
            width: 40%;
        }
        .totals-table tr.subtotal td {
            border-top: 0.5pt solid #cbd5e1;
        }
        .totals-table tr.total-final td {
            border-top: 1.5pt solid #1a1a1a;
            font-size: 10pt;
            font-weight: bold;
            padding-top: 3pt;
        }
        .totals-table tr.deposit td {
            color: #374151;
        }

        /* ── Footer ── */
        .footer {
            font-size: 7.5pt;
            color: #777;
            border-top: 0.5pt solid #e2e8f0;
            padding-top: 3mm;
            margin-top: 8mm;
        }
        .footer-table {
            width: 100%;
        }
        .footer-table td {
            vertical-align: top;
            padding-right: 5mm;
        }
    </style>
</head>
<body>

{{-- ══════════════════════════════════════════════════════════
     HEADER: Logo + Company
═══════════════════════════════════════════════════════════════ --}}
<div class="header">
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @php
                    $logoPath = public_path('images/kolabri-logo.png');
                    // DomPDF needs PHP GD to process PNG images.
                    // Fall back to text when GD is unavailable (e.g. dev/test environments).
                    $canEmbedLogo = file_exists($logoPath) && function_exists('imagecreatefrompng');
                @endphp
                @if($canEmbedLogo)
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logoPath)) }}"
                         alt="Kolabri Logo">
                @else
                    <div class="logo-placeholder">Kolabri</div>
                @endif
            </td>
            <td class="company-cell">
                <strong>{{ $company?->name ?? 'Kolabri Getränke' }}</strong><br>
                @if($company?->address)
                    {!! nl2br(e($company->address)) !!}<br>
                @endif
                @if($company?->vat_id)
                    USt-IdNr.: {{ $company->vat_id }}
                @endif
            </td>
        </tr>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════════
     ADDRESS BLOCK + INVOICE META
═══════════════════════════════════════════════════════════════ --}}
<div class="address-section">
    <table class="address-table">
        <tr>
            <td class="recipient-cell">
                <div class="recipient-label">Rechnungsempfänger</div>
                @php $customer = $invoice->order->customer @endphp
                @if($customer)
                    @if($customer->first_name || $customer->last_name)
                        <strong>{{ trim($customer->first_name . ' ' . $customer->last_name) }}</strong><br>
                    @endif
                    @if($customer->customer_number)
                        Kunden-Nr.: {{ $customer->customer_number }}<br>
                    @endif
                    @if($customer->delivery_address_text)
                        {!! nl2br(e($customer->delivery_address_text)) !!}
                    @endif
                @endif
            </td>
            <td class="invoice-meta-cell">
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Rechnungsnummer</td>
                        <td class="meta-value">{{ $invoice->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Rechnungsdatum</td>
                        <td class="meta-value">{{ $invoice->finalized_at?->format('d.m.Y') ?? now()->format('d.m.Y') }}</td>
                    </tr>
                    @if($invoice->order->delivery_date)
                    <tr>
                        <td class="meta-label">Lieferdatum</td>
                        <td class="meta-value">{{ $invoice->order->delivery_date->format('d.m.Y') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="meta-label">Bestellung</td>
                        <td class="meta-value">#{{ $invoice->order_id }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════════
     TITLE
═══════════════════════════════════════════════════════════════ --}}
<div class="invoice-title">Rechnung {{ $invoice->invoice_number }}</div>

{{-- ══════════════════════════════════════════════════════════
     LINE ITEMS TABLE
═══════════════════════════════════════════════════════════════ --}}
<table class="items-table">
    <thead>
        <tr>
            <th class="pos-col">Pos.</th>
            <th class="desc-col">Bezeichnung</th>
            <th class="qty-col text-right">Menge</th>
            <th class="unit-col">Einheit</th>
            <th class="net-col text-right">Netto-EP</th>
            <th class="gross-col text-right">Brutto-EP</th>
            <th class="total-col text-right">Gesamt brutto</th>
        </tr>
    </thead>
    <tbody>
        @php $pos = 1 @endphp
        @foreach($invoice->items as $item)
            @if($item->line_type === \App\Models\Admin\InvoiceItem::TYPE_PRODUCT)
            <tr>
                <td>{{ $pos++ }}</td>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ number_format($item->qty, 0, ',', '.') }}</td>
                <td>Stk.</td>
                <td class="text-right">{{ number_format($item->unit_price_net_milli / 1_000_000, 2, ',', '.') }} €</td>
                <td class="text-right">{{ number_format($item->unit_price_gross_milli / 1_000_000, 2, ',', '.') }} €</td>
                <td class="text-right">{{ number_format($item->line_total_gross_milli / 1_000_000, 2, ',', '.') }} €</td>
            </tr>
            @elseif($item->line_type === \App\Models\Admin\InvoiceItem::TYPE_DEPOSIT)
            <tr class="line-deposit">
                <td></td>
                <td><em>{{ $item->description }}</em></td>
                <td class="text-right">{{ number_format($item->qty, 0, ',', '.') }}</td>
                <td></td>
                <td class="text-right"></td>
                <td class="text-right"></td>
                <td class="text-right">{{ number_format($item->line_total_gross_milli / 1_000_000, 2, ',', '.') }} €</td>
            </tr>
            @elseif($item->line_type === \App\Models\Admin\InvoiceItem::TYPE_ADJUSTMENT)
            <tr class="line-adjustment">
                <td></td>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ number_format($item->qty, 0, ',', '.') }}</td>
                <td></td>
                <td class="text-right"></td>
                <td class="text-right"></td>
                <td class="text-right">{{ number_format($item->line_total_gross_milli / 1_000_000, 2, ',', '.') }} €</td>
            </tr>
            @endif
        @endforeach
    </tbody>
</table>

{{-- ══════════════════════════════════════════════════════════
     TOTALS
═══════════════════════════════════════════════════════════════ --}}
<div class="totals-section">
    <table class="totals-table">
        <tr>
            <td class="label-cell">Nettobetrag</td>
            <td class="value-cell">{{ number_format($invoice->total_net_milli / 1_000_000, 2, ',', '.') }} €</td>
        </tr>
        <tr>
            <td class="label-cell">MwSt.</td>
            <td class="value-cell">{{ number_format($invoice->total_tax_milli / 1_000_000, 2, ',', '.') }} €</td>
        </tr>
        @if($invoice->total_deposit_milli != 0)
        <tr class="deposit">
            <td class="label-cell">Pfand (brutto)</td>
            <td class="value-cell">{{ number_format($invoice->total_deposit_milli / 1_000_000, 2, ',', '.') }} €</td>
        </tr>
        @endif
        @if($invoice->total_adjustments_milli != 0)
        <tr>
            <td class="label-cell">Anpassungen</td>
            <td class="value-cell">{{ number_format($invoice->total_adjustments_milli / 1_000_000, 2, ',', '.') }} €</td>
        </tr>
        @endif
        <tr class="total-final">
            <td class="label-cell">Rechnungsbetrag brutto</td>
            <td class="value-cell">{{ number_format($invoice->total_gross_milli / 1_000_000, 2, ',', '.') }} €</td>
        </tr>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════════════════ --}}
<div class="footer">
    <table class="footer-table">
        <tr>
            <td>
                <strong>Bankverbindung:</strong><br>
                IBAN: DE__ ____ ____ ____ ____ __<br>
                BIC: __________
            </td>
            <td>
                <strong>Zahlungsziel:</strong><br>
                Bitte überweisen Sie den Rechnungsbetrag innerhalb von 14 Tagen unter Angabe der Rechnungsnummer.
            </td>
            <td>
                @if($company?->vat_id)
                    <strong>USt-IdNr.:</strong> {{ $company->vat_id }}
                @endif
            </td>
        </tr>
    </table>
</div>

</body>
</html>
