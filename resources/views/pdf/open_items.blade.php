<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Offene-Posten-Übersicht {{ $customerNumber }}</title>
    <style>
        /*
         * DIN 5008 Form A
         * Alle Maße ab Papierkante (oben-links = 0/0).
         *
         * Zone 1:  0 – 45 mm   → Briefkopf (Logo + Absender)
         * Zone 2: 45 – 90 mm   → Anschriftfeld (links) + Infoblock (rechts)
         * Zone 3: 90 mm +      → Betreff + Inhalt
         * Fußzeile: fix, 22 mm von unten
         * Falzmarken: 105 mm und 210 mm von oben
         *
         * Linker Rand Inhalt: 25 mm
         * Linker Rand Anschriftfeld: 20 mm  (5 mm Einzug aus Rand)
         * Rechter Rand: 20 mm
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

        /* ── Fußzeile (fest, wiederholt auf jeder Seite) ────────── */
        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 22mm;
            border-top: 0.5pt solid #d1d5db;
            padding: 3mm 20mm 0 25mm;
            font-size: 6.5pt;
            color: #888;
            line-height: 1.4;
        }
        .footer-cols {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-cols td {
            vertical-align: top;
            padding-right: 5mm;
        }
        .footer-cols td:last-child { padding-right: 0; }

        /* ── Falzmarken DIN 5008 ─────────────────────────────────── */
        .fold-mark {
            position: fixed;
            left: 5mm;
            width: 8mm;
            height: 0.3pt;
            background: #bbb;
        }
        .fold-mark-1 { top: 105mm; }
        .fold-mark-2 { top: 210mm; }

        /* ── Zone 1: Briefkopf (0–45 mm) ────────────────────────── */
        .zone-header {
            height: 45mm;
            padding: 7mm 20mm 0 25mm;
        }
        .header-cols {
            width: 100%;
            border-collapse: collapse;
        }
        .logo-td {
            width: 50%;
            vertical-align: top;
        }
        .logo-td img {
            max-height: 25mm;
            max-width: 100%;
        }
        .logo-text {
            font-size: 18pt;
            font-weight: bold;
            color: #15803d;
            letter-spacing: 1px;
        }
        .company-td {
            width: 50%;
            vertical-align: top;
            text-align: right;
            font-size: 7.5pt;
            color: #555;
            line-height: 1.55;
        }
        .company-td strong {
            font-size: 9pt;
            color: #1a1a1a;
        }

        /* ── Zone 2: Anschriftfeld + Infoblock (45–90 mm) ───────── */
        .zone-address {
            height: 45mm;
        }
        .address-cols {
            width: 100%;
            height: 45mm;
            border-collapse: collapse;
        }
        .address-td {
            /* DIN 5008: Anschriftfeld 20 mm vom Rand, Breite 85 mm */
            width: 105mm;
            padding-left: 20mm;
            vertical-align: top;
            font-size: 9pt;
            line-height: 1.7;
        }
        .sender-line {
            font-size: 6.5pt;
            color: #888;
            border-bottom: 0.3pt solid #ccc;
            padding-bottom: 1mm;
            margin-bottom: 2mm;
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

        /* ── Zone 3: Inhalt (ab 90 mm) ───────────────────────────── */
        .zone-content {
            padding: 7mm 20mm 28mm 25mm;
        }

        /* Betreff */
        .subject {
            font-size: 11.5pt;
            font-weight: bold;
            margin-bottom: 4mm;
            padding-bottom: 2mm;
            border-bottom: 1.5pt solid #15803d;
        }

        /* Hinweisbox */
        .notice-box {
            border: 0.5pt solid #cbd5e1;
            border-radius: 2pt;
            padding: 2.5mm 4mm;
            margin-bottom: 5mm;
            font-size: 8pt;
            color: #555;
            background-color: #f8fafc;
        }

        /* Positionstabelle */
        .items-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 4mm;
        }
        .items-table thead tr { background-color: #f1f5f9; }
        .items-table th {
            padding: 3pt 4pt;
            text-align: left;
            font-weight: bold;
            font-size: 7.5pt;
            color: #444;
            border-bottom: 1pt solid #cbd5e1;
            overflow: hidden;
        }
        .items-table th.r,
        .items-table td.r { text-align: right; }
        .items-table td { white-space: nowrap; overflow: hidden; }
        .items-table td {
            padding: 3pt 4pt;
            border-bottom: 0.3pt solid #e2e8f0;
            vertical-align: middle;
        }
        .items-table tbody tr:last-child td { border-bottom: none; }
        .row-overdue td { background-color: #fef2f2; }

        .badge {
            font-size: 7pt;
            padding: 1pt 4pt;
            border-radius: 2pt;
            font-weight: bold;
        }
        .badge-overdue { background-color: #fee2e2; color: #b91c1c; }
        .badge-open    { background-color: #fef3c7; color: #92400e; }

        /* Summentabelle */
        .totals-table {
            width: 55%;
            margin-left: 45%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        .totals-table td { padding: 2pt 5pt; }
        .totals-table .lbl { text-align: right; color: #555; width: 60%; }
        .totals-table .val { text-align: right; width: 40%; white-space: nowrap; }
        .totals-table tr.grand td {
            border-top: 1.5pt solid #1a1a1a;
            font-size: 10pt;
            font-weight: bold;
            padding-top: 3pt;
        }
    </style>
</head>
<body>

{{-- Fußzeile (fest, alle Seiten) --}}
<div class="page-footer">
    <table class="footer-cols">
        <tr>
            <td style="width:28%">
                <strong>Kolabri Getränke</strong><br>
                Benedikt Schneider<br>
                Odenwaldstr. 65 · 64372 Ober-Ramstadt
            </td>
            <td style="width:22%">
                <strong>Kontakt</strong><br>
                Tel. 06151–9501441<br>
                Mobil 0152-01932110<br>
                getraenke@kolabri.de
            </td>
            <td style="width:30%">
                <strong>Bankverbindung</strong><br>
                Benedikt Schneider<br>
                IBAN: DE98 1101 0101 5660 6254 01<br>
                BIC: SOBKDEB2XXX · solarisBank
            </td>
            <td style="width:20%">
                <strong>Rechtliches</strong><br>
                USt-IdNr.: DE 258 418 801<br>
                Amtsgericht Darmstadt
            </td>
        </tr>
    </table>
</div>

{{-- Falzmarken --}}
<div class="fold-mark fold-mark-1"></div>
<div class="fold-mark fold-mark-2"></div>

{{-- ══ Zone 1: Briefkopf (0–45 mm) ══════════════════════════ --}}
<div class="zone-header">
    <table class="header-cols">
        <tr>
            <td class="logo-td">
                @if($logoBase64)
                    <img src="data:image/png;base64,{{ $logoBase64 }}" alt="Logo">
                @else
                    <div class="logo-text">Kolabri</div>
                @endif
            </td>
            <td class="company-td">
                <strong>Kolabri Getränke</strong><br>
                Benedikt Schneider<br>
                Odenwaldstr. 65 · 64372 Ober-Ramstadt<br>
                Tel. 06151–9501441 · Mobil 0152-01932110<br>
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
                <div class="sender-line">Kolabri Getränke &middot; Odenwaldstr. 65 &middot; 64372 Ober-Ramstadt</div>
                {{ $recipientName }}<br>
                @if($recipientStreet){{ $recipientStreet }}<br>@endif
                @if($recipientZipCity){{ $recipientZipCity }}@endif
            </td>
            <td class="info-td">
                Ober-Ramstadt, {{ $date }}<br>
                <br>
                Kunden-Nr.: {{ $customerNumber }}
            </td>
        </tr>
    </table>
</div>

{{-- ══ Zone 3: Inhalt (ab 90 mm) ════════════════════════════ --}}
<div class="zone-content">

    <div class="subject">Offene-Posten-Übersicht</div>

    <div class="notice-box">
        <strong>Hinweis:</strong> Dies ist kein Mahnschreiben — diese Übersicht dient ausschließlich Ihrer Information.
        Bitte überweisen Sie offene Beträge bis zu den jeweiligen Fälligkeitsdaten.
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width:15%">Belegnummer</th>
                <th style="width:13%">Rechnungs­datum</th>
                <th style="width:12%">Fällig am</th>
                <th class="r" style="width:10%">Überfällig</th>
                <th class="r" style="width:14%">Rechnungs­betrag</th>
                <th class="r" style="width:13%">Offener Betrag</th>
                <th style="width:10%">Mahnstufe</th>
                <th style="width:13%">Status</th>
            </tr>
        </thead>
        <tbody>
            @php $totalOpen = 0; $totalGross = 0; @endphp
            @foreach($vouchers as $v)
            @php
                $daysOverdue = $v->daysOverdue();
                $totalOpen  += $v->signedOpen();
                $totalGross += $v->signedTotal();
            @endphp
            <tr @if($daysOverdue > 0) class="row-overdue" @endif>
                <td>{{ $v->voucher_number ?? '—' }}</td>
                <td>{{ $v->voucher_date?->format('d.m.Y') ?? '—' }}</td>
                <td>{{ $v->due_date?->format('d.m.Y') ?? '—' }}</td>
                <td class="r">
                    @if($daysOverdue > 0)
                        <span style="color:#b91c1c">{{ $daysOverdue }}&nbsp;{{ $daysOverdue === 1 ? 'Tag' : 'Tage' }}</span>
                    @else
                        —
                    @endif
                </td>
                <td class="r">{{ number_format($v->signedTotal() / 1_000_000, 2, ',', '.') }}&nbsp;€</td>
                <td class="r" style="font-weight:bold">{{ number_format($v->signedOpen() / 1_000_000, 2, ',', '.') }}&nbsp;€</td>
                <td>{{ $v->dunning_level > 0 ? 'Stufe '.$v->dunning_level : '—' }}</td>
                <td>
                    @if($daysOverdue > 0)
                        <span class="badge badge-overdue">Überfällig</span>
                    @else
                        <span class="badge badge-open">Offen</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td class="lbl">Rechnungsbetrag gesamt</td>
            <td class="val">{{ number_format($totalGross / 1_000_000, 2, ',', '.') }}&nbsp;€</td>
        </tr>
        <tr class="grand">
            <td class="lbl">Offener Betrag gesamt</td>
            <td class="val">{{ number_format($totalOpen / 1_000_000, 2, ',', '.') }}&nbsp;€</td>
        </tr>
    </table>

</div>

</body>
</html>
