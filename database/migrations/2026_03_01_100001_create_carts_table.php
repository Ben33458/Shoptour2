<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-3: Shopping cart persistence for authenticated users.
 *
 * Guests continue to use PHP sessions; authenticated users get DB-persisted carts.
 * After login, CartMergeService transfers guest session items into the DB cart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 255)->nullable();
            $table->enum('status', ['active', 'merged', 'abandoned'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('user_id');
            $table->index('session_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
