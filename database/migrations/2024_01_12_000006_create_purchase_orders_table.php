<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase orders sent to suppliers.
 *
 * Status lifecycle:
 *   draft → sent → confirmed → received | cancelled
 *
 * total_milli: sum of (qty × unit_purchase_milli) for all items (milli-cents).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('warehouse_id');

            // PO-YYYY-NNNNN, assigned on creation
            $table->string('po_number', 64)->nullable()->unique();

            // draft | sent | confirmed | received | cancelled
            $table->string('status')->default('draft');

            $table->date('ordered_at')->nullable();
            $table->date('expected_at')->nullable();

            // Sum of line totals in milli-cents
            $table->bigInteger('total_milli')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->nullOnDelete();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->restrictOnDelete();

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->restrictOnDelete();

            $table->index(['company_id', 'status']);
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
