<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Models\Events\EventImportLink;
use App\Models\Events\EventOccurrence;
use App\Services\Events\NinoxEventImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PROJ-38: Tests for EventImportLink creation and duplicate prevention
 */
class ImportLinkingTest extends TestCase
{
    use RefreshDatabase;

    private NinoxEventImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NinoxEventImportService();
    }

    private function seedNinoxBestellannahme(int $ninoxId, string $status = 'Angebot schreiben'): void
    {
        DB::table('ninox_bestellannahme')->insertOrIgnore([
            'ninox_id'               => $ninoxId,
            'status'                 => $status,
            'datum_der_veranstaltung'=> now()->addDays(60)->toDateString(),
            'anzahl_gaeste'          => 100,
        ]);
    }

    public function test_import_creates_import_link(): void
    {
        $this->seedNinoxBestellannahme(9001);

        $occurrence = $this->service->importEventOccurrence(9001);

        $this->assertNotNull($occurrence);
        $this->assertDatabaseHas('event_import_links', [
            'entity_type'      => EventOccurrence::class,
            'entity_id'        => $occurrence->id,
            'source_system'    => 'ninox',
            'source_record_id' => '9001',
        ]);
    }

    public function test_second_import_of_same_ninox_id_returns_null(): void
    {
        $this->seedNinoxBestellannahme(9002);

        $first  = $this->service->importEventOccurrence(9002);
        $second = $this->service->importEventOccurrence(9002);

        $this->assertNotNull($first);
        $this->assertNull($second);
    }

    public function test_no_duplicate_occurrences_created(): void
    {
        $this->seedNinoxBestellannahme(9003);

        $this->service->importEventOccurrence(9003);
        $this->service->importEventOccurrence(9003);

        $this->assertEquals(1, EventOccurrence::where('source_record_id', '9003')->count());
    }

    public function test_no_duplicate_import_links_created(): void
    {
        $this->seedNinoxBestellannahme(9004);

        $this->service->importEventOccurrence(9004);
        $this->service->importEventOccurrence(9004);

        $this->assertEquals(
            1,
            EventImportLink::where('source_system', 'ninox')
                ->where('source_record_id', '9004')
                ->count()
        );
    }

    public function test_throws_for_nonexistent_ninox_id(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->importEventOccurrence(99999999);
    }
}
