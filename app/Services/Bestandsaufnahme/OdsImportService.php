<?php

declare(strict_types=1);

namespace App\Services\Bestandsaufnahme;

use App\Models\Bestandsaufnahme\ArtikelMindestbestand;
use App\Models\Bestandsaufnahme\ArtikelVerpackungseinheit;
use App\Models\Catalog\Product;
use App\Models\Import\ImportBestandsaufnahmeKonflikt;
use App\Models\Import\ImportBestandsaufnahmeLauf;
use App\Models\Import\ImportBestandsaufnahmeMapping;
use App\Models\Import\ImportBestandsaufnahmeRohzeile;
use App\Models\Supplier\Supplier;
use App\Models\Supplier\SupplierProduct;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OdsImportService
{
    /**
     * ODS-Datei einlesen und Rohimport durchführen.
     *
     * Gibt den angelegten Importlauf zurück.
     */
    public function importFile(UploadedFile $file, User $user): ImportBestandsaufnahmeLauf
    {
        $lauf = ImportBestandsaufnahmeLauf::create([
            'dateiname'      => $file->getClientOriginalName(),
            'status'         => 'verarbeitung',
            'importiert_von' => $user->id,
        ]);

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheets = $spreadsheet->getAllSheets();

            $lauf->update(['anzahl_blaetter' => count($sheets)]);

            $rohzeilenCount  = 0;
            $konflikteCount  = 0;

            foreach ($sheets as $sheet) {
                $blattName = $sheet->getTitle();
                $rows = $sheet->toArray(null, true, true, true);

                foreach ($rows as $rowIdx => $row) {
                    // Leere Zeilen überspringen
                    $values = array_filter(array_values($row), fn($v) => $v !== null && $v !== '');
                    if (empty($values)) {
                        continue;
                    }

                    $rohzeile = ImportBestandsaufnahmeRohzeile::create([
                        'importlauf_id'   => $lauf->id,
                        'tabellenblatt'   => $blattName,
                        'zeilennummer'    => $rowIdx,
                        'roh_payload_json' => $row,
                        'erkannt_status'  => 'neu',
                    ]);

                    $rohzeilenCount++;

                    // Automatisches Mapping anwenden wenn konfiguriert
                    $mapping = ImportBestandsaufnahmeMapping::where('tabellenblatt', $blattName)
                        ->where('aktiv', true)
                        ->first();

                    if ($mapping) {
                        $konflikte = $this->applyMapping($rohzeile, $mapping);
                        $konflikteCount += $konflikte;
                    } else {
                        $rohzeile->update([
                            'erkannt_status' => 'pruefbeduertig',
                            'mapping_hinweis' => 'Kein Mapping für Blatt "' . $blattName . '" konfiguriert.',
                        ]);
                    }
                }
            }

            $lauf->update([
                'status'           => 'abgeschlossen',
                'anzahl_rohzeilen' => $rohzeilenCount,
                'anzahl_konflikte' => $konflikteCount,
            ]);
        } catch (\Throwable $e) {
            $lauf->update([
                'status'    => 'fehler',
                'fehler_log' => $e->getMessage(),
            ]);
        }

        return $lauf->fresh();
    }

    /**
     * Mapping auf eine Rohzeile anwenden.
     * Gibt Anzahl gefundener Konflikte zurück.
     */
    private function applyMapping(ImportBestandsaufnahmeRohzeile $rohzeile, ImportBestandsaufnahmeMapping $mapping): int
    {
        $payload  = $rohzeile->roh_payload_json;
        $konflikte = 0;

        // Kolabri ArtNr. extrahieren
        $kolabriArtNr = null;
        if ($mapping->spalte_kolabri_artnr) {
            $kolabriArtNr = trim((string) ($payload[$mapping->spalte_kolabri_artnr] ?? ''));
        }

        // Produkt finden
        $product = null;
        if ($kolabriArtNr) {
            $product = Product::where('artikelnummer', $kolabriArtNr)->first();
        }

        // Wenn kein Treffer via ArtNr → Lieferanten-ArtNr versuchen
        if (! $product && $mapping->spalte_lieferanten_artnr && $mapping->lieferant_id) {
            $lieferantenArtNr = trim((string) ($payload[$mapping->spalte_lieferanten_artnr] ?? ''));
            if ($lieferantenArtNr) {
                $supplierProduct = SupplierProduct::where('supplier_id', $mapping->lieferant_id)
                    ->where('supplier_sku', $lieferantenArtNr)
                    ->first();
                $product = $supplierProduct?->product;
            }
        }

        if (! $product) {
            // Produktname versuchen (unsicherer Match)
            $produktname = $mapping->spalte_produktname
                ? trim((string) ($payload[$mapping->spalte_produktname] ?? ''))
                : null;

            if ($produktname) {
                $candidates = Product::where('produktname', 'like', '%' . $produktname . '%')->get();
                if ($candidates->count() === 1) {
                    $product = $candidates->first();
                } elseif ($candidates->count() > 1) {
                    $this->logKonflikt($rohzeile, null, 'mehrere_moegliche_matches', 'Produktname', $produktname, null, 'Mehrere Produkte gefunden: ' . $candidates->pluck('artikelnummer')->join(', '));
                    $konflikte++;
                }
            }

            if (! $product) {
                $this->logKonflikt($rohzeile, null, 'produkt_ohne_match', 'Kolabri ArtNr', $kolabriArtNr, null, 'Kein Produkt gefunden.');
                $rohzeile->update(['erkannt_status' => 'pruefbeduertig', 'mapping_hinweis' => 'Produkt nicht gefunden.']);
                return ++$konflikte;
            }
        }

        $rohzeile->update(['product_id' => $product->id]);

        // Mindestbestand verarbeiten
        if ($mapping->spalte_mindestbestand) {
            $odsWert = $payload[$mapping->spalte_mindestbestand] ?? null;
            if ($odsWert !== null && $odsWert !== '') {
                $this->processMindestbestand($rohzeile, $product, $mapping, (float) $odsWert, $konflikte);
            }
        }

        $rohzeile->update([
            'lieferant_id'   => $mapping->lieferant_id,
            'erkannt_status' => $konflikte > 0 ? 'konflikt' : 'gemappt',
        ]);

        return $konflikte;
    }

    private function processMindestbestand(
        ImportBestandsaufnahmeRohzeile $rohzeile,
        Product $product,
        ImportBestandsaufnahmeMapping $mapping,
        float $odsWertVpe,
        int &$konflikte,
    ): void {
        $lagerId = $mapping->lager_id_standard;
        if (! $lagerId) {
            return;
        }

        // VPE-Faktor ermitteln (erster zählbarer VPE des Produkts)
        $vpe = ArtikelVerpackungseinheit::where('product_id', $product->id)
            ->where('ist_zaehlbar', true)
            ->orderBy('sortierung')
            ->first();

        $faktor = $vpe?->faktor_basiseinheit ?? 1.0;
        $odsWertBasis = $odsWertVpe * $faktor;

        $existing = ArtikelMindestbestand::where('product_id', $product->id)
            ->where('warehouse_id', $lagerId)
            ->first();

        if ($existing) {
            // DB gewinnt — Konflikt protokollieren wenn abweichend
            if (abs($existing->mindestbestand_basiseinheit - $odsWertBasis) > 0.001) {
                $this->logKonflikt(
                    $rohzeile,
                    $product,
                    'abweichender_mindestbestand',
                    'mindestbestand_basiseinheit',
                    (string) $odsWertBasis,
                    (string) $existing->mindestbestand_basiseinheit,
                    'ODS-Wert weicht vom DB-Wert ab. DB-Wert bleibt erhalten.',
                );
                $existing->update([
                    'konflikt_flag'       => true,
                    'konflikt_details'    => array_merge($existing->konflikt_details ?? [], [
                        'ods_wert_vpe'   => $odsWertVpe,
                        'ods_wert_basis' => $odsWertBasis,
                        'quelle_blatt'   => $rohzeile->tabellenblatt,
                    ]),
                ]);
                $konflikte++;
            }
        } else {
            // Neu anlegen
            ArtikelMindestbestand::create([
                'product_id'                  => $product->id,
                'warehouse_id'                => $lagerId,
                'mindestbestand_basiseinheit' => $odsWertBasis,
                'quelle'                      => 'import',
                'quelle_datei'                => $rohzeile->importlauf->dateiname ?? null,
                'quelle_tabellenblatt'        => $rohzeile->tabellenblatt,
            ]);
        }
    }

    private function logKonflikt(
        ImportBestandsaufnahmeRohzeile $rohzeile,
        ?Product $product,
        string $typ,
        ?string $feld,
        ?string $odsWert,
        ?string $dbWert,
        ?string $hinweis,
    ): void {
        ImportBestandsaufnahmeKonflikt::create([
            'rohzeile_id'  => $rohzeile->id,
            'product_id'   => $product?->id,
            'konflikt_typ' => $typ,
            'feld'         => $feld,
            'ods_wert'     => $odsWert,
            'db_wert'      => $dbWert,
            'hinweis'      => $hinweis,
            'aktion'       => 'offen',
        ]);
    }
}
