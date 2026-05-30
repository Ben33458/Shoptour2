<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64)->default('kolabri')->index();
            $table->foreignId('gap_id')->nullable()->constrained('knowledge_gaps')->nullOnDelete();
            $table->foreignId('query_id')->nullable()->constrained('knowledge_queries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 500);
            $table->longText('content');
            $table->string('bookstack_page_id', 255)->nullable();
            $table->string('bookstack_book_slug', 255)->nullable();
            $table->string('status', 32)->default('pending'); // pending, pushed, published, failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_drafts');
    }
};
