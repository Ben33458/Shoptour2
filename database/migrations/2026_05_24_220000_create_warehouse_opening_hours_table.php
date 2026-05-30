<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('day_of_week'); // 0=Sonntag, 1=Montag … 6=Samstag
            $table->time('open_from');
            $table->time('open_to');
            $table->timestamps();

            $table->unique(['warehouse_id', 'day_of_week']);
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_opening_hours');
    }
};
