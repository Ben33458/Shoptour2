<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexoffice_recurring_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('lexoffice_uuid', 36)->unique();
            $table->unsignedInteger('version')->default(0);
            $table->string('name', 255)->nullable();
            $table->string('voucher_type', 32)->default('salesinvoice');
            $table->string('frequency', 32)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('next_execution_date')->nullable();
            $table->date('last_execution_date')->nullable();
            $table->unsignedBigInteger('total_net_amount')->nullable();
            $table->unsignedBigInteger('total_gross_amount')->nullable();
            $table->string('currency', 10)->default('EUR');
            $table->string('lexoffice_contact_id', 36)->nullable()->index();
            $table->json('raw_json');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexoffice_recurring_templates');
    }
};
