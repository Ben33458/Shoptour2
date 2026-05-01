<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dunning_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            // Status: draft | sent | cancelled
            $table->string('status')->default('draft');

            // Vorschau-/Testmodus — keine echten E-Mails
            $table->boolean('is_test_mode')->default(false);

            $table->text('notes')->nullable();

            // Timestamp when the run was actually executed
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dunning_runs');
    }
};
