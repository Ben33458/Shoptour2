<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-32: Add partially_received status support and po_sequences table
 * for race-condition-free PO number generation.
 *
 * Status lifecycle extended:
 *   draft → sent → confirmed → partially_received → received | cancelled
 *
 * The status column is VARCHAR so no ALTER ENUM needed — just a model-level change.
 */
return new class extends Migration
{
    public function up(): void
    {
        // po_sequences — race-condition-free PO number generation (like invoice_sequences)
        if (! Schema::hasTable('po_sequences')) {
            Schema::create('po_sequences', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('prefix', 16)->default('EK');
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'prefix']);

                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            });
        }

        // Add notes column to purchase_order_items if missing
        if (! Schema::hasColumn('purchase_order_items', 'notes')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('received_qty');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('po_sequences');

        if (Schema::hasColumn('purchase_order_items', 'notes')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
