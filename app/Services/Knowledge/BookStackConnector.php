<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookStackConnector
{
    private string $baseUrl;
    private string $tokenId;
    private string $tokenSecret;

    public function __construct()
    {
        $this->baseUrl      = rtrim((string) config('knowledge.bookstack_url'), '/');
        $this->tokenId      = (string) config('knowledge.bookstack_token_id');
        $this->tokenSecret  = (string) config('knowledge.bookstack_token_secret');
    }

    public function isConfigured(): bool
    {
        return $this->tokenId !== '' && $this->tokenSecret !== '';
    }

    /** Fetch all published pages, paginated. */
    public function fetchPages(?\DateTimeInterface $updatedAfter = null): \Generator
    {
        $offset = 0;
        $count  = 50;

        do {
            $params = ['count' => $count, 'offset' => $offset];
            if ($updatedAfter) {
                $params['filter[updated_at:gte]'] = $updatedAfter->format('Y-m-d');
            }

            $response = $this->get('/api/pages', $params);
            if (!$response) {
                break;
            }

            foreach ($response['data'] ?? [] as $page) {
                $detail = $this->get("/api/pages/{$page['id']}");
                if ($detail) {
                    // List endpoint has book_slug/book_name; detail endpoint does not — merge them in
                    yield $this->normalizePage(array_merge($detail, [
                        'book_slug'  => $page['book_slug'] ?? '',
                        'book_name'  => $page['book_name'] ?? '',
                        'chapter_name' => $page['chapter_name'] ?? null,
                    ]));
                }
            }

            $total  = $response['total'] ?? 0;
            $offset += $count;
        } while ($offset < $total);
    }

    /** Create a page as draft in the Entwürfe book (auto-creates the book if missing). */
    public function createDraftPage(string $title, string $markdownContent): ?array
    {
        $bookId = $this->findOrCreateBook('Entwürfe');
        if (!$bookId) {
            Log::warning('Knowledge: Could not find or create Entwürfe book for draft creation.');
            return null;
        }

        try {
            $response = $this->post('/api/pages', [
                'book_id'  => $bookId,
                'name'     => $title,
                'markdown' => $markdownContent,
                'draft'    => true,
            ]);

            if ($response) {
                Log::info("Knowledge: BookStack draft created: {$title} (page #{$response['id']})");
                return $response;
            }
        } catch (\Throwable $e) {
            Log::error("Knowledge: BookStack draft creation failed: " . $e->getMessage());
        }

        return null;
    }

    /** Find book by name, or create it. Returns book ID or null. */
    public function findOrCreateBook(string $name): ?int
    {
        $response = $this->get('/api/books', ['count' => 100]);
        foreach ($response['data'] ?? [] as $book) {
            if (strtolower($book['name']) === strtolower($name)) {
                return (int) $book['id'];
            }
        }

        // Create the book
        $result = $this->post('/api/books', ['name' => $name, 'description' => '']);
        return $result ? (int) $result['id'] : null;
    }

    /** Find chapter by name in a book, or create it. Returns chapter ID or null. */
    public function findOrCreateChapter(int $bookId, string $name): ?int
    {
        $response = $this->get('/api/chapters', ['count' => 200]);
        foreach ($response['data'] ?? [] as $chapter) {
            if ($chapter['book_id'] === $bookId && strtolower($chapter['name']) === strtolower($name)) {
                return (int) $chapter['id'];
            }
        }

        $result = $this->post('/api/chapters', ['book_id' => $bookId, 'name' => $name, 'description' => '']);
        return $result ? (int) $result['id'] : null;
    }

    /** Create a published page in a book (optionally in a chapter). Returns page ID or null. */
    public function createPage(int $bookId, string $title, string $content, ?int $chapterId = null): ?int
    {
        $html = '<p>' . implode('</p><p>', array_map(
            fn($l) => e(trim($l)),
            array_filter(explode("\n", $content), fn($l) => trim($l) !== '')
        )) . '</p>';

        $payload = [
            'book_id' => $bookId,
            'name'    => $title,
            'html'    => $html,
            'draft'   => false,
        ];

        if ($chapterId) {
            $payload['chapter_id'] = $chapterId;
        }

        $result = $this->post('/api/pages', $payload);
        return $result ? (int) $result['id'] : null;
    }

    /** Get the plain text content of a page. */
    public function getPageText(int $pageId): string
    {
        $detail = $this->get("/api/pages/{$pageId}");
        if (!$detail) {
            return '';
        }
        $text = strip_tags($detail['html'] ?? '') . "\n" . ($detail['markdown'] ?? '');
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    /** Delete a page permanently. */
    public function deletePage(int $pageId): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withToken("{$this->tokenId}:{$this->tokenSecret}", 'Token')
                ->timeout(30)
                ->delete("{$this->baseUrl}/api/pages/{$pageId}");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Knowledge: BookStack deletePage failed for page #{$pageId}: " . $e->getMessage());
            return false;
        }
    }

    /** Create a page with Markdown content (stored as HTML). */
    public function createMarkdownPage(int $bookId, string $title, string $markdown): ?int
    {
        $result = $this->post('/api/pages', [
            'book_id'  => $bookId,
            'name'     => $title,
            'markdown' => $markdown,
            'draft'    => false,
        ]);
        return $result ? (int) $result['id'] : null;
    }

    /** Move a page to a different book. */
    public function movePage(int $pageId, int $targetBookId): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withToken("{$this->tokenId}:{$this->tokenSecret}", 'Token')
                ->timeout(30)
                ->put("{$this->baseUrl}/api/pages/{$pageId}", ['book_id' => $targetBookId]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Knowledge: BookStack movePage failed for page #{$pageId}: " . $e->getMessage());
            return false;
        }
    }

    /** List all pages (paginated). Returns array of lightweight page objects from list endpoint. */
    public function listAllPages(): array
    {
        $pages  = [];
        $offset = 0;
        $count  = 100;

        do {
            $response = $this->get('/api/pages', ['count' => $count, 'offset' => $offset]);
            if (!$response) {
                break;
            }
            foreach ($response['data'] ?? [] as $page) {
                $pages[] = $page;
            }
            $total   = $response['total'] ?? 0;
            $offset += $count;
        } while ($offset < $total);

        return $pages;
    }

    /** Test API connectivity. */
    public function testConnection(): bool
    {
        $response = $this->get('/api/books', ['count' => 1]);
        return $response !== null;
    }

    private function normalizePage(array $raw): array
    {
        return [
            'external_id'     => (string) $raw['id'],
            'title'           => $raw['name'] ?? '',
            'content'         => strip_tags($raw['html'] ?? '') . "\n" . ($raw['markdown'] ?? ''),
            'url'             => "{$this->baseUrl}/books/{$raw['book_slug']}/page/{$raw['slug']}",
            'book_id'         => $raw['book_id'] ?? null,
            'book_name'       => $raw['book_name'] ?? null,
            'chapter_id'      => $raw['chapter_id'] ?? null,
            'chapter_name'    => $raw['chapter_name'] ?? null,
            'updated_at'      => $raw['updated_at'] ?? null,
            'tags'            => array_column($raw['tags'] ?? [], 'value'),
            'metadata'        => [
                'bookstack_id'   => $raw['id'],
                'book_slug'      => $raw['book_slug'] ?? null,
                'page_slug'      => $raw['slug'] ?? null,
                'book_name'      => $raw['book_name'] ?? null,
                'chapter_name'   => $raw['chapter_name'] ?? null,
            ],
        ];
    }

    /** Full-text search in BookStack. Returns array of ['id' => string, 'title' => string]. */
    public function searchPages(string $query, int $count = 5): array
    {
        $response = $this->get('/api/search', ['query' => $query, 'count' => $count]);
        $pages = [];
        foreach ($response['data'] ?? [] as $item) {
            if (($item['type'] ?? '') === 'page') {
                $pages[] = ['id' => (string) $item['id'], 'title' => $item['name'] ?? ''];
            }
        }
        return $pages;
    }

    private function findBookBySlug(string $slug): ?array
    {
        $response = $this->get('/api/books', ['filter[slug]' => $slug]);
        return $response['data'][0] ?? null;
    }

    private function markdownToHtml(string $markdown): string
    {
        // Minimal conversion: wrap in paragraph tags, keep headings
        $html = nl2br(e($markdown));
        return "<p>{$html}</p>";
    }

    private function get(string $path, array $params = []): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withToken("{$this->tokenId}:{$this->tokenSecret}", 'Token')
                ->timeout(30)
                ->get("{$this->baseUrl}{$path}", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Knowledge: BookStack API error {$response->status()} for {$path}");
        } catch (\Throwable $e) {
            Log::error("Knowledge: BookStack connection error for {$path}: " . $e->getMessage());
        }
        return null;
    }

    private function post(string $path, array $data): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withToken("{$this->tokenId}:{$this->tokenSecret}", 'Token')
                ->timeout(30)
                ->post("{$this->baseUrl}{$path}", $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Knowledge: BookStack POST error {$response->status()} for {$path}");
        } catch (\Throwable $e) {
            Log::error("Knowledge: BookStack POST error for {$path}: " . $e->getMessage());
        }
        return null;
    }
}
