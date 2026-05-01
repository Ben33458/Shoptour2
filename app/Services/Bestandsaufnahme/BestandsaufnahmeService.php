<?php

declare(strict_types=1);

namespace App\Services\Bestandsaufnahme;

use App\Models\Bestandsaufnahme\BestandsaufnahmePosition;
use App\Models\Bestandsaufnahme\BestandsaufnahmePositionEingabe;
use App\Models\Bestandsaufnahme\BestandsaufnahmeSession;
use App\Models\Catalog\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class BestandsaufnahmeService
{
    public function __construct(
        private readonly MhdRegelService $mhdRegelService,
    ) {}

    /**
     * Neue Session anlegen oder offene Session zurückgeben.
     */
    public function startOrResume(Warehouse $warehouse, User $user, ?string $titel = null): BestandsaufnahmeSession
    {
        $open = BestandsaufnahmeSession::where('warehouse_id', $warehouse->id)
            ->whereIn('status', ['offen', 'pausiert'])
            ->latest()
            ->first();

        if ($open) {
            if ($open->status === BestandsaufnahmeSession::STATUS_PAUSIERT) {
                $open->update(['status' => BestandsaufnahmeSession::STATUS_OFFEN]);
            }
            return $open;
        }

        return BestandsaufnahmeSession::create([
            'warehouse_id' => $warehouse->id,
            'titel'        => $titel,
            'status'       => BestandsaufnahmeSession::STATUS_OFFEN,
            'gestartet_von' => $user->id,
            'gestartet_am' => now(),
        ]);
    }

    /**
     * Speichert eine Zählposition und bucht den Bestand sofort.
     *
     * $eingaben = [
     *   ['verpackungseinheit_id' => 1, 'menge_vpe' => 3, 'faktor_basiseinheit' => 24.0],
     *   ['verpackungseinheit_id' => null, 'menge_vpe' => 5, 'faktor_basiseinheit' => 1.0],
     * ]
     */
    public function savePosition(
        BestandsaufnahmeSession $session,
        Product $product,
        Warehouse $warehouse,
        array $eingaben,
        string $korrekturgrund,
        ?string $kommentar,
        User $user,
    ): BestandsaufnahmePosition {
        return DB::transaction(function () use ($session, $product, $warehouse, $eingaben, $korrekturgrund, $kommentar, $user) {
            // Aktuellen Bestand als Snapshot holen
            $stock = ProductStock::firstOrNew(
                ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                ['quantity' => 0, 'reserved_quantity' => 0],
            );

            $vorherBestand = (float) $stock->quantity;

            // VPE-Eingaben summieren
            $gezaehltGesamt = 0.0;
            foreach ($eingaben as $e) {
                $gezaehltGesamt += (float) $e['menge_vpe'] * (float) $e['faktor_basiseinheit'];
            }

            $differenz = $gezaehltGesamt - $vorherBestand;

            // MHD-Modus ermitteln (snapshot)
            $mhdModus = $this->mhdRegelService->resolveModusForProduct($product, $warehouse);

            // Position anlegen oder aktualisieren (letzte Buchung gewinnt bei Parallelzählung)
            $position = BestandsaufnahmePosition::updateOrCreate(
                [
                    'session_id'  => $session->id,
                    'product_id'  => $product->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'letzter_bestand_basiseinheit'    => $vorherBestand,
                    'gezaehlter_bestand_basiseinheit' => $gezaehltGesamt,
                    'differenz_basiseinheit'           => $differenz,
                    'mhd_modus'                        => $mhdModus,
                    'gezaehlt_von'                     => $user->id,
                    'gezaehlt_am'                      => now(),
                    'korrekturgrund'                   => $korrekturgrund,
                    'kommentar'                        => $kommentar,
                ],
            );

            // Eingabe-Detailzeilen ersetzen
            $position->eingaben()->delete();
            foreach ($eingaben as $e) {
                BestandsaufnahmePositionEingabe::create([
                    'position_id'          => $position->id,
                    'verpackungseinheit_id' => $e['verpackungseinheit_id'] ?? null,
                    'menge_vpe'            => (float) $e['menge_vpe'],
                    'faktor_basiseinheit'  => (float) $e['faktor_basiseinheit'],
                    'menge_basiseinheit'   => (float) $e['menge_vpe'] * (float) $e['faktor_basiseinheit'],
                ]);
            }

            // Bestand sofort buchen
            $stock->quantity = $gezaehltGesamt;
            $stock->save();

            // Journal-Eintrag
            StockMovement::create([
                'product_id'                   => $product->id,
                'warehouse_id'                 => $warehouse->id,
                'movement_type'                => StockMovement::TYPE_CORRECTION,
                'quantity_delta'               => $differenz,
                'reference_type'               => BestandsaufnahmeSession::class,
                'reference_id'                 => $session->id,
                'note'                         => $kommentar,
                'korrekturgrund'               => $korrekturgrund,
                'bestandsaufnahme_session_id'  => $session->id,
                'created_by_user_id'           => $user->id,
            ]);

            return $position->fresh();
        });
    }

    public function pauseSession(BestandsaufnahmeSession $session): void
    {
        $session->update(['status' => BestandsaufnahmeSession::STATUS_PAUSIERT]);
    }

    public function closeSession(BestandsaufnahmeSession $session): void
    {
        $session->update([
            'status'           => BestandsaufnahmeSession::STATUS_ABGESCHLOSSEN,
            'abgeschlossen_am' => now(),
        ]);
    }
}
