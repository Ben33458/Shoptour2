<?php

declare(strict_types=1);

namespace App\Services\Ninox;

use App\Models\Admin\DeferredTask;
use App\Models\Delivery\Tour;
use App\Models\Delivery\TourStop;
use App\Models\Employee\Employee;
use App\Models\Orders\Order;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\Customer;
use App\Services\Orders\OrderNumberService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pull changes from Ninox into shoptour2 (2-hour polling).
 *
 * Conflict resolution: shoptour2 always wins.
 *  - If Ninox modifiedAt ≤ ninox_pushed_at + 60s → our own push, skip.
 *  - If both sides changed since last pull → push shoptour2 to Ninox, skip import.
 *  - If only Ninox changed → import into shoptour2 (updateQuietly to avoid loop).
 */
class NinoxPullService
{
    private const LAST_PULL_KEY = 'ninox.last_pull_at';

    private NinoxApiClient $client;
    private Carbon $lastPullAt;

    public function __construct()
    {
        $this->client = NinoxApiClient::make();

        $lastPullRaw = AppSetting::get(self::LAST_PULL_KEY);
        $this->lastPullAt = $lastPullRaw
            ? Carbon::parse($lastPullRaw)
            : Carbon::now()->subYears(10);
    }

    /**
     * Process pending ninox.push_* deferred tasks (shoptour2 → Ninox only).
     *
     * Does NOT import anything FROM Ninox. The pull methods (pullCustomers,
     * pullTours, etc.) exist for manual/admin use but are intentionally
     * excluded from the scheduled run to prevent loop-back writes.
     */
    public function pullAll(): array
    {
        $tasks = \App\Models\Admin\DeferredTask::pending()
            ->where('type', 'LIKE', 'ninox.%')
            ->oldest()
            ->get();

        $done   = 0;
        $failed = 0;

        foreach ($tasks as $task) {
            $task->update(['status' => \App\Models\Admin\DeferredTask::STATUS_RUNNING, 'attempts' => $task->attempts + 1]);

            try {
                $this->dispatchPushTask($task);
                $task->update(['status' => \App\Models\Admin\DeferredTask::STATUS_DONE]);
                $done++;
            } catch (\Throwable $e) {
                $status = $task->attempts >= $task->max_attempts
                    ? \App\Models\Admin\DeferredTask::STATUS_FAILED
                    : \App\Models\Admin\DeferredTask::STATUS_PENDING;

                $task->update(['status' => $status, 'last_error' => $e->getMessage()]);
                $failed++;
                Log::warning('Ninox push task failed', ['task_id' => $task->id, 'type' => $task->type, 'error' => $e->getMessage()]);
            }
        }

        $stats = ['push_done' => $done, 'push_failed' => $failed];
        Log::info('Ninox pull completed', $stats);

        return $stats;
    }

    private function dispatchPushTask(\App\Models\Admin\DeferredTask $task): void
    {
        $payload = $task->getPayload();
        $push    = app(\App\Services\Ninox\NinoxPushService::class);

        match ($task->type) {
            'ninox.push_customer'      => $push->pushCustomer(\App\Models\Pricing\Customer::findOrFail((int) ($payload['customer_id'] ?? 0))),
            'ninox.push_employee'      => $push->pushEmployee(\App\Models\Employee\Employee::findOrFail((int) ($payload['employee_id'] ?? 0))),
            'ninox.push_order'         => $push->pushOrder(\App\Models\Orders\Order::findOrFail((int) ($payload['order_id'] ?? 0))),
            'ninox.push_product'       => $push->pushProduct(\App\Models\Catalog\Product::findOrFail((int) ($payload['product_id'] ?? 0))),
            'ninox.push_goods_receipt' => $push->pushGoodsReceipt(\App\Models\Procurement\GoodsReceipt::findOrFail((int) ($payload['goods_receipt_id'] ?? 0))),
            'ninox.sync_tour'          => $this->pullTourStatus(Tour::findOrFail((int) ($payload['tour_id'] ?? 0))),
            default => throw new \UnexpectedValueException("Unknown ninox push type: {$task->type}"),
        };
    }

