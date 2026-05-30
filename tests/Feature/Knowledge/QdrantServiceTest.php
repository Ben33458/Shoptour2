<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Services\Knowledge\QdrantService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QdrantServiceTest extends TestCase
{
    public function test_upsert_sends_points_to_qdrant(): void
    {
        Http::fake(['*/points' => Http::response(['status' => 'ok', 'result' => ['operation_id' => 1, 'status' => 'completed']])]);

        $service = app(QdrantService::class);
        $result  = $service->upsertPoints([
            ['id' => 'abc-123', 'vector' => array_fill(0, 768, 0.1), 'payload' => ['title' => 'Test']],
        ]);

        $this->assertTrue($result);
        Http::assertSentCount(1);
    }

    public function test_delete_points_by_ids(): void
    {
        Http::fake(['*/points/delete' => Http::response(['status' => 'ok', 'result' => ['operation_id' => 2, 'status' => 'completed']])]);

        $service = app(QdrantService::class);
        $result  = $service->deletePoints(['abc-123', 'def-456']);

        $this->assertTrue($result);
    }

    public function test_delete_empty_list_returns_true(): void
    {
        $service = app(QdrantService::class);
        $result  = $service->deletePoints([]);
        $this->assertTrue($result);
        Http::assertNothingSent();
    }

    public function test_search_returns_hits(): void
    {
        Http::fake([
            '*/points/search' => Http::response([
                'result' => [
                    ['id' => 'abc-123', 'score' => 0.92, 'payload' => ['title' => 'Test Doc']],
                ],
            ]),
        ]);

        $service = app(QdrantService::class);
        $hits    = $service->search(array_fill(0, 768, 0.1), 5);

        $this->assertCount(1, $hits);
        $this->assertEquals(0.92, $hits[0]['score']);
    }

    public function test_reindex_upserts_new_points(): void
    {
        Http::fake([
            '*/points' => Http::response(['status' => 'ok', 'result' => []]),
        ]);

        $service = app(QdrantService::class);
        $result  = $service->upsertPoints([
            ['id' => 'new-id', 'vector' => array_fill(0, 768, 0.5), 'payload' => ['title' => 'Reindex']],
        ]);

        $this->assertTrue($result);
    }
}
