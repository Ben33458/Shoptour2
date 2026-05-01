<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('parent_customer_id');
            // {"orders":true,"order_history":"own","invoices":false,"addresses":false,"assortment":false,"sub_users":false}
            $table->json('permissions');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('parent_customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_users');
    }
};
