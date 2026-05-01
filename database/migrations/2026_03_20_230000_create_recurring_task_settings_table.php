<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_task_settings', function (Blueprint $table): void {
            $table->unsignedBigInteger('ninox_task_id')->primary();
            // 'auto'             = ninox ab_wann_wiederholen verwenden
            // 'from_completion'  = immer ab Erledigungsdatum
            // 'fixed_schedule'   = immer ab Fälligkeit / fester Termin
            $table->enum('recurrence_basis', ['auto', 'from_completion', 'fixed_schedule'])
                  ->default('auto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_task_settings');
    }
};
