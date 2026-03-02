<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();

            // cash | transfer | card | other
            $table->string('payment_method')->default('cash');

            // Amount received (milli-cents)
            $table->bigInteger('amount_milli');

            $table->timestamp('paid_at');
            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
