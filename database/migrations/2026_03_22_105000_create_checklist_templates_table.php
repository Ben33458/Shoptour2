<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('type', ['opening','closing','cleaning','general'])->default('general');
            $table->foreignId('shift_area_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('checklist_templates'); }
};
