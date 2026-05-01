<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $subject }}</title>
    <style>
        /*
         * DIN 5008 Form A — Standard German business letter
         * A4 (210 × 297 mm), all coordinates from paper top-left edge.
         *
         * Key zones:
         *   0–45 mm    Header (logo, company info)
         *  45–90 mm    Address window (left) + Info block (right)
         *  90–97 mm    Gap / breathing room
         *  97+ mm      Subject + body content
         *  bottom       Footer (fixed, ~22 mm)
         *
         * Fold marks: 105 mm and 210 mm from paper top.
         * Left content margin: 25 mm  |  Right content margin: 20 mm
         * Address window: 20 mm from left (5 mm inside left margin)
         */

        @page {
            size: A4;
            margin: 0;
        }

        html, body {
            margin: 0 !important;
            padding: 0 !important;
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
            line-height: 1.45;
            /* Push flowing content below the fixed header/address area + footer space */
            padding-top: 100mm;
            padding-left: 25mm;
            padding-right: 20mm;
            padding-bottom: 28mm;
        }

        /* ── Fold marks (DIN 5008) ─────────────────────────────── */
        .fold-mark {
            position: fixed;
            left: 5mm;
            width: 8mm;
            height: 0.3pt;
            background-color: #aaa;
            font-size: 0;
            line-height: 0;
            overflow: hidden;
        }
        .fold-mark-1 { top: 105mm; }
        .fold-mark-2 { top: 210mm; }

        /* ── Page header: Logo + company info (0–45 mm) ────────── */
        .page-header {
            position: fixed;
            top: 7mm;
            left: 25mm;
            right: 20mm;
            height: 35mm;
        }
        .header-table {
            width: 100%;
        }
        .logo-cell {
            width: 45%;
            vertical-align: top;
        }
        .logo-cell img {
            max-height: 24mm;
            max-width: 100%;
        }
        .logo-text {
            font-size: 18pt;
            font-weight: bold;
            color: #15803d;
            letter-spacing: 1px;
        }
        .company-cell {
            width: 55%;
            vertical-align: top;
            text-align: right;
            font-size: 7.5pt;
            color: #444;
            line-height: 1.5;
        }
        .company-cell strong {
            font-size: 9pt;
            color: #1a1a1a;
        }

        /* ── Address window: 45–90 mm from top, 20–105 mm from left (DIN 5008) ── */
        .address-block {
            position: fixed;
            top: 45mm;
            left: 20mm;
            width: 85mm;
            height: 45mm;
        }
        .sender-line {
            font-size: 6.5pt;
            color: #666;
            border-bottom: 0.3pt solid #bbb;
            padding-bottom: 0.8mm;
            margin-bottom: 1.5mm;
            white-space: nowrap;
            overflow: hidden;
        }
        .recipient-address {
            font-size: 9.5pt;
            line-height: 1.7;
        }

        /* ── Info block (date, Kunden-Nr.): right side, 45–90 mm ── */
        .info-block {
            position: fixed;
            top: 45mm;
            left: 125mm;
            right: 20mm;
            height: 45mm;
            text-align: right;
            font-size: 8.5pt;
            color: #333;
            line-height: 1.7;
        }

        /* ── Subject line ───────────────────────────────────────── */
        .subject {
            font-size: 11.5pt;
            font-weight: bold;
            margin-bottom: 5mm;
            padding-bottom: 2mm;
            border-bottom: 1.5pt solid #15803d;
            color: #1a1a1a;
        }

        /* ── Body text ──────────────────────────────────────────── */
        .body-text {
            font-size: 9pt;
            line-height: 1.6;
            margin-bottom: 6mm;
        }

        /* ── Invoice table ──────────────────────────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4mm;
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
            white-space: nowrap;
        }
        .items-table td {
            padding: 3pt 5pt;
            border-bottom: 0.3pt solid #e2e8f0;
            vertical-align: top;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .overdue-cell {
            color: #b91c1c;
        }

        /* ── Totals ─────────────────────────────────────────────── */
        .totals-table {
            width: 55%;
            margin-left: 45%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 6mm;
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
            white-space: nowrap;
        }
        .totals-table tr.total-final td {
            border-top: 1.5pt solid #1a1a1a;
            font-size: 10pt;
            font-weight: bold;
            padding-top: 3pt;
        }
        .totals-table tr.subtotal td {
            border-top: 0.5pt solid #cbd5e1;
        }

        /* ── Payment box ────────────────────────────────────────── */
        .payment-box {
            border: 0.5pt solid #15803d;
            border-radius: 2pt;
            padding: 4mm 5mm;
            margin-bottom: 6mm;
            font-size: 8.5pt;
            background-color: #f0fdf4;
            page-break-inside: avoid;
        }
        .payment-box strong {
            font-size: 9pt;
        }
        .payment-table {
            width: 100%;
            margin-top: 2mm;
        }
        .payment-table td {
            padding: 1pt 0;
            vertical-align: top;
        }
        .payment-label {
            width: 30%;
            color: #555;
        }

        /* ── Closing ────────────────────────────────────────────── */
        .closing {
            font-size: 9pt;
            line-height: 1.6;
            margin-bottom: 8mm;
        }

        /* ── Footer (fixed, repeats on all pages) ───────────────── */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 22mm;
            font-size: 6.5pt;
            color: #777;
            border-top: 0.5pt solid #e2e8f0;
            padding: 2mm 20mm 2mm 25mm;
            line-height: 1.3;
        }
        .footer-table {
            width: 100%;
            table-layout: fixed;
        }
        .footer-table td {
            vertical-align: top;
            padding-right: 4mm;
        }
        .footer-table td:last-child {
            padding-right: 0;
        }
    </style>
