<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rückgabeformular{{ $order ? ' #' . $order->id : '' }}</title>
    <style>
        @page {
            margin: 2cm 2cm 2cm 2.5cm;
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
            margin-bottom: 6mm;
        }
        .header-table {
            width: 100%;
        }
        .logo-cell {
            width: 40%;
            vertical-align: middle;
        }
        .logo-cell img {
            max-height: 20mm;
            max-width: 100%;
        }
        .logo-placeholder {
            font-size: 16pt;
            font-weight: bold;
            color: #2563eb;
            letter-spacing: 1px;
        }
        .title-cell {
            width: 60%;
            vertical-align: bottom;
            text-align: right;
        }
        .form-title {
            font-size: 15pt;
            font-weight: bold;
            color: #1a1a1a;
            border-bottom: 2pt solid #2563eb;
            padding-bottom: 1.5mm;
            display: inline-block;
        }

        /* ── Meta info box ── */
        .meta-box {
            width: 100%;
            margin-bottom: 6mm;
            border: 0.5pt solid #cbd5e1;
            border-radius: 2pt;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2.5pt 6pt;
            font-size: 8.5pt;
            vertical-align: middle;
        }
        .meta-table tr:not(:last-child) td {
            border-bottom: 0.3pt solid #e2e8f0;
        }
        .meta-label {
            color: #666;
            width: 14%;
            white-space: nowrap;
            font-size: 7.5pt;
        }
        .meta-value {
            font-weight: bold;
            width: 36%;
        }
        .meta-blank {
            border-bottom: 1pt dashed #999;
            min-width: 100pt;
            display: inline-block;
            height: 9pt;
            width: 85%;
        }

        /* ── Section heading ── */
        .section-heading {
            font-size: 9pt;
            font-weight: bold;
            color: #1e3a5f;
            background: #dbeafe;
            border-left: 3pt solid #2563eb;
            padding: 2pt 7pt;
            margin-bottom: 0;
            margin-top: 4mm;
        }

        /* ── Data tables ── */
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            font-size: 8pt;
        }
        .form-table thead tr {
            background-color: #f1f5f9;
        }
        .form-table th {
            padding: 2.5pt 4pt;
            text-align: left;
            font-weight: bold;
            font-size: 7pt;
            color: #444;
            border-bottom: 1pt solid #cbd5e1;
            border-right: 0.3pt solid #e2e8f0;
        }
        .form-table th:last-child { border-right: none; }
        .form-table td {
            padding: 3pt 4pt;
            border-bottom: 0.3pt solid #e2e8f0;
            border-right: 0.3pt solid #e2e8f0;
            vertical-align: middle;
        }
        .form-table td:last-child { border-right: none; }
        .form-table tr.blank-row td {
            height: 13pt;
        }
        .form-table tr.summary-row td {
            background: #f1f5f9;
            border-top: 1pt solid #cbd5e1;
            border-bottom: none;
            padding: 3pt 4pt;
        }
        .form-table .pos-col   { width: 5%;  text-align: center; }
        .form-table .artnr-col { width: 11%; }
        .form-table .desc-col  { width: 33%; }
        .form-table .desc-wide { width: 44%; }
        .form-table .qty-col   { width: 10%; text-align: center; }
        .form-table .input-col { width: 13%; }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-muted  { color: #94a3b8; font-size: 7pt; }

        /* Handwriting input line */
        .write-line {
            border-bottom: 1pt dashed #999;
            display: block;
            height: 9pt;
            width: 88%;
        }
        .write-line-wide {
            border-bottom: 1pt dashed #999;
            display: inline-block;
            height: 9pt;
            width: 60%;
        }

        /* ── Gesamt-Kontrollzählung Block ── */
        .total-block {
            margin-top: 4mm;
            margin-bottom: 4mm;
            border: 1pt solid #2563eb;
            border-radius: 2pt;
            background: #eff6ff;
            padding: 4pt 8pt;
        }
        .total-block-title {
            font-size: 8pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 3pt;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .total-block-table {
            width: 100%;
            border-collapse: collapse;
        }
        .total-block-table td {
            padding: 2pt 6pt 2pt 0;
            font-size: 8pt;
            vertical-align: bottom;
        }
        .total-block-label {
            color: #374151;
            white-space: nowrap;
            width: 20%;
        }
        .total-block-field {
            width: 25%;
        }
        .total-block-field-bold {
            width: 25%;
            font-weight: bold;
        }

        /* ── Bemerkungen ── */
        .remarks-block {
            margin-top: 4mm;
            margin-bottom: 4mm;
        }
        .remarks-label {
            font-size: 8pt;
            font-weight: bold;
            color: #374151;
            margin-bottom: 3pt;
        }
        .remarks-line {
            border-bottom: 0.7pt solid #94a3b8;
            display: block;
            height: 13pt;
            margin-bottom: 1pt;
            width: 100%;
        }

        /* ── Signature block ── */
        .signature-block {
            margin-top: 6mm;
            width: 100%;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sig-cell {
            width: 40%;
            vertical-align: bottom;
            padding-right: 6mm;
        }
        .sig-line {
            border-bottom: 1pt solid #374151;
            height: 16pt;
            display: block;
        }
        .sig-label {
            font-size: 7pt;
            color: #666;
            margin-top: 2pt;
        }
        .sig-date-cell {
            width: 18%;
            vertical-align: bottom;
        }
        .notice-text {
            font-size: 6.5pt;
            color: #94a3b8;
            margin-top: 5mm;
            text-align: center;
        }
    </style>
</head>
<body>

{{-- ══════════════════════════════════════════════════════════
     HEADER: Logo + Titel
═══════════════════════════════════════════════════════════════ --}}
<div class="header">
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @php
                    $logoPath = public_path('images/kolabri_logo.png');
                    $canEmbedLogo = file_exists($logoPath) && function_exists('imagecreatefrompng');
                @endphp
                @if($canEmbedLogo)
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logoPath)) }}"
                         alt="Kolabri Logo">
                @else
                    <div class="logo-placeholder">Kolabri</div>
                @endif
            </td>
            <td class="title-cell">
                <span class="form-title">Rückgabeformular</span>
            </td>
        </tr>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════════
     META: Kunden- / Bestell- / Lieferdaten
