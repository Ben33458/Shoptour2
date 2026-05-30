<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Services\Knowledge\PaperlessConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaperlessConnectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_fetches_documents_with_ki_freigabe_tag(): void
    {
        Http::fake([
            '*/api/tags/*' => Http::response([
                'results' => [
                    ['id' => 1, 'name' => 'ki-freigabe'],
                    ['id' => 2, 'name' => 'ki-preisliste'],
                    ['id' => 3, 'name' => 'privat'],
                ],
                'next' => null,
            ]),
            '*/api/documents/*' => Http::response([
                'results' => [
                    [
                        'id'            => 42,
                        'title'         => 'Freigegebenes Dokument',
                        'content'       => 'Inhalt des Dokuments',
                        'tags'          => [1],
                        'tags_full'     => [['name' => 'ki-freigabe']],
                        'document_date' => '2026-01-15',
                        'created'       => '2026-01-15T10:00:00Z',
                        'modified'      => '2026-01-15T10:00:00Z',
                    ],
                ],
                'next' => null,
            ]),
        ]);

        $connector = app(PaperlessConnector::class);
        $docs = iterator_to_array($connector->fetchApprovedDocuments(), false);

        $this->assertCount(1, $docs);
        $this->assertEquals('42', $docs[0]['external_id']);
        $this->assertEquals('Freigegebenes Dokument', $docs[0]['title']);
    }

    public function test_api_connection_error_is_logged_gracefully(): void
    {
        Http::fake([
            '*/api/tags/*'      => Http::response(null, 503),
            '*/api/documents/*' => Http::response(null, 503),
        ]);

        $connector = app(PaperlessConnector::class);
        $docs = iterator_to_array($connector->fetchApprovedDocuments(), false);

        // Should return empty, not throw
        $this->assertIsArray($docs);
    }

    public function test_is_price_list_flag_set_from_tag(): void
    {
        Http::fake([
            '*/api/tags/*' => Http::response([
                'results' => [
                    ['id' => 1, 'name' => 'ki-freigabe'],
                    ['id' => 2, 'name' => 'ki-preisliste'],
                ],
                'next' => null,
            ]),
            '*/api/documents/*' => Http::response([
                'results' => [[
                    'id'       => 10,
                    'title'    => 'Preisliste Q1',
                    'content'  => 'Preise...',
                    'tags'     => [1, 2],
                    'tags_full'=> [['name' => 'ki-freigabe'], ['name' => 'ki-preisliste']],
                    'document_date' => null,
                    'created'  => '2026-01-01T00:00:00Z',
                    'modified' => '2026-01-01T00:00:00Z',
                ]],
                'next' => null,
            ]),
        ]);

        $connector = app(PaperlessConnector::class);
        $docs = iterator_to_array($connector->fetchApprovedDocuments(), false);

        $this->assertTrue($docs[0]['is_price_list']);
        $this->assertFalse($docs[0]['is_lmiv']);
    }
}
