<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debtor_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            // Relation — customer is mandatory, voucher optional (note on invoice level)
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('lexoffice_voucher_id')->nullable()->index();

            // Type: note | task | payment_promise | dispute | warning
            $table->string('type')->default('note');

            $table->text('body');

            // Status: open | done
            $table->string('status')->default('open');

            // For payment_promise: promised payment date
            $table->date('promised_date')->nullable();

            // Wiedervorlage — remind again at this time
            $table->timestamp('due_at')->nullable();

            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->timestamps();

            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->restrictOnDelete();

            $table->foreign('lexoffice_voucher_id')
                ->references('id')->on('lexoffice_vouchers')
                ->nullOnDelete();

            $table->index(['customer_id', 'status']);
            $table->index(['due_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debtor_notes');
    }
};
