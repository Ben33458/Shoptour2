<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\Knowledge\KnowledgeChunk;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\Knowledge\KnowledgeSyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IndexingService
{
    public function __construct(
        private readonly QdrantService   $qdrant,
        private readonly EmbeddingService $embedding,
        private readonly ChunkingService  $chunking,
    ) {}

    /**
     * Index (or re-index) a document.
     * Returns ['created' => int, 'updated' => int, 'skipped' => int].
     */
    public function indexDocument(KnowledgeDocument $document, string $content): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $contentHash = $this->chunking->contentHash($content);

        // Skip unchanged documents (delta sync)
        if ($document->indexed && $document->content_hash === $contentHash) {
            $stats['skipped'] = 1;
            return $stats;
        }

        // Remove old chunks from Qdrant
        $this->removeDocumentFromQdrant($document);

        // Build chunk metadata base
        $baseMeta = [
            'tenant_id'         => $document->tenant_id,
            'source_type'       => $document->source_type,
            'source_id'         => $document->source_id,
            'source_document_id'=> $document->external_id,
            'title'             => $document->title,
            'url'               => $document->url,
            'tags'              => $document->tags ?? [],
            'visibility'        => $document->visibility,
            'permissions'       => $document->permissions ?? [],
            'document_date'     => $document->document_date?->toDateString(),
            'valid_from'        => $document->valid_from?->toDateString(),
            'valid_until'       => $document->valid_until?->toDateString(),
            'is_price_list'     => $document->is_price_list,
            'is_lmiv'           => $document->is_lmiv,
            'is_technical'      => $document->is_technical,
            'source_category'   => $document->source_category,
            'indexed_at'        => now()->toIso8601String(),
        ];

        $rawChunks = $this->chunking->chunk($content, $baseMeta);
        if (empty($rawChunks)) {
            Log::warning("Knowledge: Document #{$document->id} produced no chunks.");
            return $stats;
        }

        // Embed in batches of 50
        $batchSize = 50;
        $qdrantPoints = [];
        $chunkRecords = [];

        foreach (array_chunk($rawChunks, $batchSize) as $batch) {
            $texts    = array_column($batch, 'chunk_text');
            $vectors  = $this->embedding->embedBatch($texts);

            if (count($vectors) !== count($batch)) {
                Log::error("Knowledge: Embedding batch size mismatch for doc #{$document->id}.");
                continue;
            }

            foreach ($batch as $i => $chunk) {
                $pointId = (string) Str::uuid();

                $qdrantPoints[] = [
                    'id'      => $pointId,
                    'vector'  => $vectors[$i],
                    'payload' => array_merge($chunk, ['source_chunk_id' => $pointId]),
                ];

                $chunkRecords[] = [
                    'tenant_id'       => $document->tenant_id,
                    'document_id'     => $document->id,
                    'qdrant_point_id' => $pointId,
                    'chunk_index'     => $chunk['chunk_index'],
                    'chunk_text'      => $chunk['chunk_text'],
                    'chunk_hash'      => $chunk['chunk_hash'],
                    'metadata'        => json_encode($baseMeta),
                    'indexed_at'      => now(),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }
        }

        if (empty($qdrantPoints)) {
            return $stats;
        }

        // Upsert to Qdrant
        $success = $this->qdrant->upsertPoints($qdrantPoints);

        if (!$success) {
            Log::error("Knowledge: Qdrant upsert failed for doc #{$document->id}.");
            return $stats;
        }

        // Save chunk records to DB
        DB::transaction(function () use ($document, $chunkRecords, $contentHash, &$stats) {
            KnowledgeChunk::where('document_id', $document->id)->delete();
            KnowledgeChunk::insert($chunkRecords);

            $wasIndexed = $document->indexed;
            $document->update([
                'content_hash' => $contentHash,
                'indexed'      => true,
                'indexed_at'   => now(),
            ]);

            if ($wasIndexed) {
                $stats['updated'] = 1;
            } else {
                $stats['created'] = 1;
            }
        });

        return $stats;
    }

    /** Remove all Qdrant points and DB chunks for a document. */
    public function removeDocumentFromQdrant(KnowledgeDocument $document): void
    {
        $pointIds = KnowledgeChunk::where('document_id', $document->id)
            ->pluck('qdrant_point_id')
            ->toArray();

        if (!empty($pointIds)) {
            $this->qdrant->deletePoints($pointIds);
        }

        KnowledgeChunk::where('document_id', $document->id)->delete();

        $document->update(['indexed' => false, 'indexed_at' => null]);
    }
}
