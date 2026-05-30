<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64)->default('kolabri')->index();
            $table->foreignId('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->string('qdrant_point_id', 36)->unique(); // UUID used as Qdrant point ID
            $table->unsignedSmallInteger('chunk_index')->default(0);
            $table->text('chunk_text');
            $table->string('chunk_hash', 64)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'chunk_index']);
            $table->index(['tenant_id', 'chunk_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
