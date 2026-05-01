<?php
declare(strict_types=1);
namespace App\Services\Rental;

use App\Models\Catalog\Product;
use App\Models\Pricing\Customer;
use App\Models\Rental\DeliveryReturn;
use App\Models\Rental\DeliveryReturnItem;
use Illuminate\Support\Facades\DB;

/**
 * Vollgut-Rückgaben im Fahrer-Tool.
 *
 * Artikel 58610 = Volle Kasten-Rückgabe (pro Kasten)
 * Artikel 58611 = Volle Fass-Rückgabe (pro Fass)
 *
 * Regeln:
 *   - Nur volle, ungeöffnete, wieder einlagerbare Ware
 *   - MHD ist Pflichtfeld
 *   - Kästen: -qty auf Originalartikel + qty Artikel 58610
 *   - Fässer: -qty auf Originalartikel + qty Artikel 58611
 *   - Fässer können NUR voll zurückgegeben werden; angebrochen = Leergut/Pfand
 */
class VollgutReturnService
{
    public const ARTICLE_KASTEN_RUECKGABE = '58610';
    public const ARTICLE_FASS_RUECKGABE   = '58611';

    /**
     * Verbucht Vollgut-Rückgabe von Kästen.
     *
     * @param array<array{article_id: int, quantity: int, best_before_date: string, notes?: string}> $items
     */
    public function returnKaesten(
        Customer $customer,
        array $items,
        ?int $orderId = null,
        ?int $driverUserId = null,
    ): DeliveryReturn {
        return DB::transaction(function () use ($customer, $items, $orderId, $driverUserId) {
            $feeArticle = Product::where('artikelnummer', self::ARTICLE_KASTEN_RUECKGABE)->first();

            $return = DeliveryReturn::create([
                'company_id'     => $customer->company_id,
                'order_id'       => $orderId,
                'customer_id'    => $customer->id,
                'driver_user_id' => $driverUserId,
                'returned_at'    => now(),
                'return_type'    => DeliveryReturn::TYPE_FULL_GOODS,
            ]);

            foreach ($items as $item) {
                if (empty($item['best_before_date'])) {
                    throw new \InvalidArgumentException('MHD ist Pflicht bei Vollgut-Rückgaben (Kästen)');
                }

                // Negative Menge auf Originalartikel
                DeliveryReturnItem::create([
                    'delivery_return_id'     => $return->id,
                    'article_id'             => $item['article_id'],
                    'quantity'               => -abs($item['quantity']),
                    'best_before_date'       => $item['best_before_date'],
                    'is_restockable'         => true,
                    'return_reason'          => 'vollgut_kasten',
                    'generated_fee_article_id' => $feeArticle?->id,
                    'generated_fee_quantity' => abs($item['quantity']),
                    'notes'                  => $item['notes'] ?? null,
                ]);
            }

            return $return;
        });
    }

    /**
     * Verbucht Vollgut-Rückgabe von Fässern.
     * Fässer können NUR voll zurückgegeben werden.
     *
     * @param array<array{article_id: int, quantity: int, best_before_date: string, is_full: bool, notes?: string}> $items
     */
    public function returnFaesser(
        Customer $customer,
        array $items,
        ?int $orderId = null,
        ?int $driverUserId = null,
    ): DeliveryReturn {
        return DB::transaction(function () use ($customer, $items, $orderId, $driverUserId) {
            $feeArticle = Product::where('artikelnummer', self::ARTICLE_FASS_RUECKGABE)->first();

            // Validate: all barrels must be full
            foreach ($items as $item) {
                if (empty($item['is_full']) || !$item['is_full']) {
                    throw new \InvalidArgumentException(
                        'Fässer können nur voll als Vollgut zurückgegeben werden. Angebrochen = Leergut/Pfand.'
                    );
                }
                if (empty($item['best_before_date'])) {
                    throw new \InvalidArgumentException('MHD ist Pflicht bei Vollgut-Rückgaben (Fässer)');
                }
            }

            $return = DeliveryReturn::create([
                'company_id'     => $customer->company_id,
                'order_id'       => $orderId,
                'customer_id'    => $customer->id,
                'driver_user_id' => $driverUserId,
                'returned_at'    => now(),
                'return_type'    => DeliveryReturn::TYPE_FULL_GOODS,
            ]);

            foreach ($items as $item) {
                DeliveryReturnItem::create([
                    'delivery_return_id'     => $return->id,
                    'article_id'             => $item['article_id'],
                    'quantity'               => -abs($item['quantity']),
                    'best_before_date'       => $item['best_before_date'],
                    'is_restockable'         => true,
                    'return_reason'          => 'vollgut_fass',
                    'generated_fee_article_id' => $feeArticle?->id,
                    'generated_fee_quantity' => abs($item['quantity']),
                    'notes'                  => $item['notes'] ?? null,
                ]);
            }

            return $return;
        });
    }
}
