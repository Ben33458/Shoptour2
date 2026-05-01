<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\Gebinde;
use App\Models\Catalog\PfandItem;
use App\Models\Catalog\PfandSet;
use App\Models\Catalog\PfandSetComponent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WawiImportPfandCommand extends Command
{
    protected $signature = 'wawi:import-pfand {--dry-run : Zeige was passieren würde ohne Änderungen zu speichern}';

    protected $description = 'Importiert Pfand-Daten aus WaWi (Attribut "Pfand" je Artikel) in pfand_items / pfand_sets / gebinde und verknüpft Produkte.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Keine Änderungen werden gespeichert.');
        }

        // ── Schritt 1: Distinct Pfand-Brutto-Beträge aus wawi_artikel_attribute ─
        // fWertDecimal enthält den Brutto-Pfandbetrag (z.B. 0.08 = 8 Cent brutto).
        // Pfand ist i.d.R. ohne USt., daher gilt: brutto = netto.
        $pfandValues = DB::table('wawi_artikel_attribute')
            ->where('cAttributName', 'Pfand')
            ->where('fWertDecimal', '>', 0)
            ->select('fWertDecimal')
            ->distinct()
            ->orderBy('fWertDecimal')
            ->get();

        if ($pfandValues->isEmpty()) {
            $this->warn('Kein Pfand-Attribut (cAttributName="Pfand") in wawi_artikel_attribute gefunden. Abbruch.');
            return self::FAILURE;
        }

        $this->info("Gefundene Pfand-Beträge (brutto, aus wawi_artikel_attribute): {$pfandValues->count()}");

        // Pfand*-Artikel aus wawi_artikel für die Bezeichnungs-Ermittlung laden.
        // Matching: entweder direkter Treffer auf fVKNetto (0%-USt.) oder über
        // fVKNetto * 1.19 ≈ brutto (falls WaWi 19%-USt. auf Pfand-Artikel hat).
        $pfandArtikelByNetto = DB::table('wawi_artikel')
            ->where('cName', 'like', 'Pfand%')
            ->whereNotNull('fVKNetto')
            ->where('fVKNetto', '>', 0)
            ->get(['cName', 'fVKNetto'])
            ->groupBy(fn ($a) => (string) round((float) $a->fVKNetto, 4));

        $createdItems   = 0;
        $createdSets    = 0;
        $createdGebinde = 0;
        $linkedProducts = 0;

        // Map: string(round(bruttoEur, 4)) → gebinde.id
        $gebindeByBetrag = [];

        foreach ($pfandValues as $row) {
            $bruttoEur   = (float) $row->fWertDecimal;
            $bruttoMilli = (int) round($bruttoEur * 1_000_000);
            $nettoMilli  = $bruttoMilli; // Pfand ist i.d.R. ohne USt.
            $preisKey    = (string) round($bruttoEur, 4);

            // Bezeichnung aus passendem Pfand*-Artikel ableiten:
            // 1. Direkter Treffer (fVKNetto = brutto, 0%-USt.)
            // 2. Näherung: brutto / 1.19 ≈ netto (19%-USt.)
            $nettoKeyDirekt = $preisKey;
            $nettoKeyVia19  = (string) round($bruttoEur / 1.19, 4);
            $artikelGruppe  = $pfandArtikelByNetto->get($nettoKeyDirekt)
                           ?? $pfandArtikelByNetto->get($nettoKeyVia19);

            $bezeichnung = $artikelGruppe
                ? $artikelGruppe->first()->cName
                : sprintf('Pfand %s €', number_format($bruttoEur, 2, ',', '.'));

            $this->line(sprintf('  %s EUR (brutto) → "%s"', $bruttoEur, $bezeichnung));

            if ($dryRun) {
                $gebindeByBetrag[$preisKey] = null;
                $createdItems++;
                $createdSets++;
                $createdGebinde++;
                continue;
            }

            // PfandItem anlegen oder aktualisieren
            $pfandItem = PfandItem::updateOrCreate(
                ['wert_brutto_milli' => $bruttoMilli],
                [
                    'bezeichnung'                        => $bezeichnung,
                    'pfand_typ'                          => 'Mehrweg',
                    'wert_netto_milli'                   => $nettoMilli,
                    'wiederverkaeufer_wert_brutto_milli' => $bruttoMilli,
                    'wiederverkaeufer_wert_netto_milli'  => $nettoMilli,
                    'active'                             => true,
                ]
            );
            if ($pfandItem->wasRecentlyCreated) {
                $createdItems++;
            }

            // PfandSet anlegen oder aktualisieren
            $pfandSet = PfandSet::firstOrCreate(
                ['name' => $bezeichnung],
                ['active' => true]
            );
            if ($pfandSet->wasRecentlyCreated) {
                $createdSets++;

                PfandSetComponent::create([
                    'pfand_set_id'       => $pfandSet->id,
                    'pfand_item_id'      => $pfandItem->id,
                    'child_pfand_set_id' => null,
                    'qty'                => 1,
                ]);
            }

            // Gebinde anlegen
            $gebinde = Gebinde::firstOrCreate(
                ['pfand_set_id' => $pfandSet->id],
                [
                    'name'         => $bezeichnung,
                    'gebinde_type' => null,
                    'material'     => null,
                    'active'       => true,
                ]
            );
            if ($gebinde->wasRecentlyCreated) {
                $createdGebinde++;
            }

            $gebindeByBetrag[$preisKey] = $gebinde->id;
        }

        // ── Schritt 2: Produkte verknüpfen ───────────────────────────────────
        if (! $dryRun) {
            $productsToLink = DB::table('products as p')
                ->join('wawi_artikel as wa', 'wa.kArtikel', '=', 'p.wawi_artikel_id')
                ->join('wawi_artikel_attribute as waa', function ($j) {
                    $j->on('waa.kArtikel', '=', 'wa.kArtikel')
                      ->where('waa.cAttributName', '=', 'Pfand');
                })
                ->whereNotNull('waa.fWertDecimal')
                ->where('waa.fWertDecimal', '>', 0)
                ->select('p.id', 'waa.fWertDecimal as pfand_betrag_brutto')
                ->get();

            foreach ($productsToLink as $row) {
                $preisKey  = (string) round((float) $row->pfand_betrag_brutto, 4);
                $gebindeId = $gebindeByBetrag[$preisKey] ?? null;
                if ($gebindeId === null) {
                    continue;
                }
                DB::table('products')
                    ->where('id', $row->id)
                    ->update(['gebinde_id' => $gebindeId]);
                $linkedProducts++;
            }
        } else {
            $count = DB::table('products as p')
                ->join('wawi_artikel as wa', 'wa.kArtikel', '=', 'p.wawi_artikel_id')
                ->join('wawi_artikel_attribute as waa', function ($j) {
                    $j->on('waa.kArtikel', '=', 'wa.kArtikel')
                      ->where('waa.cAttributName', '=', 'Pfand');
                })
                ->whereNotNull('waa.fWertDecimal')
                ->where('waa.fWertDecimal', '>', 0)
                ->count();
            $linkedProducts = $count;
        }

        // ── Zusammenfassung ───────────────────────────────────────────────────
        $this->info('');
        $this->info('Ergebnis:');
        $this->table(
            ['', 'Anzahl'],
            [
                ['PfandItems angelegt/aktualisiert', $createdItems],
                ['PfandSets angelegt',               $createdSets],
                ['Gebinde angelegt',                 $createdGebinde],
                ['Produkte verknüpft',               $linkedProducts],
            ]
        );

        return self::SUCCESS;
    }
}
