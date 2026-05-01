<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();

            $table->string('name', 200);
            $table->text('description')->nullable();

            // Condition
            $table->string('condition_type', 50);
            // from_domain | from_address | subject_contains | has_attachment | attachment_type | to_address
            $table->string('condition_value', 500);

            // Action
            $table->string('action_type', 50);
            // assign_customer | assign_supplier | set_category | set_tag | skip_review | set_direction
            $table->string('action_value', 500)->nullable();

            $table->unsignedTinyInteger('confidence_boost')->default(20); // 0-100
            $table->smallInteger('priority')->default(100);               // kleiner = früher
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['company_id', 'active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_rules');
    }
};
