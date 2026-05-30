<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Models\Events\EventOccurrence;
use App\Models\Events\EventSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PROJ-38: Tests for EventSeries / previous-year linking
 */
class PreviousYearLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_occurrence_is_linked_to_series(): void
    {
        $series = EventSeries::create([
            'name'       => 'Kerb Musterstadt',
            'event_type' => 'kerb',
        ]);

        $occ = EventOccurrence::create([
            'title'           => 'Kerb 2026',
            'event_type'      => 'kerb',
            'event_series_id' => $series->id,
            'event_year'      => 2026,
            'offer_status'    => 'sent',
            'event_status'    => 'planned',
        ]);

        $this->assertEquals($series->id, $occ->event_series_id);
        $this->assertTrue($occ->series->is($series));
    }

    public function test_previous_year_occurrence_is_retrievable(): void
    {
        $series = EventSeries::create([
            'name'       => 'Kerb Musterstadt',
            'event_type' => 'kerb',
        ]);

        $occ2025 = EventOccurrence::create([
            'title'           => 'Kerb 2025',
            'event_type'      => 'kerb',
            'event_series_id' => $series->id,
            'event_year'      => 2025,
            'event_status'    => 'closed',
            'offer_status'    => 'converted_to_order',
        ]);

        $occ2026 = EventOccurrence::create([
            'title'           => 'Kerb 2026',
            'event_type'      => 'kerb',
            'event_series_id' => $series->id,
            'event_year'      => 2026,
            'event_status'    => 'planned',
            'offer_status'    => 'sent',
        ]);

        $previousYear = EventOccurrence::where('event_series_id', $occ2026->event_series_id)
            ->where('event_year', $occ2026->event_year - 1)
            ->first();

        $this->assertNotNull($previousYear);
        $this->assertTrue($previousYear->is($occ2025));
    }

    public function test_series_occurrences_are_ordered_by_year(): void
    {
        $series = EventSeries::create([
            'name'       => 'Sommerfest',
            'event_type' => 'sommerfest',
        ]);

        $years = [2024, 2026, 2025];
        foreach ($years as $year) {
            EventOccurrence::create([
                'title'           => "Fest {$year}",
                'event_type'      => 'sommerfest',
                'event_series_id' => $series->id,
                'event_year'      => $year,
                'event_status'    => 'planned',
                'offer_status'    => 'draft',
            ]);
        }

        $seriesOccs = $series->occurrences()->orderBy('event_year')->get();

        $this->assertEquals([2024, 2025, 2026], $seriesOccs->pluck('event_year')->toArray());
    }

    public function test_new_occurrence_can_be_created_from_previous_year(): void
    {
        $series = EventSeries::create([
            'name'       => 'Vereinsfest',
            'event_type' => 'vereinsfest',
        ]);

        $occ2025 = EventOccurrence::create([
            'title'               => 'Vereinsfest 2025',
            'event_type'          => 'vereinsfest',
            'event_series_id'     => $series->id,
            'event_year'          => 2025,
            'expected_guests'     => 200,
            'location_name'       => 'Festplatz Musterstadt',
            'event_status'        => 'closed',
            'offer_status'        => 'converted_to_order',
        ]);

        // Copy key fields to new year
        $occ2026 = EventOccurrence::create([
            'title'           => str_replace('2025', '2026', $occ2025->title),
            'event_type'      => $occ2025->event_type,
            'event_series_id' => $occ2025->event_series_id,
            'event_year'      => 2026,
            'expected_guests' => $occ2025->expected_guests,
            'location_name'   => $occ2025->location_name,
            'event_status'    => 'planned',
            'offer_status'    => 'requested',
        ]);

        $this->assertEquals($series->id, $occ2026->event_series_id);
        $this->assertEquals(2026, $occ2026->event_year);
        $this->assertEquals('Vereinsfest 2026', $occ2026->title);
        $this->assertEquals('Festplatz Musterstadt', $occ2026->location_name);
    }
}
