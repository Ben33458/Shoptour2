<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Supplier type ───────────────────────────────────────────────
        // 'supplier' = Warenlieferant (shows in purchase order dropdowns)
        // 'partner'  = Geschäftspartner (Krankenkassen, Tankstellen, etc.)
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('type', 20)->default('supplier')->after('company_id');
            $table->index('type');
        });

        // ── 2. Lexoffice import block list ─────────────────────────────────
        // Records added here will be skipped during Lexoffice pull imports.
        Schema::create('lexoffice_contact_blocks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('lexoffice_contact_id', 36);
            $table->string('blocked_entity', 20)->default('both'); // customer, supplier, both
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['company_id', 'lexoffice_contact_id']);
            $table->index('lexoffice_contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexoffice_contact_blocks');
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
