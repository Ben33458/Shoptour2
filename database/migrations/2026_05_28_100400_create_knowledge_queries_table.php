<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_queries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64)->default('kolabri')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('question');
            $table->longText('answer')->nullable();
            $table->string('question_type', 32)->default('knowledge'); // knowledge, data
            $table->boolean('used_internal_sources')->default(false);
            $table->boolean('used_live_data')->default(false);
            $table->boolean('has_customer_data')->default(false);   // for special audit log
            $table->boolean('has_invoice_data')->default(false);    // for special audit log
            $table->string('filter', 32)->nullable();               // all, wiki, documents, price_list, technik
            $table->unsignedSmallInteger('source_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['tenant_id', 'question_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_queries');
    }
};
