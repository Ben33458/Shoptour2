<?php

declare(strict_types=1);

namespace App\Services\Ninox;

use App\Models\Catalog\Product;
use App\Models\Employee\Employee;
use App\Models\Orders\Order;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\Customer;
use App\Models\Procurement\GoodsReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Push shoptour2 entities to Ninox.
 *
 * Each method:
 *  1. Maps local fields to Ninox field names
 *  2. Creates or updates the Ninox record
 *  3. Persists the Ninox ID back (if new) and sets ninox_pushed_at = now()
 *     via updateQuietly() to avoid triggering the observer again.
 */
class NinoxPushService
{
    private NinoxApiClient $client;

    public function __construct()
    {
        $this->client = NinoxApiClient::make();
    }

    // ── Customer ──────────────────────────────────────────────────────────────

    public function pushCustomer(Customer $customer): void
    {
        $tableId = config('services.ninox.tables.kunden');

        $fields = [
            'Kundennummer' => $customer->customer_number,
            'Vorname'      => $customer->first_name,
            'Nachname'     => $customer->last_name,
            'Firmenname'   => $customer->company_name,
            'E-Mail'       => $customer->email,
        ];

        if ($customer->ninox_kunden_id) {
            $this->client->updateRecord($tableId, (string) $customer->ninox_kunden_id, $fields);
        } else {
            $record = $this->client->createRecord($tableId, $fields);
            $ninoxId = $record['id'] ?? null;
            if ($ninoxId) {
                $customer->updateQuietly(['ninox_kunden_id' => $ninoxId]);
            }
        }

        $customer->updateQuietly(['ninox_pushed_at' => now()]);

        Log::info('Ninox: customer pushed', ['customer_id' => $customer->id, 'ninox_id' => $customer->ninox_kunden_id]);
    }

    // ── Employee ──────────────────────────────────────────────────────────────

    public function pushEmployee(Employee $employee): void
    {
        $tableId = config('services.ninox.tables.mitarbeiter');

        $fields = [
            'Vorname'  => $employee->first_name,
            'Nachname' => $employee->last_name,
        ];

        if ($employee->ninox_source_id) {
            $this->client->updateRecord($tableId, (string) $employee->ninox_source_id, $fields);
        } else {
            $record = $this->client->createRecord($tableId, $fields);
            $ninoxId = $record['id'] ?? null;
            if ($ninoxId) {
                $employee->updateQuietly(['ninox_source_id' => $ninoxId]);
            }
        }

        $employee->updateQuietly(['ninox_pushed_at' => now()]);

        Log::info('Ninox: employee pushed', ['employee_id' => $employee->id, 'ninox_id' => $employee->ninox_source_id]);
    }

    // ── Order ─────────────────────────────────────────────────────────────────

    public function pushOrder(Order $order): void
    {
        $tableId = config('services.ninox.tables.bestellannahme');

        $order->loadMissing('customer');
        $ninoxKundenId = $order->customer?->ninox_kunden_id;

        $fields = [
            'Bestelltext' => $order->order_number,
            'Preis'       => round($order->total_gross_milli / 1_000_000, 2),
        ];

        $ninoxStatus = $this->mapOrderStatusToNinox($order->status);
        if ($ninoxStatus !== null) {
            $fields['Status'] = $ninoxStatus;
        }

        if ($ninoxKundenId) {
            $fields['Kunden'] = (int) $ninoxKundenId;
        }

        if ($order->delivery_date) {
            $fields['Lieferdatum'] = $order->delivery_date->format('Y-m-d');
        }

        DB::transaction(function () use ($order, $tableId, $fields): void {
            $fresh = Order::where('id', $order->id)->lockForUpdate()->first();
            if (!$fresh) {
                return;
            }

            if ($fresh->ninox_id) {
                $this->client->updateRecord($tableId, $fresh->ninox_id, $fields);
                $fresh->updateQuietly(['ninox_pushed_at' => now()]);
            } else {
                $record  = $this->client->createRecord($tableId, $fields);
                $ninoxId = $record['id'] ?? null;
                if ($ninoxId) {
                    $fresh->updateQuietly(['ninox_id' => (string) $ninoxId, 'ninox_pushed_at' => now()]);
                }
            }
        });

        $order->refresh();
        Log::info('Ninox: order pushed', ['order_id' => $order->id, 'ninox_id' => $order->ninox_id]);
    }

    private function mapOrderStatusToNinox(string $status): ?string
    {
        $mapping = [
            Order::STATUS_DELIVERED  => AppSetting::get('ninox.status_delivered',  ''),
            Order::STATUS_CANCELLED  => AppSetting::get('ninox.status_cancelled',  ''),
            Order::STATUS_CONFIRMED  => AppSetting::get('ninox.status_confirmed',  ''),
        ];

        $value = $mapping[$status] ?? null;
        return ($value !== null && $value !== '') ? $value : null;
    }

    // ── Product ───────────────────────────────────────────────────────────────

    public function pushProduct(Product $product): void
    {
        $tableId = config('services.ninox.tables.marktbestand');

        $fields = [
            'Artnummer'   => $product->artikelnummer,
            'Artikelname' => $product->produktname,
        ];

        if ($product->ninox_artikel_id) {
            $this->client->updateRecord($tableId, (string) $product->ninox_artikel_id, $fields);
        } else {
            $record = $this->client->createRecord($tableId, $fields);
            $ninoxId = $record['id'] ?? null;
            if ($ninoxId) {
                $product->updateQuietly(['ninox_artikel_id' => $ninoxId]);
            }
        }

        $product->updateQuietly(['ninox_pushed_at' => now()]);

        Log::info('Ninox: product pushed', ['product_id' => $product->id, 'ninox_id' => $product->ninox_artikel_id]);
    }

    // ── GoodsReceipt (Anlieferung) ────────────────────────────────────────────

    public function pushGoodsReceipt(GoodsReceipt $gr): void
    {
        $tableId = config('services.ninox.tables.bestellung');
        if (!$tableId) {
            return;
        }

        $gr->loadMissing('supplier', 'purchaseOrder');

        $fields = [
            'anliefertag'    => $gr->arrived_at?->format('Y-m-d'),
            'lieferanten'    => (int) ($gr->supplier?->ninox_lieferanten_id ?? 0),
            'lieferschein'   => $gr->lieferschein_nr,
            'geliefert'      => $gr->status === GoodsReceipt::STATUS_GEBUCHT,
            'bestell_schein' => $gr->purchaseOrder?->po_number,
            'notiz'          => $gr->notiz,
        ];

        if ($gr->ninox_bestellung_id) {
            $this->client->updateRecord($tableId, $gr->ninox_bestellung_id, $fields);
        } else {
            $record  = $this->client->createRecord($tableId, $fields);
            $ninoxId = $record['id'] ?? null;
            if ($ninoxId) {
                $gr->updateQuietly(['ninox_bestellung_id' => (string) $ninoxId]);
            }
        }

        $gr->updateQuietly(['ninox_pushed_at' => now()]);

        Log::info('Ninox: goods_receipt pushed', ['gr_id' => $gr->id, 'ninox_id' => $gr->ninox_bestellung_id]);
    }
}
