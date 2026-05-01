<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexoffice_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable()->index();

            // Link to the voucher this payment belongs to
            $table->string('lexoffice_voucher_id', 36)->index();

            // Individual payment record UUID from Lexoffice
            $table->string('payment_id', 36)->nullable()->unique();

            // Voucher metadata (denormalized for easy querying)
            $table->string('voucher_type', 32)->nullable();
            $table->string('contact_name', 255)->nullable();

            // Payment details
            $table->date('payment_date')->nullable()->index();
            $table->bigInteger('amount')->default(0);         // milli-cent, signed
            $table->string('currency', 10)->default('EUR');
            $table->string('payment_type', 64)->nullable()->index(); // 'payment', 'creditNote', ...
            $table->text('open_item_description')->nullable();

            // Remaining open amount on the voucher at time of import
            $table->bigInteger('open_amount')->default(0);   // milli-cent

            // Full payments/{voucherId} response stored once per voucher row
            $table->json('raw_json')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexoffice_payments');
    }
};
