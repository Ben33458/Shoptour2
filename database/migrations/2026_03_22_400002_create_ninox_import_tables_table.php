<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ninox_import_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ninox_import_runs')->cascadeOnDelete();
            $table->string('table_id', 50)->comment('Ninox table ID (e.g. "D")');
            $table->string('table_name', 200)->comment('Ninox table display name');
            $table->enum('status', ['pending', 'importing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('records_count')->default(0);
            $table->unsignedInteger('records_imported')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'status']);
            $table->index('table_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ninox_import_tables');
    }
};
