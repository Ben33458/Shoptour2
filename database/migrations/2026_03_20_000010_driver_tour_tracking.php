<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tour: started_at / ended_at
        Schema::table('tours', function (Blueprint $table): void {
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('ended_at')->nullable()->after('started_at');
        });

        // TourStop: departed_at
        Schema::table('tour_stops', function (Blueprint $table): void {
            $table->timestamp('departed_at')->nullable()->after('finished_at');
        });

        // Cash registers
        Schema::create('cash_registers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Cash register → employee assignment (one register per employee)
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('cash_register_id')->nullable()->after('role');
            $table->foreign('cash_register_id')->references('id')->on('cash_registers')->nullOnDelete();
        });

        // Cash transactions (Kassenentnahme / Einnahme)
        Schema::create('cash_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cash_register_id');
            $table->foreign('cash_register_id')->references('id')->on('cash_registers')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('tour_id')->nullable()->index();
            $table->enum('type', ['withdrawal', 'deposit']);
            $table->integer('amount_cents');   // always positive; type determines direction
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['cash_register_id', 'created_at']);
        });

        // Driver settings (key-value store for configurable driver behaviour)
        Schema::create('driver_settings', function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->string('value', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['cash_register_id']);
            $table->dropColumn('cash_register_id');
        });
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('cash_registers');
        Schema::table('tour_stops', function (Blueprint $table): void {
            $table->dropColumn('departed_at');
        });
        Schema::table('tours', function (Blueprint $table): void {
            $table->dropColumn(['started_at', 'ended_at']);
        });
        Schema::dropIfExists('driver_settings');
    }
};
