<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconcile_product_rules', function (Blueprint $table): void {
            $table->id();
            // 'synonym' → source_token wird zu target_token normalisiert
            // 'noise'   → source_token wird gestripped (target_token = '')
            $table->string('type', 20);
            $table->string('source_token', 100);
            $table->string('target_token', 100)->default('');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['type', 'source_token']);
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconcile_product_rules');
    }
};
