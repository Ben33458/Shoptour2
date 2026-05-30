<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Models\Events\EventLogisticsAppointment;
use App\Models\Events\EventOccurrence;
use App\Models\Events\EventOfferItem;
use App\Models\Events\EventOfferVersion;
use App\Models\Events\EventRentalReservation;
use App\Models\Pricing\Customer;
use App\Services\Events\EventOfferAuditService;
use App\Services\Events\OfferAcceptanceService;
use App\Services\Orders\OrderNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PROJ-38: Tests for all 8 needs_review cases in OfferAcceptanceService
 */
class NeedsReviewTest extends TestCase
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

    private function makeCustomer(): Customer
    {
        return Customer::factory()->create();
    }

    private function makeOccurrence(?int $customerId = null, ?string $startAt = null): EventOccurrence
    {
        return EventOccurrence::create([
            'title'          => 'Test',
            'event_type'     => 'sommerfest',
            'customer_id'    => $customerId,
            'event_start_at' => $startAt ?? now()->addDays(30)->toDateTimeString(),
            'event_year'     => now()->year,
            'offer_status'   => 'sent',
            'event_status'   => 'planned',
        ]);
    }

    private function makeVersion(EventOccurrence $occ, ?int $netMilli = 1_000_000_000): EventOfferVersion
    {
        return EventOfferVersion::create([
            'event_occurrence_id' => $occ->id,
            'version_number'      => 1,
            'source_system'       => 'shoptour2',
            'status'              => 'sent',
            'net_total_milli'     => $netMilli,
        ]);
    }

    private function addItem(EventOfferVersion $v, bool $freeText = false, bool $hasArticle = false): void
    {
        EventOfferItem::create([
            'event_offer_version_id' => $v->id,
            'name'        => 'Item',
            'quantity'    => 1,
            'unit'        => 'Stück',
            'is_free_text'=> $freeText,
            'article_id'  => $hasArticle ? 1 : null,
            'sort_order'  => 1,
        ]);
    }

    private function addDelivery(EventOccurrence $occ): void
    {
        EventLogisticsAppointment::create([
            'event_occurrence_id' => $occ->id,
            'type'           => 'delivery',
            'status'         => 'planned',
            'scheduled_date' => now()->addDays(25)->toDateString(),
        ]);
    }

    // Case 1: missing customer
    public function test_review_when_customer_missing(): void
    {
        $occ = $this->makeOccurrence(null);
        $v   = $this->makeVersion($occ);
        $this->addItem($v);
        $this->addDelivery($occ);

        $result = $this->service->accept($v, 1);

        $this->assertTrue($result['review_needed']);
        $this->assertContains('Kein Kunde am Vorgang hinterlegt.', $result['errors']);
    }

    // Case 2: missing event_start_at
    public function test_review_when_event_date_missing(): void
    {
        $customer = $this->makeCustomer();
        $occ = EventOccurrence::create([
            'title'         => 'Test',
            'event_type'    => 'sommerfest',
            'customer_id'   => $customer->id,
            'event_start_at'=> null,
            'offer_status'  => 'sent',
            'event_status'  => 'planned',
        ]);
        $v = $this->makeVersion($occ);
        $this->addItem($v);
        $this->addDelivery($occ);

        $result = $this->service->accept($v, 1);

        $this->assertTrue($result['review_needed']);
        $this->assertContains('Kein Veranstaltungsdatum hinterlegt.', $result['errors']);
    }

    // Case 3: no items
    public function test_review_when_no_items(): void
    {
        $customer = $this->makeCustomer();
        $occ = $this->makeOccurrence($customer->id);
        $v   = $this->makeVersion($occ);
        $this->addDelivery($occ);

        $result = $this->service->accept($v, 1);

        $this->assertTrue($result['review_needed']);
        $this->assertContains('Das Angebot enthält keine Positionen.', $result['errors']);
    }

    // Case 4: all items are free-text without article
    public function test_review_when_all_items_free_text(): void
    {
        $customer = $this->makeCustomer();
        $occ = $this->makeOccurrence($customer->id);
        $v   = $this->makeVersion($occ);
        $this->addItem($v, freeText: true, hasArticle: false);
        $this->addDelivery($occ);

        $result = $this->service->accept($v, 1);

        $this->assertTrue($result['review_needed']);
    }

    // Case 5: no net_total_milli
    public function test_review_when_net_total_missing(): void
    {
        $customer = $this->makeCustomer();
        $occ = $this->makeOccurrence($customer->id);
        $v   = $this->makeVersion($occ, null);
        $this->addItem($v);
        $this->addDelivery($occ);

        $result = $this->service->accept($v, 1);

        $this->assertTrue($result['review_needed']);
        $this->assertContains('Kein Nettobetrag hinterlegt.', $result['errors']);
    }

    // Case 6: no delivery appointment
    public function test_review_when_no_delivery_appointment(): void
    {
        $customer = $this->makeCustomer();
        $occ = $this->makeOccurrence($customer->id);
        $v   = $this->makeVersion($occ);
        $this->addItem($v);
        // No delivery added

        $result = $this->service->accept($v, 1);

        $this->assertTrue($result['review_needed']);
        $this->assertContains('Kein Liefertermin hinterlegt.', $result['errors']);
    }

    // Case 7: rental conflict
    public function test_review_when_rental_conflict_exists(): void
    {
        $customer = $this->makeCustomer();
        $occ = $this->makeOccurrence($customer->id);
        $v   = $this->makeVersion($occ);
        $this->addItem($v);
        $this->addDelivery($occ);

        EventRentalReservation::create([
            'event_occurrence_id' => $occ->id,
            'article_id'          => 999,
            'quantity'            => 5,
            'reservation_type'    => 'soft',
            'reservation_status'  => 'conflict',
            'date_from'           => now()->addDays(20)->toDateString(),
            'date_to'             => now()->addDays(25)->toDateString(),
        ]);

        $result = $this->service->accept($v, 1);

        $this->assertTrue($result['review_needed']);
        $this->assertContains('Festbedarf-Konflikt bei Reservierungen (reservation_status=conflict).', $result['errors']);
    }

    // Case 8: success (no review needed when everything is valid)
    public function test_no_review_when_all_data_valid(): void
    {
        $customer = $this->makeCustomer();
        $occ = $this->makeOccurrence($customer->id);
        $v   = $this->makeVersion($occ);
        $this->addItem($v);
        $this->addDelivery($occ);

        $result = $this->service->accept($v, 1);

        $this->assertFalse($result['review_needed']);
        $this->assertTrue($result['success']);
    }
}
