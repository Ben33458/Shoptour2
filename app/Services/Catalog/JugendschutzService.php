<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Models\Orders\Order;

/**
 * Jugendschutz-Logik: Mindestalter für Produkte und Bestellungen.
 *
 * Klassifizierung nach §9 JuSchG (Jugendschutzgesetz):
 *   - 0  = keine Altersbeschränkung
 *   - 16 = Bier, Wein, Sekt, Mischgetränke mit Bier/Wein, Apfelwein, Glühwein
 *   - 18 = Spirituosen, Likör, Rum, Wodka, Schnaps und sonstige Branntweine
 *
 * Primäre Quelle: warengruppe_id (stabil, adminseitig gepflegt).
 * Fallback: alkoholgehalt_vol_percent wenn warengruppe_id nicht gesetzt oder unbekannt.
 *
 * Warengruppen-Zuordnung (Stand 2026-04-18):
 *   18+ IDs : 17 (Spirituosen), 18 (Likör), 19 (Rum), 20 (Wodka), 28 (Schnaps)
 *   16+ IDs : 2 (Bier), 3 (Bayrisch Hell), 4 (Kölsch & Alt), 5 (Weizenbier),
 *             6 (Bockbier), 7 (Märzen), 9 (Apfelwein), 10 (Faßbier & Apfelwein),
 *             11 (Pils), 12 (Export), 13 (Radler & Mischgetränke), 14 (Schwarzbier),
 *             16 (Wein & Sekt), 21 (Sonstige Biere), 23 (Bittergetränke),
 *             27 (Lager), 29 (Rotbier), 31 (Kellerbier/Kräusen), 32 (Craftbier),
 *             33 (Glühwein & Punsch), 34 (Leichtbier)
 *   Nicht altersrelevant: 1 (Limonade), 8 (Malztrunk), 15 (Saft & Nektar),
 *             22 (Alkoholfreies Bier), 24/25 (Mineralwasser), 26 (Wasser m. Geschm.),
 *             30 (Sonstiges)
 */
class JugendschutzService
{
    /** Warengruppen-IDs, die Spirituosen / Branntweine sind → 18+ */
    public const WG_IDS_18 = [17, 18, 19, 20, 28];

    /**
     * Warengruppen-IDs, die Bier / Wein / Sekt / Mischgetränke sind → 16+
     * Hinweis: Bittergetränke (23) sind hier eingeordnet, da Campari/Aperol
     * alkoholisch aber nicht destilliert sind. Kann bei Bedarf auf 18+ verschoben werden.
     */
    public const WG_IDS_16 = [2, 3, 4, 5, 6, 7, 9, 10, 11, 12, 13, 14, 16, 21, 23, 27, 29, 31, 32, 33, 34];

    /**
     * Mindestalter für ein einzelnes Produkt.
     * Gibt 0, 16 oder 18 zurück.
     */
    public static function productMinAge(Product $product): int
    {
        $wgId = $product->warengruppe_id;

        if ($wgId !== null) {
            if (in_array((int) $wgId, self::WG_IDS_18, true)) {
                return 18;
            }
            if (in_array((int) $wgId, self::WG_IDS_16, true)) {
                return 16;
            }
        }

        // Fallback: Alkoholgehalt auswerten wenn WG nicht klassifizierbar
        $alkohol = (float) ($product->alkoholgehalt_vol_percent ?? 0);
        if ($alkohol >= 15.0) {
            return 18;
        }
        if ($alkohol >= 1.2) {
            return 16;
        }

        return 0;
    }

    /**
     * Höchstes Mindestalter über eine Liste von Produkten.
     * Gibt 0, 16 oder 18 zurück.
     *
     * @param iterable<Product> $products
     */
    public static function productsMinAge(iterable $products): int
    {
        $max = 0;
        foreach ($products as $product) {
            $age = self::productMinAge($product);
            if ($age > $max) {
                $max = $age;
            }
            if ($max === 18) {
                break; // Höchstwert erreicht
            }
        }
        return $max;
    }

    /**
     * Mindestalter für einen Warenkorb (CartService::calculate()-Rückgabe).
     * $cartItems ist array<int, array{product: Product, qty: int}> (keyed by product_id).
     *
     * @param array<int, array{product: Product, qty: int}> $cartItems
     */
    public static function cartMinAge(array $cartItems): int
    {
        $max = 0;
        foreach ($cartItems as $line) {
            if (isset($line['product']) && $line['product'] instanceof Product) {
                $age = self::productMinAge($line['product']);
                if ($age > $max) {
                    $max = $age;
                }
                if ($max === 18) {
                    break;
                }
            }
        }
        return $max;
    }

    /**
     * Mindestalter für eine Bestellung (lädt items.product falls nötig).
     */
    public static function orderMinAge(Order $order): int
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->with('product')->get();
        $max   = 0;
        foreach ($items as $item) {
            if ($item->product) {
                $age = self::productMinAge($item->product);
                if ($age > $max) {
                    $max = $age;
                }
                if ($max === 18) {
                    break;
                }
            }
        }
        return $max;
    }

    /**
     * Hinweistext für Checkout / Warenkorb.
     * Gibt null zurück wenn keine Altersbeschränkung.
     */
    public static function checkoutWarning(int $minAge): ?string
    {
        return match ($minAge) {
            16 => 'Enthält altersbeschränkte Ware. Abgabe nur nach Altersprüfung ab 16 Jahren.',
            18 => 'Enthält altersbeschränkte Ware. Abgabe nur nach Altersprüfung ab 18 Jahren.',
            default => null,
        };
    }

    /**
     * Hinweistext für Lieferschein / Fahrer.
     * Gibt null zurück wenn keine Altersbeschränkung.
     */
    public static function deliveryNote(int $minAge): ?string
    {
        return match ($minAge) {
            16 => 'Altersprüfung bei Übergabe erforderlich (16+).',
            18 => 'Altersprüfung bei Übergabe erforderlich (18+).',
            default => null,
        };
    }
}
