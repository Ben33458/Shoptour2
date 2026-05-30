<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $subject }}</title>
    <style>
        /*
         * DIN 5008 Form A — Standard German business letter
         * A4 (210 × 297 mm), flow-based zones (no position:fixed for content areas).
         *
         * Zone 1:  0–45 mm    Briefkopf (Logo + Absender)
         * Zone 2: 45–90 mm    Anschriftfeld (links) + Infoblock (rechts)
         * Zone 3: 90 mm +     Betreff + Inhalt  (7 mm Puffer → Inhalt ab 97 mm)
         * Fußzeile: fixed, 22 mm von unten
         * Falzmarken: 105 mm und 210 mm von oben
         * Linker Rand Inhalt: 25 mm | Linker Rand Anschriftfeld: 20 mm | Rechts: 20 mm
         */

        @page {
            size: A4;
            margin: 0;
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
        }

        /* ── Falzmarken DIN 5008 ─────────────────────────────── */
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

        /* ── Zone 1: Briefkopf (0–45 mm) ────────────────────── */
        .zone-header {
            height: 45mm;
            padding: 7mm 20mm 0 25mm;
        }
        .header-cols {
            width: 100%;
            border-collapse: collapse;
        }
        .logo-td {
            width: 45%;
            vertical-align: top;
        }
        .logo-td img {
            max-height: 24mm;
            max-width: 100%;
        }
        .logo-text {
            font-size: 18pt;
            font-weight: bold;
            color: #15803d;
            letter-spacing: 1px;
        }
        .company-td {
            width: 55%;
            vertical-align: top;
            text-align: right;
            font-size: 7.5pt;
            color: #444;
            line-height: 1.5;
        }
        .company-td strong {
            font-size: 9pt;
            color: #1a1a1a;
        }

        /* ── Zone 2: Anschriftfeld + Infoblock (45–90 mm) ───── */
        .zone-address {
            height: 45mm;
        }
        .address-cols {
            width: 100%;
            height: 45mm;
            border-collapse: collapse;
        }
        .address-td {
            /* DIN 5008: Anschriftfeld 20 mm vom Rand, Breite 85 mm → Spalte = 105 mm */
            width: 105mm;
            padding-left: 20mm;
            vertical-align: top;
            font-size: 9pt;
            line-height: 1.7;
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
        .info-td {
            vertical-align: top;
            text-align: right;
            padding-right: 20mm;
            font-size: 8.5pt;
            color: #333;
            line-height: 1.7;
        }

        /* ── Zone 3: Inhalt (ab 90 mm, 7 mm Puffer → 97 mm) ── */
        .zone-content {
            padding: 7mm 20mm 28mm 25mm;
        }

        /* ── Betreff ─────────────────────────────────────────── */
        .subject {
            font-size: 11.5pt;
            font-weight: bold;
            margin-bottom: 5mm;
            padding-bottom: 2mm;
            border-bottom: 1.5pt solid #15803d;
            color: #1a1a1a;
        }

        /* ── Anschreiben ─────────────────────────────────────── */
        .body-text {
            font-size: 9pt;
            line-height: 1.6;
            margin-bottom: 6mm;
        }

        /* ── Rechnungsübersicht ──────────────────────────────── */
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

        /* ── Summen ──────────────────────────────────────────── */
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

        /* ── Zahlungsbox ─────────────────────────────────────── */
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

        /* ── Grußformel ──────────────────────────────────────── */
        .closing {
            font-size: 9pt;
            line-height: 1.6;
            margin-bottom: 8mm;
        }

        /* ── Fußzeile (fest, wiederholt auf jeder Seite) ─────── */
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

{{-- Fußzeile (fest, alle Seiten) --}}
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

{{-- Falzmarken DIN 5008 --}}
<div class="fold-mark fold-mark-1"></div>
<div class="fold-mark fold-mark-2"></div>

{{-- ══ Zone 1: Briefkopf (0–45 mm) ════════════════════════════ --}}
<div class="zone-header">
    <table class="header-cols">
        <tr>
            <td class="logo-td">
                @if($logoBase64)
                    <img src="data:image/png;base64,{{ $logoBase64 }}" alt="Kolabri Logo">
                @else
                    <div class="logo-text">Kolabri</div>
                @endif
            </td>
            <td class="company-td">
                <strong>Kolabri Getränke</strong><br>
                Benedikt Schneider<br>
                Odenwaldstr. 65 · 64372 Ober-Ramstadt<br>
                Tel.: 06151–9501441 · Mobil: 0152-01932110<br>
                getraenke@kolabri.de
            </td>
        </tr>
    </table>
</div>

{{-- ══ Zone 2: Anschriftfeld + Infoblock (45–90 mm) ══════════ --}}
<div class="zone-address">
    <table class="address-cols">
        <tr>
            <td class="address-td">
                <div class="sender-line">Kolabri Getränke · Odenwaldstr. 65 · 64372 Ober-Ramstadt</div>
                <div>
                    {{ $recipientName }}<br>
                    @if($recipientStreet){{ $recipientStreet }}<br>@endif
                    @if($recipientZipCity){{ $recipientZipCity }}<br>@endif
                </div>
            </td>
            <td class="info-td">
                Ober-Ramstadt, {{ $date }}<br>
                <br>
                Kunden-Nr.: {{ $customerNumber }}<br>
                @if($level >= 2)
                    <span style="color:#b91c1c;font-weight:bold">2. Mahnung</span>
                @else
                    Zahlungserinnerung
                @endif
            </td>
        </tr>
    </table>
</div>

{{-- ══ Zone 3: Inhalt (ab 97 mm) ══════════════════════════════ --}}
<div class="zone-content">

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

</div>{{-- end zone-content --}}

</body>
</html>
