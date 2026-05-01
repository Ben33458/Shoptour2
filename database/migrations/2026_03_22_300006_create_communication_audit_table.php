<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('communication_id');
            $table->foreign('communication_id')->references('id')->on('communications')->cascadeOnDelete();

            $table->string('event_type', 100);
            // imported | rule_matched | assigned | reviewed | archived | attachment_stored | manual_note
            $table->json('details_json')->nullable();

            $table->unsignedBigInteger('user_id')->nullable(); // NULL = System
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent(); // append-only, kein updated_at

            $table->index(['communication_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_audit');
    }
};
