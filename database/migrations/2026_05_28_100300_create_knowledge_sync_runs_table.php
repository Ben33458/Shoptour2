<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64)->default('kolabri')->index();
            $table->string('source', 64)->index(); // paperless, bookstack, shoptour2, all
            $table->string('status', 32)->default('running'); // running, completed, failed
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('documents_seen')->default(0);
            $table->unsignedInteger('documents_indexed')->default(0);
            $table->unsignedInteger('chunks_created')->default(0);
            $table->unsignedInteger('chunks_updated')->default(0);
            $table->unsignedInteger('chunks_deleted')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->json('error_log')->nullable();
            $table->timestamps();

            $table->index(['source', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_sync_runs');
    }
};
