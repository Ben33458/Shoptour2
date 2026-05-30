<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Services\Events\NinoxEventImportService;
use Tests\TestCase;

/**
 * PROJ-38: Tests for NinoxEventImportService::mapNinoxStatus()
 */
class NinoxStatusMappingTest extends TestCase
{
    private NinoxEventImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NinoxEventImportService();
    }

    // ── Plain-text (backwards-compatible) ──────────────────────

    public function test_angebot_schreiben_maps_to_requested_without_review(): void
    {
        $result = $this->service->mapNinoxStatus('Angebot schreiben');

        $this->assertEquals('requested', $result['offer_status']);
        $this->assertFalse($result['needs_review']);
    }

    public function test_angebot_geschrieben_maps_to_sent_without_review(): void
    {
        $result = $this->service->mapNinoxStatus('Angebot geschrieben');

        $this->assertEquals('sent', $result['offer_status']);
        $this->assertFalse($result['needs_review']);
    }

    public function test_angebot_abgelehnt_maps_to_rejected_without_review(): void
    {
        $result = $this->service->mapNinoxStatus('Angebot abgelehnt');

        $this->assertEquals('rejected', $result['offer_status']);
        $this->assertFalse($result['needs_review']);
    }

    // ── Emoji-prefixed (real Ninox production values) ───────────

    public function test_emoji_angebot_schreiben_maps_to_requested(): void
    {
        $result = $this->service->mapNinoxStatus('❣️ Angebot schreiben');

        $this->assertEquals('requested', $result['offer_status']);
        $this->assertFalse($result['needs_review']);
    }

    public function test_emoji_angebot_geschrieben_maps_to_sent(): void
    {
        $result = $this->service->mapNinoxStatus('🕙 Angebot geschrieben');

        $this->assertEquals('sent', $result['offer_status']);
        $this->assertFalse($result['needs_review']);
    }

    public function test_emoji_angebot_abgelehnt_maps_to_rejected(): void
    {
        $result = $this->service->mapNinoxStatus('❌ Angebot abgelehnt');

        $this->assertEquals('rejected', $result['offer_status']);
        $this->assertFalse($result['needs_review']);
    }

    public function test_bestellt_maps_to_converted_to_order(): void
    {
        $result = $this->service->mapNinoxStatus('📄 bestellt');

        $this->assertEquals('converted_to_order', $result['offer_status']);
        $this->assertFalse($result['needs_review']);
    }

    public function test_geliefert_maps_to_converted_to_order(): void
    {
        $result = $this->service->mapNinoxStatus('✅ geliefert');

        $this->assertEquals('converted_to_order', $result['offer_status']);
        $this->assertFalse($result['needs_review']);
    }

    // ── Edge cases ───────────────────────────────────────────────

    public function test_empty_status_maps_to_draft_with_review(): void
    {
        $result = $this->service->mapNinoxStatus('');

        $this->assertEquals('draft', $result['offer_status']);
        $this->assertTrue($result['needs_review']);
    }

    public function test_unknown_status_maps_to_draft_with_review(): void
    {
        $result = $this->service->mapNinoxStatus('Irgendein unbekannter Status');

        $this->assertEquals('draft', $result['offer_status']);
        $this->assertTrue($result['needs_review']);
    }
}
