<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QdrantService
{
    private string $baseUrl;
    private string $apiKey;
    private string $collection;
    private const VECTOR_SIZE = 768; // nomic-embed-text

    public function __construct()
    {
        $this->baseUrl    = rtrim((string) config('knowledge.qdrant_url'), '/');
        $this->apiKey     = (string) config('knowledge.qdrant_api_key');
        $this->collection = (string) config('knowledge.qdrant_collection', 'kolabri_knowledge');
    }

    /** Ensure the collection exists with the correct vector config. */
    public function ensureCollection(): bool
    {
        $response = $this->get("/collections/{$this->collection}");

        if ($response !== null) {
            return true; // already exists
        }

        $result = $this->put("/collections/{$this->collection}", [
            'vectors' => [
                'size'     => self::VECTOR_SIZE,
                'distance' => 'Cosine',
            ],
        ]);

        if ($result !== null) {
            Log::info("Knowledge: Qdrant collection '{$this->collection}' created.");
            return true;
        }

        Log::error("Knowledge: Failed to create Qdrant collection '{$this->collection}'.");
        return false;
    }

    /** Upsert a batch of points. Each point: ['id' => uuid, 'vector' => [], 'payload' => []]. */
    public function upsertPoints(array $points): bool
    {
        if (empty($points)) {
            return true;
        }

        $result = $this->put("/collections/{$this->collection}/points", [
            'points' => $points,
        ]);

        return $result !== null;
    }

    /** Delete points by IDs (UUIDs). */
    public function deletePoints(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        $result = $this->post("/collections/{$this->collection}/points/delete", [
            'points' => $ids,
        ]);

        return $result !== null;
    }

    /** Delete all points matching a filter (e.g., by document_id). */
    public function deleteByFilter(string $field, mixed $value): bool
    {
        $result = $this->post("/collections/{$this->collection}/points/delete", [
            'filter' => [
                'must' => [[
                    'key'   => $field,
                    'match' => ['value' => $value],
                ]],
            ],
        ]);

        return $result !== null;
    }

    /**
     * Search for similar vectors.
     *
     * @return array[] List of hits: ['id', 'score', 'payload']
     */
    public function search(array $vector, int $limit = 5, array $filter = []): array
    {
        $body = [
            'vector'       => $vector,
            'limit'        => $limit,
            'with_payload' => true,
        ];

        if (!empty($filter)) {
            $body['filter'] = $filter;
        }

        $result = $this->post("/collections/{$this->collection}/points/search", $body);

        return $result['result'] ?? [];
    }

    /** Count points in the collection. */
    public function count(): int
    {
        $result = $this->post("/collections/{$this->collection}/points/count", [
            'exact' => true,
        ]);

        return (int) ($result['result']['count'] ?? 0);
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders(['api-key' => $this->apiKey])
                ->timeout(10)
                ->get("{$this->baseUrl}/healthz");
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function get(string $path): ?array
    {
        try {
            $response = Http::withHeaders(['api-key' => $this->apiKey])
                ->timeout(10)
                ->get("{$this->baseUrl}{$path}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error("Knowledge: Qdrant GET error for {$path}: " . $e->getMessage());
        }
        return null;
    }

    private function post(string $path, array $body): ?array
    {
        try {
            $response = Http::withHeaders(['api-key' => $this->apiKey])
                ->timeout(30)
                ->post("{$this->baseUrl}{$path}", $body);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Knowledge: Qdrant POST error {$response->status()} for {$path}: " . $response->body());
        } catch (\Throwable $e) {
            Log::error("Knowledge: Qdrant POST error for {$path}: " . $e->getMessage());
        }
        return null;
    }

    private function put(string $path, array $body): ?array
    {
        try {
            $response = Http::withHeaders(['api-key' => $this->apiKey])
                ->timeout(30)
                ->put("{$this->baseUrl}{$path}", $body);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Knowledge: Qdrant PUT error {$response->status()} for {$path}");
        } catch (\Throwable $e) {
            Log::error("Knowledge: Qdrant PUT error for {$path}: " . $e->getMessage());
        }
        return null;
    }
}
