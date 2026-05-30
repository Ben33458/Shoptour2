<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Models\Knowledge\KnowledgeDocument;
use App\Services\Knowledge\IndexingService;
use App\Services\Knowledge\PaperlessConnector;
use App\Services\Knowledge\QdrantService;
use Illuminate\Console\Command;

class KnowledgeReindexCommand extends Command
{
    protected $signature = 'knowledge:reindex {--source= : Limit to specific source_type}';
    protected $description = 'Force reindex all documents (ignores content_hash cache)';

    public function __construct(
        private readonly IndexingService    $indexing,
        private readonly QdrantService      $qdrant,
        private readonly PaperlessConnector $paperless,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('knowledge.enabled')) {
            $this->warn('Knowledge module is disabled.');
            return self::FAILURE;
        }

        $this->qdrant->ensureCollection();

        $query = KnowledgeDocument::query();
        if ($sourceFilter = $this->option('source')) {
            $query->where('source_type', $sourceFilter);
        }

        $total = $query->count();
        $this->info("Reindexing {$total} documents...");
        $bar = $this->output->createProgressBar($total);

        $done = $errors = 0;

        $query->each(function (KnowledgeDocument $doc) use (&$done, &$errors, $bar) {
            try {
                // Reset content hash so indexing always runs
                $doc->update(['content_hash' => null]);

                $content = $this->fetchContent($doc);
                if ($content !== null) {
                    $this->indexing->indexDocument($doc, $content);
                    $done++;
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->line("  Error for doc #{$doc->id}: " . $e->getMessage());
            }
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info("Reindex complete: {$done} done, {$errors} errors.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function fetchContent(KnowledgeDocument $doc): ?string
    {
        if ($doc->source_type === 'paperless') {
            return $this->paperless->getDocumentContent((int) $doc->external_id);
        }

        // For bookstack: content is in metadata (would require a full re-fetch)
        return null;
    }
}
