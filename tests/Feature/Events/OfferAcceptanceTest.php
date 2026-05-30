<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Models\Events\EventLogisticsAppointment;
use App\Models\Events\EventOccurrence;
use App\Models\Events\EventOfferItem;
use App\Models\Events\EventOfferVersion;
use App\Models\Pricing\Customer;
use App\Services\Events\EventOfferAuditService;
use App\Services\Events\OfferAcceptanceService;
use App\Services\Orders\OrderNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * PROJ-38: Tests for OfferAcceptanceService::accept()
 */
class OfferAcceptanceTest extends TestCase
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

    private function makeValidOccurrence(): EventOccurrence
    {
        $customer = Customer::factory()->create();

        return EventOccurrence::create([
            'title'          => 'Test Event',
            'event_type'     => 'sommerfest',
            'customer_id'    => $customer->id,
            'event_start_at' => now()->addDays(30),
            'event_year'     => now()->addDays(30)->year,
            'offer_status'   => 'sent',
            'event_status'   => 'planned',
        ]);
    }

    private function makeValidVersion(EventOccurrence $occ): EventOfferVersion
    {
        $version = EventOfferVersion::create([
            'event_occurrence_id' => $occ->id,
            'version_number'      => 1,
            'source_system'       => 'shoptour2',
            'status'              => 'sent',
            'net_total_milli'     => 1_500_000_000,  // 1500 EUR
            'gross_total_milli'   => 1_785_000_000,
        ]);

        EventOfferItem::create([
            'event_offer_version_id' => $version->id,
            'name'                   => 'Bier Kasten',
            'quantity'               => 10,
            'unit'                   => 'Stück',
            'is_free_text'           => false,
            'sort_order'             => 1,
        ]);

        EventLogisticsAppointment::create([
            'event_occurrence_id' => $occ->id,
            'type'                => 'delivery',
            'status'              => 'planned',
            'scheduled_date'      => now()->addDays(28)->toDateString(),
        ]);

        return $version;
    }

    public function test_successful_acceptance_creates_order(): void
    {
        $occ     = $this->makeValidOccurrence();
        $version = $this->makeValidVersion($occ);

        $result = $this->service->accept($version, 1);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['order']);
        $this->assertFalse($result['review_needed']);
        $this->assertEmpty($result['errors']);
    }

    public function test_acceptance_marks_version_as_converted(): void
    {
        $occ     = $this->makeValidOccurrence();
        $version = $this->makeValidVersion($occ);

        $this->service->accept($version, 1);

        $version->refresh();
        $this->assertEquals('converted_to_order', $version->status);
        $this->assertNotNull($version->converted_order_id);
    }

    public function test_acceptance_confirms_occurrence(): void
    {
        $occ     = $this->makeValidOccurrence();
        $version = $this->makeValidVersion($occ);

        $this->service->accept($version, 1);

        $occ->refresh();
        $this->assertEquals('confirmed', $occ->event_status);
        $this->assertEquals('converted_to_order', $occ->offer_status);
    }

    public function test_fails_with_missing_customer(): void
    {
        $occ = EventOccurrence::create([
            'title'          => 'No Customer Event',
            'event_type'     => 'sommerfest',
            'customer_id'    => null,
            'event_start_at' => now()->addDays(30),
            'offer_status'   => 'sent',
            'event_status'   => 'planned',
        ]);

        $version = EventOfferVersion::create([
            'event_occurrence_id' => $occ->id,
            'version_number'      => 1,
            'source_system'       => 'shoptour2',
            'status'              => 'sent',
            'net_total_milli'     => 1_000_000_000,
        ]);

        EventOfferItem::create([
            'event_offer_version_id' => $version->id,
            'name'    => 'Test',
            'quantity'=> 1,
            'unit'    => 'Stück',
            'sort_order' => 1,
        ]);

        EventLogisticsAppointment::create([
            'event_occurrence_id' => $occ->id,
            'type'           => 'delivery',
            'status'         => 'planned',
            'scheduled_date' => now()->addDays(10)->toDateString(),
        ]);

        $result = $this->service->accept($version, 1);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['review_needed']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_fails_with_no_items(): void
    {
        $occ     = $this->makeValidOccurrence();

        $version = EventOfferVersion::create([
            'event_occurrence_id' => $occ->id,
            'version_number'      => 1,
            'source_system'       => 'shoptour2',
            'status'              => 'sent',
            'net_total_milli'     => 1_000_000_000,
        ]);

        EventLogisticsAppointment::create([
            'event_occurrence_id' => $occ->id,
            'type'           => 'delivery',
            'status'         => 'planned',
            'scheduled_date' => now()->addDays(10)->toDateString(),
        ]);

        $result = $this->service->accept($version, 1);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['review_needed']);
    }

    public function test_fails_with_missing_net_total(): void
    {
        $occ = $this->makeValidOccurrence();

        $version = EventOfferVersion::create([
            'event_occurrence_id' => $occ->id,
            'version_number'      => 1,
            'source_system'       => 'shoptour2',
            'status'              => 'sent',
            'net_total_milli'     => null,
        ]);

        EventOfferItem::create([
            'event_offer_version_id' => $version->id,
            'name'    => 'Test',
            'quantity'=> 1,
            'unit'    => 'Stück',
            'sort_order' => 1,
        ]);

        EventLogisticsAppointment::create([
            'event_occurrence_id' => $occ->id,
            'type'           => 'delivery',
            'status'         => 'planned',
            'scheduled_date' => now()->addDays(10)->toDateString(),
        ]);

        $result = $this->service->accept($version, 1);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['review_needed']);
    }
}
