<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Bestellung {{ $po->po_number }}</title>
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

        /* ── Address + PO meta ── */
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
        .po-meta-cell {
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
            width: 50%;
        }
        .meta-value {
            font-weight: bold;
        }

        /* ── Title ── */
        .po-title {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 6mm;
            color: #1a1a1a;
            border-bottom: 1pt solid #2563eb;
            padding-bottom: 2mm;
        }

        /* ── Items table ── */
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
        .item-notes {
            font-size: 7.5pt;
            color: #666;
            margin-top: 1pt;
        }

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
        .totals-table tr.total-final td {
            border-top: 1.5pt solid #1a1a1a;
            font-size: 10pt;
            font-weight: bold;
            padding-top: 3pt;
        }

        /* ── Notes ── */
        .notes {
            margin-top: 8mm;
            padding: 4mm;
            background: #f9fafb;
            border: 0.5pt solid #e2e8f0;
            font-size: 8.5pt;
        }
        .notes-label {
            font-weight: 700;
            margin-bottom: 2pt;
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
                    $canEmbedLogo = file_exists($logoPath) && function_exists('imagecreatefrompng');
                @endphp
                @if($canEmbedLogo)
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logoPath)) }}"
                         alt="Kolabri Logo">
                @else
                    <div class="logo-placeholder">{{ $company?->name ?? config('app.name') }}</div>
                @endif
            </td>
            <td class="company-cell">
                <strong>{{ $company?->name ?? config('app.name') }}</strong><br>
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
     ADDRESS BLOCK + PO META
═══════════════════════════════════════════════════════════════ --}}
<div class="address-section">
    <table class="address-table">
        <tr>
            <td class="recipient-cell">
                <div class="recipient-label">Lieferant</div>
                <strong>{{ $po->supplier->name ?? '—' }}</strong><br>
                @if($po->supplier?->contact_name)
                    z. Hd. {{ $po->supplier->contact_name }}<br>
                @endif
                @if($po->supplier?->address)
                    {!! nl2br(e($po->supplier->address)) !!}
                @endif
            </td>
            <td class="po-meta-cell">
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Bestellnummer</td>
                        <td class="meta-value">{{ $po->po_number }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Bestelldatum</td>
                        <td class="meta-value">{{ $po->ordered_at?->format('d.m.Y') ?? now()->format('d.m.Y') }}</td>
                    </tr>
                    @if($po->expected_at)
                    <tr>
                        <td class="meta-label">Gew. Lieferdatum</td>
                        <td class="meta-value">{{ $po->expected_at->format('d.m.Y') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="meta-label">Lieferort</td>
                        <td class="meta-value">{{ $po->warehouse->name ?? '—' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════════
     TITLE
═══════════════════════════════════════════════════════════════ --}}
<div class="po-title">Bestellung {{ $po->po_number }}</div>

{{-- ══════════════════════════════════════════════════════════
     LINE ITEMS TABLE
═══════════════════════════════════════════════════════════════ --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="width:5%">Pos.</th>
            <th style="width:13%">Art.-Nr.</th>
            <th style="width:42%">Bezeichnung</th>
            <th class="text-right" style="width:10%">Menge</th>
            <th class="text-right" style="width:15%">EK-Preis</th>
            <th class="text-right" style="width:15%">Gesamt</th>
        </tr>
    </thead>
    <tbody>
        @foreach($po->items as $index => $item)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $item->product->artikelnummer ?? '—' }}</td>
            <td>
                {{ $item->product->produktname ?? 'Produkt #' . $item->product_id }}
                @if($item->notes)
                    <div class="item-notes">{{ $item->notes }}</div>
                @endif
            </td>
            <td class="text-right">{{ number_format($item->qty, $item->qty == intval($item->qty) ? 0 : 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format($item->unit_purchase_milli / 1_000_000, 2, ',', '.') }} &euro;</td>
            <td class="text-right">{{ number_format($item->line_total_milli / 1_000_000, 2, ',', '.') }} &euro;</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ══════════════════════════════════════════════════════════
     TOTALS
═══════════════════════════════════════════════════════════════ --}}
<div class="totals-section">
    <table class="totals-table">
        <tr class="total-final">
            <td class="label-cell">Gesamtbetrag (netto)</td>
            <td class="value-cell">{{ number_format($po->total_milli / 1_000_000, 2, ',', '.') }} &euro;</td>
        </tr>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════════
     NOTES
═══════════════════════════════════════════════════════════════ --}}
@if($po->notes)
<div class="notes">
    <div class="notes-label">Bemerkung:</div>
    {!! nl2br(e($po->notes)) !!}
</div>
@endif

{{-- ══════════════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════════════════ --}}
<div class="footer">
    <table class="footer-table">
        <tr>
            <td>
                <strong>{{ $company?->name ?? config('app.name') }}</strong><br>
                @if($company?->address){{ $company->address }}@endif
            </td>
            <td>
                @if($company?->phone)
                    <strong>Tel:</strong> {{ $company->phone }}<br>
                @endif
                @if($company?->email)
                    {{ $company->email }}
                @endif
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
