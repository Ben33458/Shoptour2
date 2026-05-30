<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('query_id')->constrained('knowledge_queries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reaction', 32); // helpful, wrong, incomplete, outdated, critical, good_idea, adopt_rule, lifesaver, suspicious, remove
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['query_id', 'user_id', 'reaction']);
            $table->index(['query_id', 'reaction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_feedback');
    }
};
