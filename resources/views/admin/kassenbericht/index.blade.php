@extends('admin.layout')

@section('title', 'Kassenbericht – ' . $bericht['monat_label'])

@section('actions')
    <a href="{{ route('admin.kassenbericht.pdf', ['month' => $monat]) }}"
       class="btn btn-primary btn-sm">PDF herunterladen</a>
@endsection

@section('content')

{{-- Monatsauswahl --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.kassenbericht') }}"
              style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group" style="margin:0">
                <label class="form-label">Monat</label>
                <input type="month" name="month" value="{{ $monat }}"
                       max="{{ now()->format('Y-m') }}"
                       class="form-control" style="width:180px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Anzeigen</button>

            {{-- Quick navigation --}}
            @php
                $prevMonat = \Carbon\Carbon::parse($monat . '-01')->subMonth()->format('Y-m');
                $nextMonat = \Carbon\Carbon::parse($monat . '-01')->addMonth()->format('Y-m');
            @endphp
            <a href="{{ route('admin.kassenbericht', ['month' => $prevMonat]) }}"
               class="btn btn-outline btn-sm">← Vormonat</a>
            @if($nextMonat <= now()->format('Y-m'))
                <a href="{{ route('admin.kassenbericht', ['month' => $nextMonat]) }}"
                   class="btn btn-outline btn-sm">Nächster Monat →</a>
            @endif
        </form>
    </div>
</div>

{{-- Datenlücken-Warnung --}}
@if(!$bericht['daten_vollstaendig'])
<div class="alert alert-warning" style="margin-bottom:16px">
    <strong>Datenlücke erkannt:</strong>
    Nur {{ number_format($bericht['bon_count'], 0, ',', '.') }} Bons in der Datenbank für diesen Monat —
    historische POS-Daten wurden vermutlich nicht vollständig synchronisiert.
    Bitte in JTL WAWI einen <strong>Full-Resync</strong> (POS_Bon, POS_Umsaetze, POS_BonPosition)
    für <strong>{{ $bericht['monat_label'] }}</strong> anstoßen (erst nach 23:00 Uhr)
    und danach <code>php artisan stats:refresh-pos</code> ausführen.
</div>
@endif

{{-- Kein Daten-Hinweis --}}
@if(!$bericht['hat_daten'])
    <div class="alert alert-info">
        Für <strong>{{ $bericht['monat_label'] }}</strong> sind keine Kassendaten vorhanden.
        Prüfe ob <code>stats_pos_daily</code> aktuell ist:
        <code>php artisan stats:refresh-pos</code>
    </div>
@else

{{-- MwSt-Tabelle --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <strong>Umsatz nach Steuersatz – {{ $bericht['monat_label'] }}</strong>
    </div>
    <div class="card-body" style="padding:0">
        <table class="table table-sm" style="margin:0">
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
                        <td class="text-right">{{ number_format($zeile['brutto'], 2, ',', '.') }} €</td>
                        <td class="text-right">{{ number_format($zeile['netto'], 2, ',', '.') }} €</td>
                        <td class="text-right">{{ number_format($zeile['mwst'], 2, ',', '.') }} €</td>
                        <td class="text-right">{{ number_format($zeile['menge'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;border-top:2px solid #dee2e6">
                    <td>Summe (ohne Pfand)</td>
                    <td class="text-right">{{ number_format($bericht['summe_brutto'], 2, ',', '.') }} €</td>
                    <td class="text-right">{{ number_format($bericht['summe_netto'], 2, ',', '.') }} €</td>
                    <td class="text-right">{{ number_format($bericht['summe_mwst'], 2, ',', '.') }} €</td>
                    <td class="text-right"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Pfand / Leergut --}}
@if($bericht['pfand_brutto'] != 0 || $bericht['leergut_brutto'] != 0)
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <strong>Pfand &amp; Leergut</strong>
    </div>
    <div class="card-body" style="padding:0">
        <table class="table table-sm" style="margin:0">
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
                        <td class="text-right">{{ number_format($bericht['pfand_brutto'], 2, ',', '.') }} €</td>
                        <td class="text-right">{{ number_format($bericht['pfand_netto'], 2, ',', '.') }} €</td>
                    </tr>
                @endif
                @if($bericht['leergut_brutto'] != 0)
                    <tr>
                        <td>Leergut-Rücknahmen</td>
                        <td class="text-right">{{ number_format($bericht['leergut_brutto'], 2, ',', '.') }} €</td>
                        <td class="text-right">{{ number_format($bericht['leergut_netto'], 2, ',', '.') }} €</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="alert alert-info" style="font-size:0.85em">
    MwSt-Sätze werden direkt aus den JTL WaWi Kassenbonpositionen gelesen (<code>tArtikel_fMwSt</code>).
    Die Daten stammen aus dem letzten Lauf von <code>php artisan stats:refresh-pos</code>.
</div>

@endif

{{-- Zahlungsart-Aufschlüsselung (aus wawi_dbo_pos_umsaetze, unabhängig von hat_daten) --}}
@if($bericht['zahlungen']['hat_kassenumsatz'])
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <strong>Kassenumsatz nach Zahlungsart – {{ $bericht['monat_label'] }}</strong>
    </div>
    <div class="card-body" style="padding:0">
        @php
            $kasseData = $bericht['zahlungen']['kassenumsatz'];
            $kasseGesamt = $bericht['zahlungen']['kassenumsatz_gesamt'];
            $gruppenLabel = ['bar' => 'Bar (inkl. Barauszahlung)', 'ec' => 'EC-Zahlungen', 'sonstige' => 'Sonstige'];
            // Collect all mwst keys present
            $allMwstKeys = array_unique(array_merge(...array_map('array_keys', array_values($kasseData) + [$kasseGesamt])));
            rsort($allMwstKeys);
        @endphp
        <table class="table table-sm" style="margin:0">
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
                                    {{ number_format($kasseData[$gruppe][$mwstKey]['brutto'], 2, ',', '.') }} €
                                @else
                                    –
                                @endif
                            </td>
                        @endforeach
                        <td class="text-right">
                            {{ number_format(array_sum(array_column($kasseData[$gruppe], 'brutto')), 2, ',', '.') }} €
                        </td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;border-top:2px solid #dee2e6">
                    <td>Gesamt</td>
                    @foreach($allMwstKeys as $mwstKey)
                        <td class="text-right">
                            {{ number_format($kasseGesamt[$mwstKey]['brutto'] ?? 0, 2, ',', '.') }} €
                        </td>
                    @endforeach
                    <td class="text-right">
                        {{ number_format(array_sum(array_column($kasseGesamt, 'brutto')), 2, ',', '.') }} €
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

@if($bericht['zahlungen']['hat_auftragszahlung'])
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <strong>Auftragszahlungen – {{ $bericht['monat_label'] }}</strong>
        <span style="font-weight:normal;font-size:0.85em;margin-left:8px;color:#6b7280">
            Kunden bezahlen Heimdienst-Aufträge an der Kasse
        </span>
    </div>
    <div class="card-body" style="padding:0">
        @php $auftr = $bericht['zahlungen']['auftragszahlungen']; @endphp
        <table class="table table-sm" style="margin:0">
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
                    <td class="text-right">{{ number_format($auftr['bar'], 2, ',', '.') }} €</td>
                </tr>
                @endif
                @if($auftr['ec'] != 0)
                <tr>
                    <td>EC-Zahlungen</td>
                    <td class="text-right">{{ number_format($auftr['ec'], 2, ',', '.') }} €</td>
                </tr>
                @endif
                @if($auftr['sonstige'] != 0)
                <tr>
                    <td>Sonstige</td>
                    <td class="text-right">{{ number_format($auftr['sonstige'], 2, ',', '.') }} €</td>
                </tr>
                @endif
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;border-top:2px solid #dee2e6">
                    <td>Gesamt</td>
                    <td class="text-right">{{ number_format($auftr['gesamt'], 2, ',', '.') }} €</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="card-body" style="padding:8px 16px;background:#f8f9fa;font-size:0.82em;color:#6b7280;border-top:1px solid #e9ecef">
        MwSt-Aufschlüsselung der Auftragszahlungen aus den Lieferauftrags-Rechnungen entnehmen –
        der POS erfasst Auftragszahlungen ohne MwSt-Satz.
    </div>
</div>
@endif

@endsection
