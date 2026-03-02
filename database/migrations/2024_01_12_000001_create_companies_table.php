<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('name');
            // URL-friendly unique identifier, e.g. "kolabri-gmbh"
            $table->string('slug')->unique();

            // VAT / Umsatzsteuer-ID, e.g. "DE123456789"
            $table->string('vat_id', 64)->nullable();

            // Free-form address block for invoices / documents
            $table->text('address')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