</head>
<body>

{{-- Falzmarken DIN 5008 --}}
<div class="fold-mark fold-mark-1"></div>
<div class="fold-mark fold-mark-2"></div>

{{-- ══ HEADER: Logo + Absender-Info ════════════════════════════ --}}
<div class="page-header">
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if($logoBase64)
                    <img src="data:image/png;base64,{{ $logoBase64 }}" alt="Kolabri Logo">
                @else
                    <div class="logo-text">Kolabri</div>
                @endif
            </td>
            <td class="company-cell">
                <strong>Kolabri Getränke</strong><br>
                Benedikt Schneider<br>
                Odenwaldstr. 65 · 64372 Ober-Ramstadt<br>
                Tel.: 06151–9501441 · Mobil: 0152-01932110<br>
                getraenke@kolabri.de
            </td>
        </tr>
    </table>
</div>

{{-- ══ ANSCHRIFTFELD (DIN 5008: 45–90 mm, 20–105 mm) ═════════ --}}
<div class="address-block">
    <div class="sender-line">Kolabri Getränke · Odenwaldstr. 65 · 64372 Ober-Ramstadt</div>
    <div class="recipient-address">
        {{ $recipientName }}<br>
        @if($recipientStreet){{ $recipientStreet }}<br>@endif
        @if($recipientZipCity){{ $recipientZipCity }}<br>@endif
    </div>
</div>

{{-- ══ INFORMATIONSBLOCK (rechts, 45–90 mm) ═══════════════════ --}}
<div class="info-block">
    Ober-Ramstadt, {{ $date }}<br>
    <br>
    Kunden-Nr.: {{ $customerNumber }}<br>
    @if($level >= 2)
        <span style="color:#b91c1c;font-weight:bold">2. Mahnung</span>
    @else
        Zahlungserinnerung
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════════
     FLIESSTEXT (ab padding-top: 100 mm)
═══════════════════════════════════════════════════════════════════ --}}

{{-- Betreff --}}
<div class="subject">{{ $subject }}</div>

@if($isKehr ?? false)
<div style="border:1px solid #ccc;padding:8px 12px;margin-bottom:12px;font-size:9pt;color:#555">
    <strong>Hinweis:</strong> Ihre Geschäftsbeziehung wird von <em>Getränke Kehr</em>
    auf <em>Kolabri Getränke</em> übertragen. Bitte überweisen Sie den offenen Betrag
    an das unten angegebene Konto von Kolabri Getränke.
</div>
@endif

{{-- Anschreiben --}}
<div class="body-text">
    @if($level >= 2)
        Sehr geehrte Damen und Herren,<br><br>
        leider haben wir trotz unserer Zahlungserinnerung vom {{ $firstDunningDate ?? 'kürzlich' }} noch
        keinen Zahlungseingang für die nachfolgend aufgeführten Rechnungen verbuchen können.<br><br>
        Wir bitten Sie dringend, den ausstehenden Betrag bis spätestens <strong>{{ $deadline }}</strong>
        auf unser Konto zu überweisen. Sollten Sie Fragen haben oder die Zahlung bereits veranlasst haben,
        melden Sie sich bitte bei uns — wir helfen Ihnen gerne weiter.<br><br>
        Bitte beachten Sie: Sollte bis zum genannten Datum kein Zahlungseingang vorliegen, sehen wir uns
        leider gezwungen, weitere Schritte einzuleiten.
    @else
        Sehr geehrte Damen und Herren,<br><br>
        wir hoffen, dass alles in Ordnung ist, und möchten Sie freundlich daran erinnern, dass folgende
        Rechnung{{ $vouchers->count() > 1 ? 'en' : '' }} noch offen {{ $vouchers->count() > 1 ? 'sind' : 'ist' }}.<br><br>
        Bitte überweisen Sie den ausstehenden Betrag bis spätestens <strong>{{ $deadline }}</strong>
        auf unser Konto. Falls die Zahlung zwischenzeitlich bereits erfolgt ist, betrachten Sie dieses
        Schreiben bitte als gegenstandslos — wir bedanken uns herzlich!
    @endif
