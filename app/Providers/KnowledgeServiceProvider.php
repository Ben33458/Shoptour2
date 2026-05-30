<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Knowledge\BookStackConnector;
use App\Services\Knowledge\ChunkingService;
use App\Services\Knowledge\EmbeddingService;
use App\Services\Knowledge\IndexingService;
use App\Services\Knowledge\KnowledgeQueryService;
use App\Services\Knowledge\PaperlessConnector;
use App\Services\Knowledge\QdrantService;
use Illuminate\Support\ServiceProvider;

class KnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QdrantService::class);
        $this->app->singleton(EmbeddingService::class);
        $this->app->singleton(ChunkingService::class);
        $this->app->singleton(PaperlessConnector::class);
        $this->app->singleton(BookStackConnector::class);

        $this->app->singleton(IndexingService::class, function ($app) {
            return new IndexingService(
                $app->make(QdrantService::class),
                $app->make(EmbeddingService::class),
                $app->make(ChunkingService::class),
            );
        });

        $this->app->singleton(KnowledgeQueryService::class, function ($app) {
            return new KnowledgeQueryService(
                $app->make(QdrantService::class),
                $app->make(EmbeddingService::class),
                $app->make(BookStackConnector::class),
            );
        });
    }
}
