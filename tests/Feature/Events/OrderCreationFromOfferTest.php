<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Models\Admin\AuditLog;
use App\Models\Events\EventLogisticsAppointment;
use App\Models\Events\EventOccurrence;
use App\Models\Events\EventOfferItem;
use App\Models\Events\EventOfferVersion;
use App\Models\Pricing\Customer;
use App\Services\Events\EventOfferAuditService;
use App\Services\Events\OfferAcceptanceService;
use App\Services\Orders\OrderNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PROJ-38: Tests that verify Order creation from offer acceptance
 */
class OrderCreationFromOfferTest extends TestCase
{
    use RefreshDatabase;

    private OfferAcceptanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OfferAcceptanceService(
            new OrderNumberService(),
            new EventOfferAuditService(),
        );
    }

    private function createValidSetup(): array
    {
        $customer = Customer::factory()->create();

        $occ = EventOccurrence::create([
            'title'          => 'Sommerfest 2026',
            'event_type'     => 'sommerfest',
            'customer_id'    => $customer->id,
            'event_start_at' => now()->addDays(45),
            'event_year'     => now()->addDays(45)->year,
            'offer_status'   => 'sent',
            'event_status'   => 'planned',
        ]);

        $version = EventOfferVersion::create([
            'event_occurrence_id' => $occ->id,
            'offer_number'        => 'ST-A-2026-000099',
            'version_number'      => 1,
            'source_system'       => 'shoptour2',
            'status'              => 'sent',
            'net_total_milli'     => 2_000_000_000,
            'gross_total_milli'   => 2_380_000_000,
            'deposit_total_milli' => 150_000_000,
        ]);

        EventOfferItem::create([
            'event_offer_version_id' => $version->id,
            'name'     => 'Bier Kasten 0,5l',
            'quantity' => 20,
            'unit'     => 'Kasten',
            'sort_order' => 1,
        ]);

        EventLogisticsAppointment::create([
            'event_occurrence_id' => $occ->id,
            'type'           => 'delivery',
            'status'         => 'planned',
            'scheduled_date' => now()->addDays(43)->toDateString(),
        ]);

        return compact('occ', 'version', 'customer');
    }

    public function test_order_is_created_as_event_order(): void
    {
        ['occ' => $occ, 'version' => $version] = $this->createValidSetup();

        $result = $this->service->accept($version, 1);

        $this->assertTrue($result['success']);
        $this->assertTrue((bool) $result['order']->is_event_order);
    }

    public function test_order_has_correct_delivery_type(): void
    {
        ['occ' => $occ, 'version' => $version] = $this->createValidSetup();

        $result = $this->service->accept($version, 1);

        $this->assertEquals('home_delivery', $result['order']->delivery_type);
    }

    public function test_converted_order_id_is_set(): void
    {
        ['occ' => $occ, 'version' => $version] = $this->createValidSetup();

        $result = $this->service->accept($version, 1);

        $version->refresh();
        $this->assertEquals($result['order']->id, $version->converted_order_id);
    }

    public function test_event_status_becomes_confirmed(): void
    {
        ['occ' => $occ, 'version' => $version] = $this->createValidSetup();

        $this->service->accept($version, 1);

        $occ->refresh();
        $this->assertEquals('confirmed', $occ->event_status);
    }

    public function test_audit_log_entry_is_written(): void
    {
        ['occ' => $occ, 'version' => $version] = $this->createValidSetup();

        $this->service->accept($version, 1);

        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'event.offer.accepted',
            'subject_type' => EventOfferVersion::class,
            'subject_id'   => $version->id,
        ]);
    }

    public function test_order_notes_reference_offer_number(): void
    {
        ['occ' => $occ, 'version' => $version] = $this->createValidSetup();

        $result = $this->service->accept($version, 1);

        $this->assertStringContainsString('ST-A-2026-000099', $result['order']->notes);
    }
}
