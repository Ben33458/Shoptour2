<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexoffice_import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('running')->index(); // running|done|failed
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->json('result_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexoffice_import_runs');
    }
};
