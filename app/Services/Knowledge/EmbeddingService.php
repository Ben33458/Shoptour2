<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('knowledge.ollama_url', 'http://ollama:11434'), '/');
        $this->model   = (string) config('knowledge.ollama_embedding_model', 'nomic-embed-text');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    /** Embed a single text. Returns float[] or null on failure. */
    public function embed(string $text): ?array
    {
        $results = $this->embedBatch([$text]);
        return $results[0] ?? null;
    }

    /** Embed multiple texts. Returns array of vectors. */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        try {
            // Ollama native /api/embed — supports multiple inputs
            $response = Http::timeout(120)
                ->post("{$this->baseUrl}/api/embed", [
                    'model' => $this->model,
                    'input' => array_values($texts),
                ]);

            if (!$response->successful()) {
                Log::error('Knowledge: Ollama embed error ' . $response->status() . ': ' . $response->body());
                return [];
            }

            return $response->json('embeddings', []);
        } catch (\Throwable $e) {
            Log::error('Knowledge: Embedding request failed: ' . $e->getMessage());
            return [];
        }
    }

    public function testConnection(): bool
    {
        $result = $this->embed('test');
        return $result !== null && count($result) > 0;
    }
}