═══════════════════════════════════════════════════════════════ --}}
<div class="meta-box">
    <table class="meta-table">
        <tr>
            <td class="meta-label">Kunde</td>
            <td class="meta-value">
                @if($order?->customer)
                    {{ $order->customer->first_name }} {{ $order->customer->last_name }}
                @else
                    <span class="meta-blank"></span>
                @endif
            </td>
            <td class="meta-label">Kundennr.</td>
            <td class="meta-value">
                @if($order?->customer?->customer_number)
                    {{ $order->customer->customer_number }}
                @else
                    <span class="meta-blank"></span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="meta-label">Bestellnr.</td>
            <td class="meta-value">
                @if($order)
                    #{{ $order->id }}
                @else
                    <span class="meta-blank"></span>
                @endif
            </td>
            <td class="meta-label">Datum</td>
            <td class="meta-value">
                @if($order)
                    {{ now()->format('d.m.Y') }}
                @else
                    <span class="meta-blank"></span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="meta-label">Lieferdatum</td>
            <td class="meta-value">
                @if($order?->delivery_date)
                    {{ $order->delivery_date->format('d.m.Y') }}
                @else
                    <span class="meta-blank"></span>
                @endif
            </td>
            <td class="meta-label">Fahrer / MA</td>
            <td class="meta-value"><span class="meta-blank"></span></td>
        </tr>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════════
     ABSCHNITT 1: FESTBEDARF (nur bei Bestellungen mit Mietpositionen)
═══════════════════════════════════════════════════════════════ --}}
@php
    $festbedarf = $order?->rentalBookingItems ?? collect();
    $showFestbedarf = $order === null || $festbedarf->isNotEmpty();
@endphp
@if($showFestbedarf)
<div class="section-heading">Festbedarf (Leihartikel)</div>
<table class="form-table">
    <thead>
        <tr>
            <th class="pos-col">Pos.</th>
            <th class="desc-wide">Bezeichnung</th>
            <th class="qty-col text-center">Geliefert</th>
            <th class="input-col text-center">Rückgabe</th>
            <th class="input-col text-center">Verbrauchte<br>Menge</th>
            <th class="input-col text-center">Differenz</th>
        </tr>
    </thead>
    <tbody>
        @if($order && $festbedarf->isNotEmpty())
            @foreach($festbedarf as $i => $rbi)
                <tr>
                    <td class="pos-col text-center">{{ $i + 1 }}</td>
                    <td>{{ $rbi->rentalItem?->name ?? '—' }}</td>
                    <td class="text-center">{{ $rbi->quantity }}</td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                </tr>
            @endforeach
        @else
            @for($i = 0; $i < 8; $i++)
                <tr class="blank-row">
                    <td class="pos-col text-center text-muted">{{ $i + 1 }}</td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                </tr>
            @endfor
        @endif
    </tbody>
</table>
@endif

