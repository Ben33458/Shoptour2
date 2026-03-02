<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-15: LMIV versions table.
 *
 * One row = one "LMIV version" for a base-item product.
 * A new version is created automatically whenever the active EAN changes.
 * Versions are closed (effective_to filled, status=archived) when superseded.
 *
 * Columns:
 *   product_id          – FK to products (the base item)
 *   version_number      – monotonically increasing per product (1, 2, 3 …)
 *   ean                 – the EAN that was active when this version was opened
 *   status              – draft | active | archived
 *   data_json           – all LMIV fields as JSON (see LmivVersioningService)
 *   change_reason       – optional free-text note about why this version was created
 *   effective_from      – when this version became / becomes active
 *   effective_to        – when this version was superseded (null = still active)
 *   created_by_user_id  – admin user who triggered the change (nullable for automated)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_lmiv_versions', static function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->cascadeOnDelete();

            $table->unsignedSmallInteger('version_number')->default(1);

            $table->string('ean', 30)->nullable();         // active EAN at version creation
            $table->string('status', 20)->default('draft'); // draft|active|archived

            $table->json('data_json')->nullable();           // all LMIV fields

            $table->string('change_reason', 255)->nullable();

            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->timestamps();

            // One active version per product at a time enforced at app level;
            // index for quick "get active version" queries:
            $table->index(['product_id', 'status']);
            $table->index(['product_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_lmiv_versions');
    }
};
