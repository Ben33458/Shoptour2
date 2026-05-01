<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_user_invitations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('email');
            $table->string('first_name');
            $table->string('last_name');
            $table->unsignedBigInteger('parent_customer_id');
            $table->json('permissions');
            $table->string('token', 64)->unique(); // hashed token
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('parent_customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->index(['token', 'used_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_user_invitations');
    }
};