{{-- ══════════════════════════════════════════════════════════
     ABSCHNITT 2: VOLLGUT (Getränke)
═══════════════════════════════════════════════════════════════ --}}
<div class="section-heading">Vollgut (Getränke)</div>
<table class="form-table">
    <thead>
        <tr>
            <th class="pos-col">Pos.</th>
            <th class="artnr-col">Art.-Nr.</th>
            <th class="desc-col">Bezeichnung</th>
            <th class="qty-col text-center">Geliefert</th>
            <th class="input-col text-center">Rückgabe</th>
            <th class="input-col text-center">Verbrauchte<br>Menge</th>
        </tr>
    </thead>
    <tbody>
        @if($order && $order->items->isNotEmpty())
            @foreach($order->items as $i => $item)
                <tr>
                    <td class="pos-col text-center">{{ $i + 1 }}</td>
                    <td><code style="font-size:7pt">{{ $item->artikelnummer_snapshot }}</code></td>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td class="text-center">{{ $item->qty }}</td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                </tr>
            @endforeach
        @else
            @for($i = 0; $i < 10; $i++)
                <tr class="blank-row">
                    <td class="pos-col text-center text-muted">{{ $i + 1 }}</td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                </tr>
            @endfor
        @endif
        <tr class="summary-row">
            <td colspan="5" class="text-right" style="font-weight:bold;font-size:8pt;padding-right:6pt">
                Kontrollzählung Vollgut (Summe aller Positionen):
            </td>
            <td><span class="write-line"></span></td>
        </tr>
    </tbody>
</table>

{{-- ══════════════════════════════════════════════════════════
     ABSCHNITT 3: LEERGUT (Pfand)
═══════════════════════════════════════════════════════════════ --}}
<div class="section-heading">Leergut (Pfand)</div>
<table class="form-table">
    <thead>
        <tr>
            <th class="pos-col">Pos.</th>
            <th style="width:46%">Bezeichnung / Typ</th>
            <th class="qty-col text-center">Geliefert</th>
            <th class="input-col text-center">Rückgabe</th>
            <th class="input-col text-center">Differenz</th>
        </tr>
    </thead>
    <tbody>
        @if($order && count($leergut) > 0)
            @foreach($leergut as $i => $lg)
                <tr>
                    <td class="pos-col text-center">{{ $i + 1 }}</td>
                    <td>{{ $lg['bezeichnung'] }}</td>
                    <td class="text-center">{{ $lg['menge'] }}</td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                </tr>
            @endforeach
            @for($i = 0; $i < 5; $i++)
                <tr class="blank-row">
                    <td class="pos-col text-center text-muted">{{ count($leergut) + $i + 1 }}</td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                </tr>
            @endfor
        @else
            @for($i = 0; $i < 10; $i++)
                <tr class="blank-row">
                    <td class="pos-col text-center text-muted">{{ $i + 1 }}</td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                    <td><span class="write-line"></span></td>
                </tr>
            @endfor
        @endif
        <tr class="summary-row">
            <td colspan="4" class="text-right" style="font-weight:bold;font-size:8pt;padding-right:6pt">
                Kontrollzählung Leergut (Summe aller Positionen):
            </td>
            <td><span class="write-line"></span></td>
        </tr>
    </tbody>
</table>

{{-- ══════════════════════════════════════════════════════════
     GESAMT-KONTROLLZÄHLUNG
═══════════════════════════════════════════════════════════════ --}}
<div class="total-block">
    <div class="total-block-title">Gesamt-Kontrollzählung</div>
    <table class="total-block-table">
        <tr>
            <td class="total-block-label">Vollgut:</td>
            <td class="total-block-field"><span class="write-line-wide"></span></td>
            <td class="total-block-label">Leergut:</td>
            <td class="total-block-field"><span class="write-line-wide"></span></td>
            <td class="total-block-label" style="font-weight:bold">Gesamt:</td>
            <td class="total-block-field-bold"><span class="write-line-wide"></span></td>
        </tr>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════════
     BEMERKUNGEN
═══════════════════════════════════════════════════════════════ --}}
<div class="remarks-block">
    <div class="remarks-label">Bemerkungen / Sonstige Hinweise:</div>
    <span class="remarks-line"></span>
    <span class="remarks-line"></span>
    <span class="remarks-line"></span>
</div>

{{-- ══════════════════════════════════════════════════════════
     UNTERSCHRIFTEN
═══════════════════════════════════════════════════════════════ --}}
<div class="signature-block">
    <table class="signature-table">
        <tr>
            <td class="sig-cell">
                <span class="sig-line"></span>
                <div class="sig-label">Unterschrift Fahrer / Mitarbeiter</div>
            </td>
            <td style="width:16%"></td>
            <td class="sig-cell">
                <span class="sig-line"></span>
                <div class="sig-label">Unterschrift Kunde</div>
            </td>
            <td class="sig-date-cell">
                <span class="sig-line"></span>
                <div class="sig-label">Datum</div>
            </td>
        </tr>
    </table>
</div>

<div class="notice-text">
    Kolabri Getränke · {{ $company?->address ?? 'Odenwaldstr. 65, 64372 Ober-Ramstadt' }}
    · getraenke@kolabri.de
</div>

</body>
</html>
