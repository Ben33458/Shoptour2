<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_queries', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('knowledge_feedback', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('knowledge_gaps', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('knowledge_drafts', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Only reverse if no nulls exist
        Schema::table('knowledge_queries',  fn ($t) => $t->foreignId('user_id')->nullable(false)->change());
        Schema::table('knowledge_feedback', fn ($t) => $t->foreignId('user_id')->nullable(false)->change());
        Schema::table('knowledge_gaps',     fn ($t) => $t->foreignId('user_id')->nullable(false)->change());
        Schema::table('knowledge_drafts',   fn ($t) => $t->foreignId('created_by')->nullable(false)->change());
    }
};
