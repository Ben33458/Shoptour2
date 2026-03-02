<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver API token table.
 *
 * Simple bearer-token auth for the driver PWA. No sessions, no cookies.
 * The token is stored as a SHA-256 hash (token_hash); the plain token is
 * shown once at creation time and never stored.
 *
 * employee_id is nullable because the Employee module does not yet exist.
 * No FK constraint — the column is a plain bigint audit reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_api_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Employee reference — plain bigint, no FK (Employee module pending)
            $table->unsignedBigInteger('employee_id')->nullable();

            // SHA-256 hash of the actual bearer token
            $table->string('token_hash', 64)->unique();

            // Human-readable label, e.g. "Max Mustermann – iPhone 14"
            $table->string('label')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_api_tokens');
    }
};
