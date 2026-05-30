<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several case (Kasten) products were incorrectly linked to the single-bottle WaWi article
 * instead of the case article, causing them to show the per-bottle price and per-bottle
 * Pfand. This migration corrects wawi_artikel_id and gebinde_id for each affected product,
 * then recalculates base_price_net_milli / base_price_gross_milli from the case VK price.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wawi_artikel')) {
            return;
        }

        // [ artikelnummer => [ new_wawi_artikel_id | null, new_gebinde_id | null ] ]
        // null = leave unchanged
        $fixes = [
            '52806' => [4494,  55],  // Herrnbräu Kristallweizen 20x0,5 l  → 3.10€ Pfand
            '58342' => [15199, 47],  // Lammsbräu EdelHell 10x0,5 l        → 2.30€ Pfand
            '57077' => [13927, 58],  // Krämer Johannisbeer Schorle 24x0,33 → 3.42€ Pfand
            '24551' => [14894, null],// Bizzl Zitrusfrüchte 12x1,0 l       (pfand already correct)
            '58496' => [15354, 57],  // Rhön Sprudel Naturell 12x1 l        → 3.30€ Pfand
            '58401' => [15260, 49],  // Rapps Meisterschoppen 6x1,0 l       → 2.40€ Pfand
            '53015' => [null,  49],  // Stenger Apfelwein 6x1,0 l           → 2.40€ Pfand (no case wawi article)
            '57728' => [14582, 58],  // Trade Islands Peach Zero 24x0,33 l  → 3.42€ Pfand
            '57746' => [14598, null],// Bionade Eistee Zitrone 12x0,33 l    (pfand already correct)
            '57743' => [14595, null],// Bionade Eistee Pfirsich 12x0,33 l   (pfand already correct)
        ];

        // Use 19 % (standard rate) for beverages; rate_basis_points = 10_000 means 100 %
        $defaultTaxBp = DB::table('tax_rates')
            ->where('rate_basis_points', 1900)
            ->value('rate_basis_points') ?? 1900;

        foreach ($fixes as $artnr => [$newWawiId, $newGebindeId]) {
            $update = ['updated_at' => now()->toDateTimeString()];

            if ($newGebindeId !== null) {
                $update['gebinde_id'] = $newGebindeId;
            }

            if ($newWawiId !== null) {
                $update['wawi_artikel_id'] = $newWawiId;

                // Recalculate price from the case WaWi article
                $vkNetto = DB::table('wawi_artikel')
                    ->where('kArtikel', $newWawiId)
                    ->value('fVKNetto');

                if ($vkNetto !== null && (float) $vkNetto > 0) {
                    $netMilli   = (int) round((float) $vkNetto * 1_000_000);
                    $grossMilli = (int) intdiv($netMilli * (10_000 + (int) $defaultTaxBp) + 5_000, 10_000);

                    $update['base_price_net_milli']   = $netMilli;
                    $update['base_price_gross_milli'] = $grossMilli;
                }
            }

            DB::table('products')
                ->where('artikelnummer', (string) $artnr)
                ->update($update);
        }
    }

    public function down(): void {}
};
