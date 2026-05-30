<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaperlessConnector
{
    private string $baseUrl;
    private string $token;
    private const TAG_KI_FREIGABE = 'ki-freigabe';
    private const REQUIRED_TAGS = [
        'ki-freigabe', 'ki-mitarbeiter', 'ki-admin',
        'ki-preisliste', 'ki-lmiv', 'ki-technik',
    ];

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('knowledge.paperless_url'), '/');
        $this->token   = (string) config('knowledge.paperless_api_token');
    }

    /** Ensure required tags exist in Paperless; create missing ones. */
    public function ensureTags(): array
    {
        $existing = $this->fetchAllTags();
        $existingNames = array_column($existing, 'name');
        $created = [];

        foreach (self::REQUIRED_TAGS as $tagName) {
            if (!in_array($tagName, $existingNames, true)) {
                $tag = $this->createTag($tagName);
                if ($tag) {
                    $created[] = $tagName;
                    Log::info("Knowledge: Paperless tag created: {$tagName}");
                }
            }
        }

        return $created;
    }

    /** Fetch all documents tagged with ki-freigabe, paginated. */
    public function fetchApprovedDocuments(?\DateTimeInterface $updatedAfter = null): \Generator
    {
        $kiFreigabeId = $this->getTagId(self::TAG_KI_FREIGABE);
        if ($kiFreigabeId === null) {
            Log::warning('Knowledge: Paperless tag ki-freigabe not found, creating...');
            $this->ensureTags();
            $kiFreigabeId = $this->getTagId(self::TAG_KI_FREIGABE);
        }

        $page = 1;
        do {
            $params = [
                'page'       => $page,
                'page_size'  => 50,
                'tags__id__all' => $kiFreigabeId,
            ];
            if ($updatedAfter) {
                $params['modified__date__gte'] = $updatedAfter->format('Y-m-d');
            }

            $response = $this->get('/api/documents/', $params);
            if (!$response) {
                break;
            }

            foreach ($response['results'] ?? [] as $doc) {
                yield $this->normalizeDocument($doc);
            }

            $hasNext = !empty($response['next']);
            $page++;
        } while ($hasNext);
    }

    /** Fetch all tags from Paperless. */
    public function fetchAllTags(): array
    {
        $tags = [];
        $page = 1;
        do {
            $response = $this->get('/api/tags/', ['page' => $page, 'page_size' => 100]);
            if (!$response) {
                break;
            }
            $tags = array_merge($tags, $response['results'] ?? []);
            $hasNext = !empty($response['next']);
            $page++;
        } while ($hasNext);
        return $tags;
    }

    /** Return the full OCR text for a document. */
    public function getDocumentContent(int $documentId): string
    {
        $response = $this->get("/api/documents/{$documentId}/");
        return $response['content'] ?? '';
    }

    /** Return the internal preview URL for a document. */
    public function getDocumentPreviewUrl(int $documentId): string
    {
        return "{$this->baseUrl}/api/documents/{$documentId}/preview/";
    }

    private function normalizeDocument(array $raw): array
    {
        $tags = $raw['tags'] ?? [];

        return [
            'external_id'      => (string) $raw['id'],
            'title'            => $raw['title'] ?? '',
            'content'          => $raw['content'] ?? '',
            'url'              => "{$this->baseUrl}/documents/{$raw['id']}/details/",
            'tags'             => $tags,
            'tag_names'        => array_column($raw['tags_full'] ?? [], 'name'),
            'correspondent'    => $raw['correspondent'] ?? null,
            'document_type'    => $raw['document_type'] ?? null,
            'document_date'    => $raw['document_date'] ?? null,
            'created'          => $raw['created'] ?? null,
            'modified'         => $raw['modified'] ?? null,
            'is_price_list'    => $this->hasTagName($raw, 'ki-preisliste'),
            'is_lmiv'          => $this->hasTagName($raw, 'ki-lmiv'),
            'is_technical'     => $this->hasTagName($raw, 'ki-technik'),
            'metadata'         => [
                'paperless_id'   => $raw['id'],
                'correspondent'  => $raw['correspondent'] ?? null,
                'document_type'  => $raw['document_type'] ?? null,
                'archive_serial' => $raw['archive_serial_number'] ?? null,
                'preview_url'    => $this->getDocumentPreviewUrl($raw['id']),
            ],
        ];
    }

    private function hasTagName(array $doc, string $name): bool
    {
        $tagNames = array_column($doc['tags_full'] ?? [], 'name');
        return in_array($name, $tagNames, true);
    }

    private function getTagId(string $name): ?int
    {
        $tags = $this->fetchAllTags();
        foreach ($tags as $tag) {
            if ($tag['name'] === $name) {
                return (int) $tag['id'];
            }
        }
        return null;
    }

    private function createTag(string $name): ?array
    {
        try {
            $response = Http::withHeaders(['Authorization' => 'Token ' . $this->token])
                ->post("{$this->baseUrl}/api/tags/", ['name' => $name]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error("Knowledge: Failed to create Paperless tag '{$name}': " . $e->getMessage());
        }
        return null;
    }

    private function get(string $path, array $params = []): ?array
    {
        try {
            $response = Http::withHeaders(['Authorization' => 'Token ' . $this->token])
                ->timeout(30)
                ->get("{$this->baseUrl}{$path}", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Knowledge: Paperless API error {$response->status()} for {$path}");
        } catch (\Throwable $e) {
            Log::error("Knowledge: Paperless connection error for {$path}: " . $e->getMessage());
        }
        return null;
    }
}
