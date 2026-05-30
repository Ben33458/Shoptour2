<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Services\Knowledge\ChunkingService;
use Tests\TestCase;

class ChunkingServiceTest extends TestCase
{
    private ChunkingService $chunker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chunker = new ChunkingService();
    }

    public function test_short_text_produces_single_chunk(): void
    {
        $text   = 'Dies ist ein kurzer Text.';
        $chunks = $this->chunker->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertEquals(0, $chunks[0]['chunk_index']);
        $this->assertStringContainsString('kurzer Text', $chunks[0]['chunk_text']);
    }

    public function test_long_text_is_split_into_multiple_chunks(): void
    {
        $words = implode(' ', array_fill(0, 900, 'wort'));
        $chunks = $this->chunker->chunk($words);

        $this->assertGreaterThan(1, count($chunks));
    }

    public function test_chunk_hash_is_deterministic(): void
    {
        $text  = 'Gleicher Text für Hash-Test';
        $hash1 = $this->chunker->hash($text);
        $hash2 = $this->chunker->hash($text);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA-256 hex
    }

    public function test_unchanged_chunk_produces_same_hash(): void
    {
        $text   = 'Unveränderter Inhalt';
        $chunks = $this->chunker->chunk($text);
        $hash   = $chunks[0]['chunk_hash'];

        $chunks2 = $this->chunker->chunk($text);
        $this->assertEquals($hash, $chunks2[0]['chunk_hash']);
    }

    public function test_empty_text_returns_no_chunks(): void
    {
        $this->assertEmpty($this->chunker->chunk(''));
        $this->assertEmpty($this->chunker->chunk('   '));
    }

    public function test_content_hash_differs_for_different_content(): void
    {
        $hash1 = $this->chunker->contentHash('Inhalt A');
        $hash2 = $this->chunker->contentHash('Inhalt B');

        $this->assertNotEquals($hash1, $hash2);
    }
}
