<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id')->nullable();

            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();

            // ISO 4217 currency code for purchase orders, default EUR
            $table->string('currency', 3)->default('EUR');

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->nullOnDelete();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
