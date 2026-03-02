<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\Product;
use App\Models\Delivery\RegularDeliveryTour;
use App\Models\Delivery\Tour;
use App\Models\Delivery\TourStop;
use App\Models\Driver\DriverApiToken;
use App\Models\Inventory\Warehouse;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artisan command: kolabri:seed-demo-tour
 *
 * Creates a minimal but realistic dataset for Driver PWA end-to-end testing:
 *   - 1 RegularDeliveryTour template
 *   - 1 concrete Tour for the given date
 *   - N customers with German addresses & delivery notes
 *   - N confirmed orders with one line item each
 *   - N TourStops (one per order, ascending stop_index)
 *   - 1 DriverApiToken (employee_id = null) — plain token printed to stdout
 *
 * The token has employee_id = null so the bootstrap endpoint returns the tour
 * regardless of which driver is logged in.
 *
 * Usage:
 *   php artisan kolabri:seed-demo-tour
 *   php artisan kolabri:seed-demo-tour --date=2026-03-01 --tour="Stadtmitte" --stops=3
 *
 * Example curl (PowerShell):
 *   $h = @{Authorization='Bearer <TOKEN>'}
 *   Invoke-RestMethod "http://localhost:8000/api/driver/bootstrap?date=<DATE>" `
 *       -Headers $h | ConvertTo-Json -Depth 10
 */
class SeedDemoTourCommand extends Command
{
    protected $signature = 'kolabri:seed-demo-tour
                            {--date=   : Delivery date (YYYY-MM-DD). Defaults to today.}
                            {--tour=   : Name of the tour template. Defaults to "Demo-Tour".}
                            {--stops=2 : Number of stops to create (1–10).}';

    protected $description = 'Seed a minimal demo tour for Driver PWA end-to-end testing.';

    // -------------------------------------------------------------------------
    // Sample data pools (10 realistic German fixtures)
    // -------------------------------------------------------------------------

    private const CUSTOMER_DATA = [
        [
            'first_name'            => 'Max',
            'last_name'             => 'Müller',
            'delivery_address_text' => 'Hauptstraße 12, 64285 Darmstadt',
            'delivery_note'         => 'Bitte klingeln – EG links',
        ],
        [
            'first_name'            => 'Petra',
            'last_name'             => 'Schmidt',
            'delivery_address_text' => 'Rheinstraße 5, 64295 Darmstadt',
            'delivery_note'         => 'Hinterhof, linke Tür',
        ],
        [
            'first_name'            => 'Thomas',
            'last_name'             => 'Weber',
            'delivery_address_text' => 'Berliner Allee 34, 64295 Darmstadt',
            'delivery_note'         => null,
        ],
        [
            'first_name'            => 'Sandra',
            'last_name'             => 'Becker',
            'delivery_address_text' => 'Luisenplatz 7, 64283 Darmstadt',
            'delivery_note'         => 'Klingel defekt – bitte anrufen',
        ],
        [
            'first_name'            => 'Klaus',
            'last_name'             => 'Fischer',
            'delivery_address_text' => 'Kasinostraße 22, 64293 Darmstadt',
            'delivery_note'         => 'Warenannahme Seiteneingang',
        ],
        [
            'first_name'            => 'Monika',
            'last_name'             => 'Hoffmann',
            'delivery_address_text' => 'Heidelberger Str. 55, 64285 Darmstadt',
            'delivery_note'         => '2. Stockwerk, bitte laut klingeln',
        ],
        [
            'first_name'            => 'Hans',
            'last_name'             => 'König',
            'delivery_address_text' => 'Frankfurter Str. 3, 64293 Darmstadt',
            'delivery_note'         => null,
        ],
        [
            'first_name'            => 'Ute',
            'last_name'             => 'Lehmann',
            'delivery_address_text' => 'Wilhelminenstraße 11, 64283 Darmstadt',
            'delivery_note'         => 'Rezeption – bitte bis 14 Uhr',
        ],
        [
            'first_name'            => 'Frank',
            'last_name'             => 'Zimmermann',
            'delivery_address_text' => 'Elisabethenstraße 9, 64283 Darmstadt',
            'delivery_note'         => null,
        ],
        [
            'first_name'            => 'Gabi',
            'last_name'             => 'Wolf',
            'delivery_address_text' => 'Marktplatz 1, 64283 Darmstadt',
            'delivery_note'         => 'Gastronomieeingang',
        ],
    ];

    private const PRODUCTS = [
        [
            'artikelnummer' => 'DEMO-WASSER-05',
            'produktname'   => 'Mineralwasser 0,5 l Kasten',
            'net_milli'     => 8_000_000,
            'gross_milli'   => 9_520_000,
            'tax_bp'        => 190_000,
        ],
        [
            'artikelnummer' => 'DEMO-COLA-15',
            'produktname'   => 'Cola 1,5 l 6er-Kasten',
            'net_milli'     => 12_000_000,
            'gross_milli'   => 14_280_000,
            'tax_bp'        => 190_000,
        ],
        [
            'artikelnummer' => 'DEMO-BIER-05',
            'produktname'   => 'Bier 0,5 l Kasten 20er',
            'net_milli'     => 18_000_000,
            'gross_milli'   => 19_080_000,
            'tax_bp'        => 70_000,
        ],
        [
            'artikelnummer' => 'DEMO-SAFT-10',
            'produktname'   => 'Apfelsaft 1,0 l 6er-Kasten',
            'net_milli'     => 10_000_000,
            'gross_milli'   => 10_700_000,
            'tax_bp'        => 70_000,
        ],
        [
            'artikelnummer' => 'DEMO-WEIN-075',
            'produktname'   => 'Weißwein 0,75 l Flasche',
            'net_milli'     => 7_000_000,
            'gross_milli'   => 8_330_000,
            'tax_bp'        => 190_000,
        ],
    ];

    // -------------------------------------------------------------------------

    public function handle(): int
    {
        $date      = (string) ($this->option('date') ?: now()->toDateString());
        $tourName  = (string) ($this->option('tour') ?: 'Demo-Tour');
        // Use (int) directly — the signature default '2' covers the absent-flag case,
        // and '0' must not be treated as falsy (max() clamps it to 1 below).
        $stopCount = max(1, min(10, (int) $this->option('stops')));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error("Invalid date format \"{$date}\". Expected YYYY-MM-DD.");

            return self::FAILURE;
        }

        $this->info("Seeding demo tour \"{$tourName}\" for {$date} with {$stopCount} stop(s)...");

        $plain = null;
        $tour  = null;

        DB::transaction(function () use ($date, $tourName, $stopCount, &$plain, &$tour): void {
            // ---- Catalog ancestors (find-or-create — safe to re-run) -------------

            $brandId = DB::table('brands')->where('name', 'Demo-Marke')->value('id')
                ?? (int) DB::table('brands')->insertGetId([
                    'name'       => 'Demo-Marke',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $productLineId = DB::table('product_lines')
                ->where('brand_id', $brandId)->where('name', 'Demo-Sortiment')->value('id')
                ?? (int) DB::table('product_lines')->insertGetId([
                    'brand_id'   => $brandId,
                    'name'       => 'Demo-Sortiment',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $categoryId = DB::table('categories')->where('name', 'Demo-Kategorie')->value('id')
                ?? (int) DB::table('categories')->insertGetId([
                    'name'       => 'Demo-Kategorie',
                    'parent_id'  => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $pfandSetId = DB::table('pfand_sets')->where('name', 'Kein Pfand')->value('id')
                ?? (int) DB::table('pfand_sets')->insertGetId([
                    'name'       => 'Kein Pfand',
                    'active'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $gebindeId = DB::table('gebinde')
                ->where('name', 'Kasten')->where('pfand_set_id', $pfandSetId)->value('id')
                ?? (int) DB::table('gebinde')->insertGetId([
                    'name'         => 'Kasten',
                    'pfand_set_id' => $pfandSetId,
                    'active'       => 1,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

            // ---- Products (find-or-create by artikelnummer) ----------------------

            $productCount = min($stopCount, count(self::PRODUCTS));
            $products     = [];

            for ($i = 0; $i < $productCount; $i++) {
                $pd         = self::PRODUCTS[$i];
                $products[] = Product::firstOrCreate(
                    ['artikelnummer' => $pd['artikelnummer']],
                    [
                        'brand_id'               => $brandId,
                        'product_line_id'        => $productLineId,
                        'category_id'            => $categoryId,
                        'gebinde_id'             => $gebindeId,
                        'tax_rate_id'            => 1,
                        'produktname'            => $pd['produktname'],
                        'base_price_net_milli'   => $pd['net_milli'],
                        'base_price_gross_milli' => $pd['gross_milli'],
                        'is_bundle'              => false,
                        'availability_mode'      => Product::AVAILABILITY_AVAILABLE,
                        'active'                 => true,
                    ]
                );
            }

            // ---- Customer group (find-or-create) & warehouse --------------------

            $group = CustomerGroup::firstOrCreate(
                ['name' => 'Demo-Gruppe'],
                [
                    'price_adjustment_type'                 => 'none',
                    'price_adjustment_fixed_milli'          => 0,
                    'price_adjustment_percent_basis_points' => 0,
                    'is_business'                           => false,
                    'is_deposit_exempt'                     => false,
                    'active'                                => true,
                ]
            );

            $warehouse = Warehouse::where('active', true)->first()
                ?? Warehouse::create(['name' => 'Demo-Lager', 'active' => true]);

            // ---- RegularDeliveryTour template ------------------------------------

            $regularTour = RegularDeliveryTour::create([
                'name'            => $tourName,
                'frequency'       => RegularDeliveryTour::FREQUENCY_WEEKLY,
                'day_of_week'     => 'Monday',
                'min_gebinde_qty' => 0,
                'active'          => true,
            ]);

            // ---- Concrete tour run -----------------------------------------------

            $tour = Tour::create([
                'tour_date'                => $date,
                'regular_delivery_tour_id' => $regularTour->id,
                'driver_employee_id'       => null,
                'status'                   => Tour::STATUS_PLANNED,
            ]);

            // ---- Stops -----------------------------------------------------------

            // Short random run-ID keeps customer_number unique across multiple
            // invocations (customers are per-run, not shared like catalog data).
            $runId = substr(bin2hex(random_bytes(3)), 0, 5);

            for ($i = 0; $i < $stopCount; $i++) {
                $cd      = self::CUSTOMER_DATA[$i % count(self::CUSTOMER_DATA)];
                $product = $products[$i % count($products)];
                $pd      = self::PRODUCTS[$i % count(self::PRODUCTS)];
                $qty     = random_int(2, 8);

                $customer = Customer::create([
                    'customer_group_id'     => $group->id,
                    'customer_number'       => sprintf('DEMO-%s-%02d', $runId, $i + 1),
                    'price_display_mode'    => 'gross',
                    'first_name'            => $cd['first_name'],
                    'last_name'             => $cd['last_name'],
                    'delivery_address_text' => $cd['delivery_address_text'],
                    'delivery_note'         => $cd['delivery_note'],
                    'active'                => true,
                ]);

                $order = Order::create([
                    'customer_id'                => $customer->id,
                    'customer_group_id_snapshot' => $group->id,
                    'regular_delivery_tour_id'   => $regularTour->id,
                    'status'                     => Order::STATUS_CONFIRMED,
                    'delivery_date'              => $date,
                    'warehouse_id'               => $warehouse->id,
                    'has_backorder'              => false,
                    'total_net_milli'            => $pd['net_milli'] * $qty,
                    'total_gross_milli'          => $pd['gross_milli'] * $qty,
                    'total_pfand_brutto_milli'   => 0,
                ]);

                OrderItem::create([
                    'order_id'               => $order->id,
                    'product_id'             => $product->id,
                    'unit_price_net_milli'   => $pd['net_milli'],
                    'unit_price_gross_milli' => $pd['gross_milli'],
                    'price_source'           => 'base_plus_adjustment',
                    'tax_rate_id'            => 1,
                    'tax_rate_basis_points'  => $pd['tax_bp'],
                    'pfand_set_id'           => null,
                    'unit_deposit_milli'     => 0,
                    'qty'                    => $qty,
                    'is_backorder'           => false,
                    'product_name_snapshot'  => $product->produktname,
                    'artikelnummer_snapshot' => $product->artikelnummer,
                ]);

                TourStop::create([
                    'tour_id'    => $tour->id,
                    'order_id'   => $order->id,
                    'stop_index' => $i + 1,
                    'status'     => TourStop::STATUS_OPEN,
                ]);
            }

            // ---- Driver API token -----------------------------------------------

            $plain    = bin2hex(random_bytes(24));
            $tokenRec = DriverApiToken::create([
                'employee_id' => null,
                'token_hash'  => hash('sha256', $plain),
                'label'       => "demo-tour-{$date}-{$runId}",
                'active'      => true,
            ]);

            $this->newLine();
            $this->line('<fg=green;options=bold>✓ Demo tour created successfully!</>');
            $this->newLine();

            $this->table(
                ['Key', 'Value'],
                [
                    ['Tour ID',         $tour->id],
                    ['Date',            $date],
                    ['Stops',           $stopCount],
                    ['Token label',     $tokenRec->label],
                    ['Bearer token',    $plain],
                ]
            );

            $this->newLine();
            $this->line('<fg=yellow>── curl ─────────────────────────────────────────────────────────</>');
            $this->line("curl -s -H \"Authorization: Bearer {$plain}\" \\");
            $this->line("  http://localhost:8000/api/driver/bootstrap?date={$date} | jq .");
            $this->newLine();
            $this->line('<fg=yellow>── PowerShell ────────────────────────────────────────────────────</>');
            $this->line("\$h = @{Authorization='Bearer {$plain}'}");
            $this->line("Invoke-RestMethod \"http://localhost:8000/api/driver/bootstrap?date={$date}\" \\");
            $this->line("  -Headers \$h | ConvertTo-Json -Depth 10");
            $this->newLine();
            $this->comment('Paste the Bearer token into the Driver PWA auth overlay.');
        });

        return self::SUCCESS;
    }
}
