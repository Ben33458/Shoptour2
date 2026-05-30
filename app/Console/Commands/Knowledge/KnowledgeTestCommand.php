<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Services\Knowledge\BookStackConnector;
use App\Services\Knowledge\EmbeddingService;
use App\Services\Knowledge\PaperlessConnector;
use App\Services\Knowledge\QdrantService;
use Illuminate\Console\Command;

class KnowledgeTestCommand extends Command
{
    protected $signature = 'knowledge:test';
    protected $description = 'Test all Knowledge service connections';

    public function __construct(
        private readonly QdrantService      $qdrant,
        private readonly EmbeddingService   $embedding,
        private readonly PaperlessConnector $paperless,
        private readonly BookStackConnector $bookstack,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Testing Knowledge Assistant connections...');
        $allOk = true;

        // Qdrant
        $this->output->write('  Qdrant (' . config('knowledge.qdrant_url') . ') ... ');
        if ($this->qdrant->testConnection()) {
            $count = $this->qdrant->count();
            $this->info("OK ({$count} points in collection)");
        } else {
            $this->error('FAILED');
            $allOk = false;
        }

        // Ollama Embeddings
        $this->output->write('  Ollama Embeddings (' . config('knowledge.ollama_embedding_model') . ') ... ');
        if ($this->embedding->testConnection()) {
            $this->info('OK');
        } else {
            $this->error('FAILED');
            $allOk = false;
        }

        // Paperless
        $this->output->write('  Paperless (' . config('knowledge.paperless_url') . ') ... ');
        $tags = $this->paperless->fetchAllTags();
        if (count($tags) > 0) {
            $this->info('OK (' . count($tags) . ' tags found)');
        } else {
            $this->warn('WARNING (no tags found or connection error)');
        }

        // BookStack
        $this->output->write('  BookStack (' . config('knowledge.bookstack_url') . ') ... ');
        if (!$this->bookstack->isConfigured()) {
            $this->warn('SKIPPED (no token configured)');
        } elseif ($this->bookstack->testConnection()) {
            $this->info('OK');
        } else {
            $this->error('FAILED');
            $allOk = false;
        }

        $this->newLine();
        if ($allOk) {
            $this->info('All connections OK.');
        } else {
            $this->error('Some connections failed. Check logs.');
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}
