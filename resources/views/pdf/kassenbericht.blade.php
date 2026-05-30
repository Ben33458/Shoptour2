<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Kassenbericht {{ $bericht['monat_label'] }}</title>
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

        .header {
            width: 100%;
            margin-bottom: 10mm;
        }
        .header-table {
            width: 100%;
        }
        .logo-cell {
            width: 40%;
            vertical-align: top;
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

        .report-title {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 6mm;
            color: #1a1a1a;
            border-bottom: 1pt solid #2563eb;
            padding-bottom: 2mm;
        }

        .section-label {
            font-size: 9pt;
            font-weight: bold;
            color: #374151;
            margin-bottom: 2mm;
            margin-top: 6mm;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4mm;
            font-size: 8.5pt;
        }
        .data-table thead tr {
            background-color: #f1f5f9;
        }
        .data-table th {
            padding: 3pt 6pt;
            text-align: left;
            font-weight: bold;
            font-size: 8pt;
            color: #444;
            border-bottom: 1pt solid #cbd5e1;
        }
        .data-table th.text-right,
        .data-table td.text-right {
            text-align: right;
        }
        .data-table td {
            padding: 3pt 6pt;
            border-bottom: 0.3pt solid #e2e8f0;
        }
        .data-table tfoot td {
            border-top: 1.5pt solid #1a1a1a;
            font-weight: bold;
            font-size: 9pt;
            padding-top: 3pt;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }

        .no-data {
            font-size: 9pt;
            color: #6b7280;
            font-style: italic;
            padding: 4mm 0;
        }

        .footer {
            font-size: 7.5pt;
            color: #777;
            border-top: 0.5pt solid #e2e8f0;
            padding-top: 3mm;
            margin-top: 10mm;
        }

        .notice {
            font-size: 7.5pt;
            color: #6b7280;
            background-color: #f9fafb;
            border: 0.3pt solid #e2e8f0;
            padding: 3pt 6pt;
            margin-top: 6mm;
        }
    </style>
</head>
<body>

{{-- Header --}}
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
                         alt="Logo" style="max-height:25mm;max-width:100%">
                @else
                    <div class="logo-placeholder">{{ $company?->name ?? 'Kolabri' }}</div>
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

{{-- Title --}}
<div class="report-title">Kassenbericht – {{ $bericht['monat_label'] }}</div>

@if(!$bericht['hat_daten'])
    <div class="no-data">Keine Kassendaten für diesen Monat vorhanden.</div>
@else

{{-- MwSt-Tabelle --}}
<div class="section-label">Umsatz nach Steuersatz</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Steuersatz</th>
            <th class="text-right">Bruttoumsatz</th>
            <th class="text-right">Nettoumsatz</th>
            <th class="text-right">MwSt-Betrag</th>
            <th class="text-right">Menge</th>
        </tr>
    </thead>
    <tbody>
        @foreach($bericht['zeilen'] as $zeile)
            <tr>
                <td>{{ $zeile['steuersatz'] }}</td>
                <td class="text-right">{{ number_format($zeile['brutto'], 2, ',', '.') }} &euro;</td>
                <td class="text-right">{{ number_format($zeile['netto'], 2, ',', '.') }} &euro;</td>
                <td class="text-right">{{ number_format($zeile['mwst'], 2, ',', '.') }} &euro;</td>
                <td class="text-right">{{ number_format($zeile['menge'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Summe (ohne Pfand)</td>
            <td class="text-right">{{ number_format($bericht['summe_brutto'], 2, ',', '.') }} &euro;</td>
            <td class="text-right">{{ number_format($bericht['summe_netto'], 2, ',', '.') }} &euro;</td>
            <td class="text-right">{{ number_format($bericht['summe_mwst'], 2, ',', '.') }} &euro;</td>
            <td class="text-right"></td>
        </tr>
    </tfoot>
</table>

{{-- Pfand / Leergut --}}
@if($bericht['pfand_brutto'] != 0 || $bericht['leergut_brutto'] != 0)
<div class="section-label">Pfand &amp; Leergut</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Position</th>
            <th class="text-right">Brutto</th>
            <th class="text-right">Netto</th>
        </tr>
    </thead>
    <tbody>
        @if($bericht['pfand_brutto'] != 0)
            <tr>
                <td>Pfand-Einnahmen</td>
                <td class="text-right">{{ number_format($bericht['pfand_brutto'], 2, ',', '.') }} &euro;</td>
                <td class="text-right">{{ number_format($bericht['pfand_netto'], 2, ',', '.') }} &euro;</td>
            </tr>
        @endif
        @if($bericht['leergut_brutto'] != 0)
            <tr>
                <td>Leergut-R&uuml;cknahmen</td>
                <td class="text-right">{{ number_format($bericht['leergut_brutto'], 2, ',', '.') }} &euro;</td>
                <td class="text-right">{{ number_format($bericht['leergut_netto'], 2, ',', '.') }} &euro;</td>
            </tr>
        @endif
    </tbody>
</table>
@endif


@endif

{{-- Kassenumsatz nach Zahlungsart --}}
@if($bericht['zahlungen']['hat_kassenumsatz'])
@php
    $kasseData   = $bericht['zahlungen']['kassenumsatz'];
    $kasseGesamt = $bericht['zahlungen']['kassenumsatz_gesamt'];
    $gruppenLabel = ['bar' => 'Bar (inkl. Barauszahlung)', 'ec' => 'EC-Zahlungen', 'sonstige' => 'Sonstige'];
    $allMwstKeys = array_unique(array_merge(...array_map('array_keys', array_values($kasseData) + [$kasseGesamt])));
    rsort($allMwstKeys);
@endphp
<div class="section-label">Kassenumsatz nach Zahlungsart</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Zahlungsart</th>
            @foreach($allMwstKeys as $mwstKey)
                <th class="text-right">{{ number_format($kasseGesamt[$mwstKey]['mwst_satz'] ?? (float)$mwstKey, 0) }}% Brutto</th>
            @endforeach
            <th class="text-right">Gesamt</th>
        </tr>
    </thead>
    <tbody>
        @foreach(['bar', 'ec', 'sonstige'] as $gruppe)
            @if(!empty($kasseData[$gruppe]))
            <tr>
                <td>{{ $gruppenLabel[$gruppe] }}</td>
                @foreach($allMwstKeys as $mwstKey)
                    <td class="text-right">
                        @if(isset($kasseData[$gruppe][$mwstKey]))
                            {{ number_format($kasseData[$gruppe][$mwstKey]['brutto'], 2, ',', '.') }} &euro;
                        @else
                            &ndash;
                        @endif
                    </td>
                @endforeach
                <td class="text-right">
                    {{ number_format(array_sum(array_column($kasseData[$gruppe], 'brutto')), 2, ',', '.') }} &euro;
                </td>
            </tr>
            @endif
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Gesamt</td>
            @foreach($allMwstKeys as $mwstKey)
                <td class="text-right">{{ number_format($kasseGesamt[$mwstKey]['brutto'] ?? 0, 2, ',', '.') }} &euro;</td>
            @endforeach
            <td class="text-right">{{ number_format(array_sum(array_column($kasseGesamt, 'brutto')), 2, ',', '.') }} &euro;</td>
        </tr>
    </tfoot>
</table>
@endif

{{-- Auftragszahlungen --}}
@if($bericht['zahlungen']['hat_auftragszahlung'])
@php $auftr = $bericht['zahlungen']['auftragszahlungen']; @endphp
<div class="section-label">Auftragszahlungen</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Zahlungsart</th>
            <th class="text-right">Brutto</th>
        </tr>
    </thead>
    <tbody>
        @if($auftr['bar'] != 0)
        <tr>
            <td>Bar (inkl. Barauszahlung)</td>
            <td class="text-right">{{ number_format($auftr['bar'], 2, ',', '.') }} &euro;</td>
        </tr>
        @endif
        @if($auftr['ec'] != 0)
        <tr>
            <td>EC-Zahlungen</td>
            <td class="text-right">{{ number_format($auftr['ec'], 2, ',', '.') }} &euro;</td>
        </tr>
        @endif
        @if($auftr['sonstige'] != 0)
        <tr>
            <td>Sonstige</td>
            <td class="text-right">{{ number_format($auftr['sonstige'], 2, ',', '.') }} &euro;</td>
        </tr>
        @endif
    </tbody>
    <tfoot>
        <tr>
            <td>Gesamt</td>
            <td class="text-right">{{ number_format($auftr['gesamt'], 2, ',', '.') }} &euro;</td>
        </tr>
    </tfoot>
</table>
<div class="notice">
    MwSt-Aufschlüsselung aus den Lieferauftrags-Rechnungen entnehmen &ndash;
    der POS erfasst Auftragszahlungen ohne MwSt-Satz.
</div>
@endif

{{-- Footer --}}
<div class="footer">
    Erstellt am {{ now()->format('d.m.Y') }} um {{ now()->format('H:i') }} Uhr
    &nbsp;&bull;&nbsp;
    Zeitraum: 01.{{ substr($bericht['monat'], 5, 2) }}.{{ substr($bericht['monat'], 0, 4) }}
    – {{ date('d', strtotime('last day of ' . $bericht['monat'])) }}.{{ substr($bericht['monat'], 5, 2) }}.{{ substr($bericht['monat'], 0, 4) }}
    &nbsp;&bull;&nbsp;
    Quelle: LS POS / JTL WaWi &middot; MwSt-S&auml;tze aus Kassenbonpositionen
</div>

</body>
</html>
