<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Models\Events\OfferNumberSequence;
use App\Services\Events\OfferNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PROJ-38: Tests for OfferNumberService
 */
class OfferNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    private OfferNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OfferNumberService();
    }

    public function test_generates_correct_format(): void
    {
        $number = $this->service->generate(2026);

        $this->assertEquals('ST-A-2026-000001', $number);
    }

    public function test_increments_on_second_call(): void
    {
        $first  = $this->service->generate(2026);
        $second = $this->service->generate(2026);

        $this->assertEquals('ST-A-2026-000001', $first);
        $this->assertEquals('ST-A-2026-000002', $second);
    }

    public function test_year_change_resets_sequence(): void
    {
        $y2026 = $this->service->generate(2026);
        $y2027 = $this->service->generate(2027);

        $this->assertEquals('ST-A-2026-000001', $y2026);
        $this->assertEquals('ST-A-2027-000001', $y2027);
    }

    public function test_sequence_is_six_digits_padded(): void
    {
        // Fill to 10 to test padding
        for ($i = 0; $i < 10; $i++) {
            $this->service->generate(2026);
        }

        $eleventh = $this->service->generate(2026);

        $this->assertEquals('ST-A-2026-000011', $eleventh);
    }

    public function test_uses_current_year_by_default(): void
    {
        $number = $this->service->generate();

        $year = now()->year;
        $this->assertStringStartsWith("ST-A-{$year}-", $number);
    }

    public function test_creates_sequence_row_if_not_exists(): void
    {
        $this->assertDatabaseMissing('offer_number_sequences', ['year' => 2025]);

        $this->service->generate(2025);

        $this->assertDatabaseHas('offer_number_sequences', [
            'year'          => 2025,
            'last_sequence' => 1,
        ]);
    }
}