    // ── Customers ─────────────────────────────────────────────────────────────

    public function pullCustomers(): int
    {
        $tableId = config('services.ninox.tables.kunden');
        $records = $this->client->getAllRecords($tableId);
        $imported = 0;

        foreach ($records as $record) {
            try {
                $imported += (int) $this->processCustomerRecord($record);
            } catch (\Throwable $e) {
                Log::warning('Ninox pull: customer record failed', [
                    'ninox_id' => $record['id'] ?? null,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $imported;
    }

    // ── Employees ─────────────────────────────────────────────────────────────

    public function pullEmployees(): int
    {
        $tableId = config('services.ninox.tables.mitarbeiter');
        $records = $this->client->getAllRecords($tableId);
        $imported = 0;

        foreach ($records as $record) {
            try {
                $imported += (int) $this->processEmployeeRecord($record);
            } catch (\Throwable $e) {
                Log::warning('Ninox pull: employee record failed', [
                    'ninox_id' => $record['id'] ?? null,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $imported;
    }

    // ── Tours ─────────────────────────────────────────────────────────────────

    /**
     * Import Liefer-Tour records from Ninox (only today and future dates).
     * Creates Tours, Orders, and TourStops in shoptour2.
     */
    public function pullTours(?string $fromDate = null): int
    {
        $fromDate = $fromDate ?? Carbon::today()->toDateString();
        $tableId  = config('services.ninox.tables.liefer_tour');
        $records  = $this->client->getAllRecords($tableId);
        $imported = 0;

        foreach ($records as $record) {
            try {
                $imported += (int) $this->processTourRecord($record, $fromDate);
            } catch (\Throwable $e) {
                Log::warning('Ninox pull: tour record failed', [
                    'ninox_id' => $record['id'] ?? null,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $imported;
    }

    /**
     * Fetch the Ninox status for a single tour and set it to "done" if Ninox says geliefert (4).
     * Only advances status — never downgrades planned or in_progress.
     * Uses updateQuietly to avoid triggering the TourObserver loop.
     */
    public function pullTourStatus(Tour $tour): bool
    {
        if (! $tour->ninox_id) {
            return false;
        }

        $record      = $this->client->getRecord(config('services.ninox.tables.liefer_tour'), $tour->ninox_id);
        $fields      = $record['fields'] ?? [];
        $ninoxStatus = (int) ($fields['Status'] ?? 0);

        $statusChanged = false;
        $updates = [];

        if ($ninoxStatus === 4 && $tour->status !== Tour::STATUS_DONE) {
            $updates['status'] = Tour::STATUS_DONE;
            $statusChanged = true;
        }

        $name = isset($fields['Text']) ? trim((string) $fields['Text']) : null;
        if ($name && $tour->name !== $name) {
            $updates['name'] = $name;
        }

        if ($updates) {
            $tour->updateQuietly($updates);
            if ($statusChanged) {
                Log::info('Ninox tour status synced → done', ['tour_id' => $tour->id, 'ninox_id' => $tour->ninox_id]);
            }
        }

        // Warenkorb-Zähler für alle Orders dieser Tour nachfüllen
        $orderIds = $fields['Bestellannahme'] ?? [];
        if (is_array($orderIds)) {
            foreach ($orderIds as $ninoxOrderId) {
                try {
                    $this->pullOrderForTour((int) $ninoxOrderId);
                } catch (\Throwable $e) {
                    Log::warning('Ninox sync: Warenkorb-Update fehlgeschlagen', [
                        'ninox_order_id' => $ninoxOrderId,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        }

        return $statusChanged;
    }

    private function processTourRecord(array $record, string $fromDate): bool
    {
        $ninoxId = (string) ($record['id'] ?? '');
        $fields  = $record['fields'] ?? [];

        if (! $ninoxId) {
            return false;
        }

        $datum = $fields['Datum'] ?? null;
        if (! $datum || $datum < $fromDate) {
            return false;
        }

        // Find or create the Tour
        $name = isset($fields['Text']) ? trim((string) $fields['Text']) : null;
        $tour = Tour::where('ninox_id', $ninoxId)->first();
        if (! $tour) {
            $driver = isset($fields['Fahrer'])
                ? Employee::where('ninox_source_id', (string) $fields['Fahrer'])->first()
                : null;

            $tour = Tour::create([
                'ninox_id'           => $ninoxId,
                'tour_date'          => $datum,
                'driver_employee_id' => $driver?->id,
                'status'             => Tour::STATUS_PLANNED,
                'name'               => $name ?: null,
            ]);

            Log::info('Ninox pull: tour created', ['tour_id' => $tour->id, 'ninox_id' => $ninoxId, 'date' => $datum]);
        } elseif ($name && ! $tour->name) {
            $tour->updateQuietly(['name' => $name]);
        }

        // Import each Bestellannahme order and create/update TourStops.
        // Pre-fetch all Bestellannahme records to read "Reihenfolge" without a second round-trip.
        $orderIds = $fields['Bestellannahme'] ?? [];
        if (! is_array($orderIds)) {
            return true;
        }

        $bestellannahmeTableId = config('services.ninox.tables.bestellannahme');

        // Pre-fetch records to get Reihenfolge; store keyed by ninoxOrderId.
        $orderRecords = [];
        foreach ($orderIds as $ninoxOrderId) {
            try {
                $rec                         = $this->client->getRecord($bestellannahmeTableId, (string) $ninoxOrderId);
                $orderRecords[$ninoxOrderId] = $rec['fields'] ?? [];
            } catch (\Throwable $e) {
                $orderRecords[$ninoxOrderId] = [];
                Log::warning('Ninox pull: Bestellannahme prefetch failed', [
                    'ninox_order_id' => $ninoxOrderId,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        // Sort ascending by "Reihenfolge"; fall back to PHP_INT_MAX (appears last).
        uasort($orderRecords, function (array $a, array $b): int {
            $ra = isset($a['Reihenfolge']) ? (int) $a['Reihenfolge'] : PHP_INT_MAX;
            $rb = isset($b['Reihenfolge']) ? (int) $b['Reihenfolge'] : PHP_INT_MAX;
            return $ra <=> $rb;
        });

        $stopIndex = 1;
        foreach ($orderRecords as $ninoxOrderId => $orderFields) {
            try {
                $order = $this->pullOrderForTour((int) $ninoxOrderId, $orderFields);
                if (! $order) {
                    continue;
                }

                // updateOrCreate so re-imports correct the stop_index if Reihenfolge changed.
                TourStop::updateOrCreate(
                    ['tour_id' => $tour->id, 'order_id' => $order->id],
                    ['stop_index' => $stopIndex, 'status' => TourStop::STATUS_OPEN]
                );
                $stopIndex++;
            } catch (\Throwable $e) {
                Log::warning('Ninox pull: tour stop failed', [
                    'ninox_order_id' => $ninoxOrderId,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    /**
     * Fetch (or accept pre-fetched) Bestellannahme fields and import as an Order.
     * Returns the existing or newly created Order, or null if customer can't be resolved.
     *
     * @param array<string, mixed> $prefetchedFields Pass the already-fetched fields array to avoid a
     *                                               second API round-trip when called from processTourRecord().
     */
    public function pullOrderForTour(int $ninoxOrderId, array $prefetchedFields = []): ?Order
    {
        if ($prefetchedFields) {
            $fields = $prefetchedFields;
        } else {
            $record = $this->client->getRecord(
                config('services.ninox.tables.bestellannahme'),
                (string) $ninoxOrderId
            );
            $fields = $record['fields'] ?? [];
        }

        // Already in shoptour2? Refresh count and re-sync items when stale.
        $existing = Order::where('ninox_id', (string) $ninoxOrderId)->first();
        if ($existing) {
            $this->pullWarenkorbCount($existing, $fields);
            $existing->refresh();

            $ninoxCount  = (int) ($existing->ninox_item_count ?? 0);
            $localCount  = $existing->items()->where('price_source', 'ninox')->count();
            $hasNullIds  = $localCount > 0
                && $existing->items()->where('price_source', 'ninox')->whereNull('product_id')->exists();

            // Re-import if: no items yet, count changed, or any item is unmatched (product_id null)
            if ($localCount === 0 || $localCount !== $ninoxCount || $hasNullIds) {
                $existing->items()->where('price_source', 'ninox')->delete();
                $this->importWarenkorbItems($existing, $fields);
            }

            return $existing;
        }

        $ninoxKundenId = isset($fields['Kunden']) ? (int) $fields['Kunden'] : null;
        if (! $ninoxKundenId) {
            Log::warning('Ninox pull: order has no Kunden field', ['ninox_order_id' => $ninoxOrderId]);
            return null;
        }

        $customer = $this->findOrCreateCustomerStub($ninoxKundenId);
        if (! $customer) {
            Log::warning('Ninox pull: kunde nicht gefunden', [
                'ninox_order_id'  => $ninoxOrderId,
                'ninox_kunden_id' => $ninoxKundenId,
            ]);
            return null;
        }

        $deliveryDate = isset($fields['Lieferdatum']) ? $fields['Lieferdatum'] : null;
        $notes        = $fields['Text (mehrzeilig)'] ?? null;

        $order = DB::transaction(function () use ($ninoxOrderId, $customer, $deliveryDate, $notes) {
            $orderNumber = app(OrderNumberService::class)->generate();

            return Order::create([
                'customer_id'               => $customer->id,
                'customer_group_id_snapshot' => $customer->customer_group_id,
                'ninox_id'                  => (string) $ninoxOrderId,
                'ninox_pushed_at'           => now(),
                'order_number'              => $orderNumber,
                'status'                    => 'confirmed',
                'delivery_type'             => 'home_delivery',
                'delivery_date'             => $deliveryDate,
                'notes'                     => $notes,
            ]);
        });

        Log::info('Ninox pull: order created', [
            'order_id'        => $order->id,
            'order_number'    => $order->order_number,
            'ninox_order_id'  => $ninoxOrderId,
            'customer_id'     => $customer->id,
        ]);

        $this->pullWarenkorbCount($order, $fields);
        $this->importWarenkorbItems($order, $fields);

        return $order;
    }

    /**
     * Import Warenkorb-Artikel as OrderItem records for a Ninox-imported order.
     * Skips silently if the order already has items.
     */
    private function importWarenkorbItems(Order $order, array $bestellannahmeFields): void
    {
        $warenkorbIds = $bestellannahmeFields['Warenkorb-Artikel'] ?? [];
        if (! is_array($warenkorbIds) || count($warenkorbIds) === 0) {
            return;
        }

        $ebTableId = config('services.ninox.tables.warenkorb_artikel');
        $zTableId  = config('services.ninox.tables.marktbestand');

        foreach ($warenkorbIds as $wbId) {
            try {
                $wbRecord = $this->client->getRecord($ebTableId, (string) $wbId);
                $wbFields = $wbRecord['fields'] ?? [];

                $anzahl         = (int) ($wbFields['Anzahl'] ?? 0);
                $einzelpreisEur = (float) ($wbFields['Einzelpreis'] ?? 0);
                $pfandGesamt    = (float) ($wbFields['Pfandpreis Gesamt'] ?? 0);
                $marktbestandId = $wbFields['Marktbestand'] ?? null;

                if ($anzahl <= 0) {
                    continue;
                }

                $artNummer          = '';
                $artNrKolabriKasten = '';
                $artikelname        = 'Unbekanntes Produkt';
                $taxBp              = 1_900; // 19% default

                if ($marktbestandId) {
                    try {
                        $zbRecord           = $this->client->getRecord($zTableId, (string) $marktbestandId);
                        $zbFields           = $zbRecord['fields'] ?? [];
                        $artNummer          = (string) ($zbFields['ArtNummer'] ?? '');
                        $artNrKolabriKasten = (string) ($zbFields['ArtNrKolabriKasten'] ?? '');
                        $artikelname        = (string) ($zbFields['Artikelname'] ?? 'Unbekanntes Produkt');
                        $taxBp              = $this->parseMwstToBasisPoints((string) ($zbFields['MWST-Satz'] ?? '19%'));
                    } catch (\Throwable $e) {
                        Log::warning('Ninox import: Marktbestand nicht abrufbar', [
                            'marktbestand_id' => $marktbestandId,
                            'error'           => $e->getMessage(),
                        ]);
                    }
                }

                // Match local product: ArtNummer first (already the shoptour2 artikelnummer for most
                // products), then ArtNrKolabriKasten as fallback (used when ArtNummer is a legacy
                // supplier number that doesn't exist in the local catalog).
                $product = null;
                $matchedArtNr = '';
                if ($artNummer) {
                    $product = \App\Models\Catalog\Product::where('artikelnummer', $artNummer)->first();
                    if ($product) {
                        $matchedArtNr = $artNummer;
                    }
                }
                if (! $product && $artNrKolabriKasten) {
                    $product = \App\Models\Catalog\Product::where('artikelnummer', $artNrKolabriKasten)->first();
                    if ($product) {
                        $matchedArtNr = $artNrKolabriKasten;
                    }
                }
                // Snapshot: use the number we actually matched with, else prefer ArtNrKolabriKasten
                $artikelnummerSnapshot = $matchedArtNr ?: ($artNrKolabriKasten ?: $artNummer);

                $grossMilli   = (int) round($einzelpreisEur * 1_000_000);
                $netMilli     = (int) round($grossMilli / (1 + $taxBp / 10_000));
                $depositMilli = $anzahl > 0 ? (int) round($pfandGesamt / $anzahl * 1_000_000) : 0;

                \App\Models\Orders\OrderItem::create([
                    'order_id'                      => $order->id,
                    'product_id'                    => $product?->id,
                    'unit_price_net_milli'          => $netMilli,
                    'unit_price_gross_milli'        => $grossMilli,
                    'price_source'                  => 'ninox',
                    'tax_rate_id'                   => $product?->tax_rate_id,
                    'tax_rate_basis_points'         => $taxBp,
                    'pfand_set_id'                  => null,
                    'unit_deposit_milli'            => $depositMilli,
                    'deposit_tax_rate_basis_points' => $depositMilli > 0 ? $taxBp : 0,
                    'qty'                           => $anzahl,
                    'is_backorder'                  => false,
                    'product_name_snapshot'         => $product?->produktname ?? $artikelname,
                    'artikelnummer_snapshot'        => $artikelnummerSnapshot,
                ]);

                Log::info('Ninox import: OrderItem erstellt', [
                    'order_id'     => $order->id,
                    'warenkorb_id' => $wbId,
                    'artikelnummer' => $artNummer,
                    'qty'          => $anzahl,
                    'matched_product' => $product?->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Ninox import: Warenkorb-Artikel fehlgeschlagen', [
                    'order_id'     => $order->id,
                    'warenkorb_id' => $wbId,
                    'error'        => $e->getMessage(),
                ]);
            }
        }
    }

    private function parseMwstToBasisPoints(string $mwstSatz): int
    {
        $percent = (float) str_replace(['%', ','], ['', '.'], trim($mwstSatz));
        return (int) round($percent * 100); // scale: 10_000 = 100 %
    }

    private function pullWarenkorbCount(Order $order, array $bestellannahmeFields): void
    {
        // Primär: "Anzahl VPE" ist von Ninox bereits fertig summiert
        if (isset($bestellannahmeFields['Anzahl VPE'])) {
            $vpe = (int) $bestellannahmeFields['Anzahl VPE'];
            if ($vpe > 0) {
                $order->updateQuietly(['ninox_item_count' => $vpe]);
            }
            return;
        }

        // Fallback: Einzelne Warenkorb-Artikel summieren (Feld "Warenkorb-Artikel", Menge "Anzahl")
        $warenkorbIds = $bestellannahmeFields['Warenkorb-Artikel'] ?? [];
        if (! is_array($warenkorbIds) || count($warenkorbIds) === 0) {
            return;
        }

        $tableId  = config('services.ninox.tables.warenkorb_artikel');
        $totalQty = 0;

        foreach ($warenkorbIds as $wbId) {
            try {
                $wbRecord  = $this->client->getRecord($tableId, (string) $wbId);
                $totalQty += (int) ($wbRecord['fields']['Anzahl'] ?? 0);
            } catch (\Throwable $e) {
                Log::warning('Ninox Warenkorb-Artikel: Fehler', [
                    'warenkorb_id' => $wbId,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        if ($totalQty > 0) {
            $order->updateQuietly(['ninox_item_count' => $totalQty]);
        }
    }

    /**
     * Find a local Customer by ninox_kunden_id, or by Kundennummer fallback.
     * If no match found, create a stub from Ninox data.
     */
    public function findOrCreateCustomerStub(int $ninoxKundenId): ?Customer
    {
        // 1. Already linked?
        $customer = Customer::where('ninox_kunden_id', $ninoxKundenId)->first();
        if ($customer) {
            return $customer;
        }

        // 2. Fetch Ninox record to get Kundennummer
        try {
            $record = $this->client->getRecord(
                config('services.ninox.tables.kunden'),
                (string) $ninoxKundenId
            );
        } catch (\Throwable $e) {
            Log::warning('Ninox pull: Kunden-Record nicht abrufbar', [
                'ninox_kunden_id' => $ninoxKundenId,
                'error'           => $e->getMessage(),
            ]);
            return null;
        }

        $fields      = $record['fields'] ?? [];
        $kundennummer = (string) ($fields['Kundennummer'] ?? '');

        // 3. Fallback: match by Kundennummer from Ninox
        if ($kundennummer) {
            $customer = Customer::where('customer_number', $kundennummer)->first();
            if ($customer) {
                $customer->updateQuietly(['ninox_kunden_id' => $ninoxKundenId]);
                Log::info('Ninox pull: Kunde verknüpft via Kundennummer', [
                    'customer_id'     => $customer->id,
                    'ninox_kunden_id' => $ninoxKundenId,
                    'kundennummer'    => $kundennummer,
                ]);
                return $customer;
            }
        }

        // 4. Create stub customer from Ninox data
        // customer_group_id is NOT NULL — use group 1 (Privatkunden) as default stub
        // saveQuietly() bypasses CustomerObserver so no ninox.push_customer task is created
        $customer = new Customer([
            'ninox_kunden_id'  => $ninoxKundenId,
            'ninox_pushed_at'  => now(),
            'customer_number'  => $kundennummer ?: ('NINOX-' . $ninoxKundenId),
            'customer_group_id' => 1,
            'first_name'       => $fields['Vorname'] ?? null,
            'last_name'        => $fields['Nachname'] ?? null,
            'company_name'     => $fields['Firmenname'] ?? null,
            'email'            => $fields['E-Mail'] ?? null,
            'active'           => true,
        ]);
        $customer->saveQuietly();

        Log::info('Ninox pull: Kunden-Stub erstellt', [
            'customer_id'     => $customer->id,
            'ninox_kunden_id' => $ninoxKundenId,
            'customer_number' => $customer->customer_number,
        ]);

        return $customer;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function processCustomerRecord(array $record): bool
    {
        $ninoxId       = (string) ($record['id'] ?? '');
        $ninoxModified = isset($record['modifiedAt'])
            ? Carbon::parse($record['modifiedAt'])
            : null;
        $fields        = $record['fields'] ?? [];

        if (! $ninoxId || ! $ninoxModified) {
            return false;
        }

        $customer = Customer::where('ninox_kunden_id', $ninoxId)->first();

        // Loop guard: if the Ninox record was modified at or before our last push + buffer, skip
        if ($customer?->ninox_pushed_at
            && $ninoxModified->lte($customer->ninox_pushed_at->addSeconds(60))) {
            return false;
        }

        // Conflict: both sides changed since last pull → shoptour2 wins → push, skip import
        if ($customer
            && $customer->updated_at > $this->lastPullAt
            && $ninoxModified > $this->lastPullAt) {
            DeferredTask::create([
                'type'         => 'ninox.push_customer',
                'payload_json' => json_encode(['customer_id' => $customer->id]),
                'status'       => DeferredTask::STATUS_PENDING,
            ]);
            return false;
        }

        // Only Ninox changed → import
        $mapped = $this->mapCustomerFields($fields);
        if (empty($mapped)) {
            return false;
        }

        if ($customer) {
            $customer->updateQuietly($mapped);
        } else {
            // New record in Ninox that doesn't exist in shoptour2 — create stub
            // saveQuietly() bypasses CustomerObserver so no ninox.push_customer task is created
            $mapped['ninox_kunden_id'] = $ninoxId;
            if (! isset($mapped['customer_number'])) {
                $mapped['customer_number'] = 'NINOX-' . $ninoxId;
            }
            (new Customer($mapped))->saveQuietly();
        }

        return true;
    }

    private function processEmployeeRecord(array $record): bool
    {
        $ninoxId       = (string) ($record['id'] ?? '');
        $ninoxModified = isset($record['modifiedAt'])
            ? Carbon::parse($record['modifiedAt'])
            : null;
        $fields        = $record['fields'] ?? [];

        if (! $ninoxId || ! $ninoxModified) {
            return false;
        }

        $employee = Employee::where('ninox_source_id', $ninoxId)->first();

        if ($employee?->ninox_pushed_at
            && $ninoxModified->lte($employee->ninox_pushed_at->addSeconds(60))) {
            return false;
        }

        if ($employee
            && $employee->updated_at > $this->lastPullAt
            && $ninoxModified > $this->lastPullAt) {
            DeferredTask::create([
                'type'         => 'ninox.push_employee',
                'payload_json' => json_encode(['employee_id' => $employee->id]),
                'status'       => DeferredTask::STATUS_PENDING,
            ]);
            return false;
        }

        $mapped = $this->mapEmployeeFields($fields);
        if (empty($mapped) || ! $employee) {
            // Don't auto-create employees — they require HR data we don't have from Ninox alone
            return false;
        }

        $employee->updateQuietly($mapped);
        return true;
    }

    /** @param array<string, mixed> $fields */
    private function mapCustomerFields(array $fields): array
    {
        $mapped = [];

        if (isset($fields['Kundennummer'])) {
            $mapped['customer_number'] = (string) $fields['Kundennummer'];
        }
        if (isset($fields['Vorname'])) {
            $mapped['first_name'] = (string) $fields['Vorname'];
        }
        if (isset($fields['Nachname'])) {
            $mapped['last_name'] = (string) $fields['Nachname'];
        }
        if (isset($fields['Firmenname'])) {
            $mapped['company_name'] = (string) $fields['Firmenname'];
        }
        if (isset($fields['E-Mail'])) {
            $mapped['email'] = (string) $fields['E-Mail'];
        }

        return $mapped;
    }

    /** @param array<string, mixed> $fields */
    private function mapEmployeeFields(array $fields): array
    {
        $mapped = [];

        if (isset($fields['Vorname'])) {
            $mapped['first_name'] = (string) $fields['Vorname'];
        }
        if (isset($fields['Nachname'])) {
            $mapped['last_name'] = (string) $fields['Nachname'];
        }

        return $mapped;
    }

    // =========================================================================
    // Standalone-Order Import (keine Tour-Zuordnung)
    // =========================================================================

    /**
     * Importiert alle ninox_bestellannahme-Einträge ohne liefer_tour,
     * die noch kein orders-Objekt in shoptour2 haben.
     *
     * @return array{imported: int, failed: int}
     */
    /**
     * @return array{imported: int, failed: int, remaining: int}
     */
    public function pullStandaloneOrders(): array
    {
        $imported = 0;
        $failed   = 0;

        $alreadyImportedIds = Order::whereNotNull('ninox_id')
            ->pluck('ninox_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $baseQuery = DB::table('ninox_bestellannahme')
            ->whereNull('liefer_tour')
            ->whereNotNull('kunden')
            ->when($alreadyImportedIds, fn ($q) => $q->whereNotIn(
                DB::raw('CAST(ninox_id AS CHAR)'),
                $alreadyImportedIds
            ));

        $total = (clone $baseQuery)->count();

        $rows = (clone $baseQuery)
            ->orderByDesc('ninox_id')
            ->limit(500)
            ->get();

        foreach ($rows as $row) {
            try {
                $order = $this->pullOrderForTour((int) $row->ninox_id);
                if ($order) {
                    $imported++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error("pullStandaloneOrders: ninox_id={$row->ninox_id} — {$e->getMessage()}");
            }
        }

        $remaining = max(0, $total - $imported - $failed);

        return compact('imported', 'failed', 'remaining');
    }

    // =========================================================================
    // Pull Anlieferungen (GoodsReceipts) from Ninox
    // =========================================================================

    /**
     * Import supplier deliveries from ninox_bestellung → goods_receipts.
     *
     * Conflict rule: if ninox_updated_at ≤ ninox_pushed_at + 60s we assume
     * the record was just echoed back from our own push → skip.
     * For new records (ninox_bestellung_id not yet in goods_receipts) we always create.
     * Status is only set on create; existing records keep their local status.
     */
    public function pullGoodsReceipts(): array
    {
        $imported = 0;
        $skipped  = 0;

        $warehouses = \App\Models\Inventory\Warehouse::all()->keyBy('id');

        DB::table('ninox_bestellung')->orderBy('ninox_id')->each(
            function (object $row) use ($warehouses, &$imported, &$skipped): void {
                // 1. Supplier-Match via ninox_lieferanten_id
                $supplier = \App\Models\Supplier\Supplier::where('ninox_lieferanten_id', $row->lieferanten)->first();
                if (!$supplier) {
                    $skipped++;
                    return;
                }

                // 2. Match or prepare new GoodsReceipt
                $gr = \App\Models\Procurement\GoodsReceipt::firstOrNew([
                    'ninox_bestellung_id' => (string) $row->ninox_id,
                ]);

                // 3. Conflict-guard: own push echoed back within 60 s → skip
                $ninoxUpdated = $row->ninox_updated_at ? Carbon::parse($row->ninox_updated_at) : null;
                if ($gr->exists && $gr->ninox_pushed_at && $ninoxUpdated
                    && $ninoxUpdated->lte($gr->ninox_pushed_at->addSeconds(60))) {
                    $skipped++;
                    return;
                }

                // 4. Warehouse: fuzzy name-match, fallback Warehouse ID 1
                $warehouseId = 1;
                if ($row->lieferung_erfolgt_nach) {
                    $needle = strtolower((string) $row->lieferung_erfolgt_nach);
                    $match  = $warehouses->first(function ($w) use ($needle) {
                        $name = strtolower($w->name);
                        return str_contains($name, $needle) || str_contains($needle, $name);
                    });
                    if ($match) {
                        $warehouseId = $match->id;
                    }
                }

                // 5. Optional PO-Match via bestell_schein (PO number)
                $purchaseOrderId = $gr->purchase_order_id;
                if ($row->bestell_schein && !$purchaseOrderId) {
                    $purchaseOrderId = \App\Models\Supplier\PurchaseOrder::where('po_number', $row->bestell_schein)->value('id');
                }

                // 6. Fill — preserve existing status on updates
                $gr->fill([
                    'company_id'          => 1,
                    'supplier_id'         => $supplier->id,
                    'warehouse_id'        => $warehouseId,
                    'purchase_order_id'   => $purchaseOrderId,
                    'lieferschein_nr'     => $row->lieferschein ?: $gr->lieferschein_nr,
                    'arrived_at'          => $row->anliefertag
                                            ? Carbon::parse($row->anliefertag)
                                            : $gr->arrived_at,
                    'status'              => $gr->exists
                                            ? $gr->status
                                            : ($row->geliefert
                                                ? \App\Models\Procurement\GoodsReceipt::STATUS_GEBUCHT
                                                : \App\Models\Procurement\GoodsReceipt::STATUS_ANGEKUENDIGT),
                    'notiz'               => $row->notiz ?: $gr->notiz,
                    'ninox_bestellung_id' => (string) $row->ninox_id,
                ]);

                // saveQuietly prevents GoodsReceiptObserver from immediately re-pushing to Ninox
                $gr->saveQuietly();
                $imported++;
            }
        );

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
