<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64)->default('kolabri')->index();
            $table->foreignId('source_id')->constrained('knowledge_sources')->cascadeOnDelete();
            $table->string('source_type', 32)->index();
            $table->string('external_id', 255)->index(); // ID in the source system
            $table->string('title', 500);
            $table->string('url', 1000)->nullable();
            $table->text('content_hash')->nullable();        // SHA-256 of full content
            $table->json('tags')->nullable();
            $table->string('visibility', 32)->default('internal'); // internal, public
            $table->json('permissions')->nullable();              // future role-based access
            $table->boolean('is_price_list')->default(false);
            $table->boolean('is_lmiv')->default(false);
            $table->boolean('is_technical')->default(false);
            $table->date('document_date')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('source_category', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('indexed')->default(false);
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['tenant_id', 'source_type', 'indexed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
