<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexoffice_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('lexoffice_uuid', 36)->unique();
            $table->unsignedInteger('version')->default(0);
            $table->boolean('archived')->default(false);
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_vendor')->default(false);
            $table->string('customer_number', 50)->nullable();
            $table->string('vendor_number', 50)->nullable();
            $table->string('company_name', 255)->nullable();
            $table->string('salutation', 30)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('primary_email', 255)->nullable()->index();
            $table->string('primary_phone', 100)->nullable();
            $table->text('note')->nullable();
            $table->json('raw_json');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexoffice_contacts');
    }
};
