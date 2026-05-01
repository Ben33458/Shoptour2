<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100)->comment('e.g. shift.create, time.clock_in, vacation.approve');
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('payload')->nullable()->comment('Relevant data snapshot');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->index(['action', 'logged_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'logged_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('system_logs'); }
};
