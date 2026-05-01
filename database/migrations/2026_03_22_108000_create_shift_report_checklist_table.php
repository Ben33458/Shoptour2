<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shift_report_checklist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_checked')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['shift_report_id', 'checklist_item_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('shift_report_checklist'); }
};
