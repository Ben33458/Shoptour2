<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('order_id')->unique(); // one invoice per order
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();

            // Auto-assigned on finalize (e.g. "RE-2024-00001")
            $table->string('invoice_number', 64)->nullable()->unique();

            // draft → finalized
            $table->string('status')->default('draft');

            // Totals (milli-cents)
            $table->bigInteger('total_net_milli')->default(0);
            $table->bigInteger('total_gross_milli')->default(0);
            $table->bigInteger('total_tax_milli')->default(0);
            $table->bigInteger('total_adjustments_milli')->default(0);
            $table->bigInteger('total_deposit_milli')->default(0);

            // Generated PDF file path (relative to storage/app)
            $table->string('pdf_path', 500)->nullable();

            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
