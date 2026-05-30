<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_query_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('query_id')->constrained('knowledge_queries')->cascadeOnDelete();
            $table->string('source_type', 32);
            $table->string('source_id', 255)->nullable();
            $table->string('source_title', 500)->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->string('chunk_id', 36)->nullable(); // Qdrant point ID
            $table->float('score')->nullable();
            $table->text('snippet')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('query_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_query_sources');
    }
};
