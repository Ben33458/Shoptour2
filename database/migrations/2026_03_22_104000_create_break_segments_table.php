<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('break_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_entry_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('counted_as_break')->default(true)->comment('False if <15 min (not legally countable)');
            $table->timestamps();
            $table->index(['time_entry_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('break_segments'); }
};
