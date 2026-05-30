<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\Knowledge\KnowledgeSyncRun;
use App\Services\Knowledge\BookStackConnector;
use App\Services\Knowledge\IndexingService;
use App\Services\Knowledge\PaperlessConnector;
use App\Services\Knowledge\QdrantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class KnowledgeSyncCommand extends Command
{
    protected $signature = 'knowledge:sync {--source=all : paperless, bookstack, shoptour2, or all}';
    protected $description = 'Sync knowledge sources into Qdrant index';

    public function __construct(
        private readonly PaperlessConnector $paperless,
        private readonly BookStackConnector $bookstack,
        private readonly IndexingService    $indexing,
        private readonly QdrantService      $qdrant,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('knowledge.enabled')) {
            $this->warn('Knowledge module is disabled (KNOWLEDGE_ENABLED=false).');
            return self::FAILURE;
        }

        $source = $this->option('source');
        $this->info("Knowledge sync started — source: {$source}");

        if (!$this->qdrant->ensureCollection()) {
            $this->error('Qdrant collection could not be created. Aborting.');
            return self::FAILURE;
        }

        if (in_array($source, ['all', 'paperless'], true)) {
            $this->syncPaperless();
        }

        if (in_array($source, ['all', 'bookstack'], true)) {
            $this->syncBookStack();
        }

        $this->info('Knowledge sync finished.');
        return self::SUCCESS;
    }

    private function syncPaperless(): void
    {
        $this->info('Syncing Paperless (ki-freigabe only)...');

        $run = KnowledgeSyncRun::create([
            'tenant_id'  => config('knowledge.tenant_id'),
            'source'     => 'paperless',
            'status'     => KnowledgeSyncRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $source = KnowledgeSource::firstOrCreate(
            ['source_type' => KnowledgeSource::TYPE_PAPERLESS, 'tenant_id' => config('knowledge.tenant_id')],
            ['name' => 'Paperless-ngx', 'base_url' => config('knowledge.paperless_url'), 'enabled' => true],
        );

        $errors = 0;
        $seen = $indexed = $created = $updated = $deleted = 0;

        try {
            // Ensure required tags exist
            $this->paperless->ensureTags();

            // Track currently approved document IDs
            $approvedExternalIds = [];

            foreach ($this->paperless->fetchApprovedDocuments() as $doc) {
                $seen++;
                $approvedExternalIds[] = $doc['external_id'];

                try {
                    $document = KnowledgeDocument::updateOrCreate(
                        ['source_id' => $source->id, 'external_id' => $doc['external_id']],
                        [
                            'tenant_id'          => config('knowledge.tenant_id'),
                            'source_type'        => KnowledgeSource::TYPE_PAPERLESS,
                            'title'              => $doc['title'],
                            'url'                => $doc['url'],
                            'tags'               => $doc['tag_names'] ?? [],
                            'visibility'         => 'internal',
                            'is_price_list'      => $doc['is_price_list'],
                            'is_lmiv'            => $doc['is_lmiv'],
                            'is_technical'       => $doc['is_technical'],
                            'document_date'      => $doc['document_date'],
                            'source_updated_at'  => $doc['modified'],
                            'metadata'           => $doc['metadata'],
                        ],
                    );

                    $stats = $this->indexing->indexDocument($document, $doc['content']);
                    $created += $stats['created'];
                    $updated += $stats['updated'];

                    if ($stats['created'] + $stats['updated'] > 0) {
                        $indexed++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    Log::error("Knowledge: Paperless doc {$doc['external_id']} indexing failed: " . $e->getMessage());
                }
            }

            // Remove documents that no longer have ki-freigabe
            $removedDocs = KnowledgeDocument::where('source_id', $source->id)
                ->whereNotIn('external_id', $approvedExternalIds)
                ->get();

            foreach ($removedDocs as $removed) {
                $this->indexing->removeDocumentFromQdrant($removed);
                $removed->delete();
                $deleted++;
                Log::info("Knowledge: Removed de-tagged Paperless doc {$removed->external_id}");
            }

            $source->update(['last_synced_at' => now()]);

        } catch (\Throwable $e) {
            $errors++;
            Log::error('Knowledge: Paperless sync failed: ' . $e->getMessage());
        }

        $run->update([
            'status'             => $errors > 0 ? KnowledgeSyncRun::STATUS_FAILED : KnowledgeSyncRun::STATUS_COMPLETED,
            'finished_at'        => now(),
            'documents_seen'     => $seen,
            'documents_indexed'  => $indexed,
            'chunks_created'     => $created,
            'chunks_updated'     => $updated,
            'chunks_deleted'     => $deleted,
            'errors'             => $errors,
        ]);

        $this->info("Paperless: seen={$seen}, indexed={$indexed}, deleted={$deleted}, errors={$errors}");
    }

    private function syncBookStack(): void
    {
        if (!$this->bookstack->isConfigured()) {
            $this->warn('BookStack token not configured — skipping.');
            return;
        }

        $this->info('Syncing BookStack pages...');

        $run = KnowledgeSyncRun::create([
            'tenant_id'  => config('knowledge.tenant_id'),
            'source'     => 'bookstack',
            'status'     => KnowledgeSyncRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $source = KnowledgeSource::firstOrCreate(
            ['source_type' => KnowledgeSource::TYPE_BOOKSTACK, 'tenant_id' => config('knowledge.tenant_id')],
            ['name' => 'BookStack', 'base_url' => config('knowledge.bookstack_url'), 'enabled' => true],
        );

        $errors = $seen = $indexed = $created = $updated = 0;

        try {
            foreach ($this->bookstack->fetchPages() as $page) {
                $seen++;

                try {
                    $document = KnowledgeDocument::updateOrCreate(
                        ['source_id' => $source->id, 'external_id' => $page['external_id']],
                        [
                            'tenant_id'    => config('knowledge.tenant_id'),
                            'source_type'  => KnowledgeSource::TYPE_BOOKSTACK,
                            'title'        => $page['title'],
                            'url'          => $page['url'],
                            'tags'         => $page['tags'],
                            'visibility'   => 'internal',
                            'source_updated_at' => $page['updated_at'],
                            'metadata'     => $page['metadata'],
                            'source_category' => $page['book_name'] ?? null,
                        ],
                    );

                    $stats = $this->indexing->indexDocument($document, $page['content']);
                    $created += $stats['created'];
                    $updated += $stats['updated'];
                    if ($stats['created'] + $stats['updated'] > 0) {
                        $indexed++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    Log::error("Knowledge: BookStack page {$page['external_id']} failed: " . $e->getMessage());
                }
            }

            $source->update(['last_synced_at' => now()]);
        } catch (\Throwable $e) {
            $errors++;
            Log::error('Knowledge: BookStack sync failed: ' . $e->getMessage());
        }

        $run->update([
            'status'            => $errors > 0 ? KnowledgeSyncRun::STATUS_FAILED : KnowledgeSyncRun::STATUS_COMPLETED,
            'finished_at'       => now(),
            'documents_seen'    => $seen,
            'documents_indexed' => $indexed,
            'chunks_created'    => $created,
            'chunks_updated'    => $updated,
            'errors'            => $errors,
        ]);

        $this->info("BookStack: seen={$seen}, indexed={$indexed}, errors={$errors}");
    }
}
