<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexoffice_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('lexoffice_voucher_id', 36)->unique();
            $table->string('voucher_type', 32);
            $table->string('voucher_number', 64)->nullable();
            $table->date('voucher_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('voucher_status', 32)->nullable();
            $table->unsignedBigInteger('total_gross_amount')->default(0);
            $table->unsignedBigInteger('open_amount')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('lexoffice_contact_id', 36)->nullable()->index();
            $table->string('contact_name', 255)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('local_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'voucher_status']);
            $table->index(['supplier_id', 'voucher_status']);
            $table->index(['voucher_type', 'voucher_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexoffice_vouchers');
    }
};