</div>

{{-- Rechnungsübersicht --}}
<table class="items-table">
    <thead>
        <tr>
            <th>Rechnungsnummer</th>
            <th>Rechnungsdatum</th>
            <th>Fällig am</th>
            <th class="text-right">Überfällig seit</th>
            <th class="text-right">Offener Betrag</th>
        </tr>
    </thead>
    <tbody>
        @foreach($vouchers as $v)
        <tr>
            <td>{{ $v->voucher_number ?? '—' }}</td>
            <td>{{ $v->voucher_date?->format('d.m.Y') ?? '—' }}</td>
            <td>{{ $v->due_date?->format('d.m.Y') ?? '—' }}</td>
            <td class="text-right overdue-cell">
                @php $days = $v->daysOverdue() @endphp
                @if($days > 0){{ $days }} {{ $days === 1 ? 'Tag' : 'Tage' }}@else—@endif
            </td>
            <td class="text-right">{{ number_format($v->open_amount / 1_000_000, 2, ',', '.') }} €</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Summen --}}
<table class="totals-table">
    <tr>
        <td class="label-cell">Offener Rechnungsbetrag</td>
        <td class="value-cell">{{ number_format($item->total_open_milli / 1_000_000, 2, ',', '.') }} €</td>
    </tr>
    @if($item->interest_milli > 0)
    <tr class="subtotal">
        <td class="label-cell">Verzugszinsen ({{ $interestRatePct }})</td>
        <td class="value-cell">{{ number_format($item->interest_milli / 1_000_000, 2, ',', '.') }} €</td>
    </tr>
    @endif
    @if($item->flat_fee_milli > 0)
    <tr>
        <td class="label-cell">Verzugspauschale (§ 288 Abs. 5 BGB)</td>
        <td class="value-cell">{{ number_format($item->flat_fee_milli / 1_000_000, 2, ',', '.') }} €</td>
    </tr>
    @endif
    <tr class="total-final">
        <td class="label-cell">Gesamtbetrag</td>
        <td class="value-cell">{{ number_format($item->totalDueMilli() / 1_000_000, 2, ',', '.') }} €</td>
    </tr>
</table>

{{-- Zahlungsbox --}}
<div class="payment-box">
    <strong>Bitte überweisen Sie bis zum {{ $deadline }}:</strong>
    <table class="payment-table">
        <tr>
            <td class="payment-label">Betrag:</td>
            <td><strong>{{ number_format($item->totalDueMilli() / 1_000_000, 2, ',', '.') }} €</strong></td>
        </tr>
        <tr>
            <td class="payment-label">Kontoinhaber:</td>
            <td>Benedikt Schneider</td>
        </tr>
        <tr>
            <td class="payment-label">IBAN:</td>
            <td>DE98 1101 0101 5660 6254 01</td>
        </tr>
        <tr>
            <td class="payment-label">BIC:</td>
            <td>SOBKDEB2XXX · solarisBank Gf (S)</td>
        </tr>
        <tr>
            <td class="payment-label">Verwendungszweck:</td>
            <td>Kunden-Nr. {{ $customerNumber }}
                @if($vouchers->count() === 1), Rg. {{ $vouchers->first()->voucher_number ?? '' }}@endif
            </td>
        </tr>
    </table>
</div>

{{-- Grußformel --}}
<div class="closing">
    Bei Fragen oder wenn Sie bereits gezahlt haben, melden Sie sich bitte unter
    <strong>getraenke@kolabri.de</strong> oder <strong>06151–9501441</strong>.<br><br>
    Mit freundlichen Grüßen<br><br>
    Benedikt Schneider<br>
    Kolabri Getränke
</div>

{{-- ══ FUSSZEILE (fest auf allen Seiten) ═══════════════════════ --}}
<div class="footer">
    <table class="footer-table">
        <tr>
            <td style="width:25%">
                <strong>Kolabri Getränke</strong><br>
                Benedikt Schneider<br>
                Odenwaldstr. 65<br>
                64372 Ober-Ramstadt
            </td>
            <td style="width:20%">
                <strong>Kontakt</strong><br>
                06151–9501441<br>
                0152-01932110<br>
                getraenke@kolabri.de
            </td>
            <td style="width:30%">
                <strong>Bankverbindung</strong><br>
                Benedikt Schneider<br>
                IBAN: DE98 1101 0101 5660 6254 01<br>
                BIC: SOBKDEB2XXX · solarisBank Gf (S)
            </td>
            <td style="width:25%">
                <strong>Rechtliches</strong><br>
                USt-IdNr.: DE 258 418 801<br>
                Amtsgericht Darmstadt
            </td>
        </tr>
    </table>
</div>

</body>
</html>
