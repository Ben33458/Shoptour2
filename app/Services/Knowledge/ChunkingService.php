<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

class ChunkingService
{
    private const CHUNK_SIZE    = 250;  // words per chunk
    private const CHUNK_OVERLAP = 40;   // words overlap between chunks

    /**
     * Split text into overlapping chunks with metadata attached.
     *
     * @return array[] Each item: ['text' => string, 'chunk_index' => int, 'chunk_hash' => string]
     */
    public function chunk(string $text, array $baseMetadata = []): array
    {
        $text = $this->normalizeText($text);

        if ($text === '') {
            return [];
        }

        $words = explode(' ', $text);
        $total = count($words);

        $chunks = [];
        $index  = 0;
        $start  = 0;

        while ($start < $total) {
            $end       = min($start + self::CHUNK_SIZE, $total);
            $chunkText = implode(' ', array_slice($words, $start, $end - $start));

            $chunks[] = array_merge($baseMetadata, [
                'chunk_index' => $index,
                'chunk_text'  => $chunkText,
                'chunk_hash'  => $this->hash($chunkText),
            ]);

            if ($end >= $total) {
                break;
            }

            $start = $end - self::CHUNK_OVERLAP;
            $index++;
        }

        return $chunks;
    }

    /** SHA-256 hash of the chunk text. */
    public function hash(string $text): string
    {
        return hash('sha256', $this->normalizeText($text));
    }

    /** SHA-256 hash of the full document content. */
    public function contentHash(string $content): string
    {
        return hash('sha256', $this->normalizeText($content));
    }

    private function normalizeText(string $text): string
    {
        // Collapse whitespace, trim
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
