<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // ── Ninox-Verknüpfung ────────────────────────────────────────────
            $table->string('ninox_source_id', 50)->nullable()
                  ->comment('Ninox record ID (raw import)');
            $table->string('ninox_source_table', 100)->nullable()->after('ninox_source_id')
                  ->comment('Ninox table name this employee came from');

            // ── Onboarding-Status ────────────────────────────────────────────
            $table->enum('onboarding_status', [
                'pending',        // Importiert, Onboarding noch nicht gestartet
                'pending_review', // Daten eingereicht, wartet auf Admin-Freigabe
                'approved',       // Admin hat freigegeben
                'active',         // Vollständig aktiviert, kann sich einloggen
            ])->default('pending')->after('ninox_source_table');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_status');
            $table->timestamp('privacy_accepted_at')->nullable()->after('onboarding_completed_at');

            // ── Pflicht-Onboarding-Daten ──────────────────────────────────────
            $table->string('iban', 34)->nullable()->after('privacy_accepted_at');
            $table->string('emergency_contact_name', 200)->nullable()->after('iban');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_name');

            // ── Adresse ───────────────────────────────────────────────────────
            $table->string('address_street', 200)->nullable()->after('emergency_contact_phone');
            $table->string('address_zip', 10)->nullable()->after('address_street');
            $table->string('address_city', 100)->nullable()->after('address_zip');

            // ── Optionale Felder ──────────────────────────────────────────────
            $table->string('nickname', 100)->nullable()->after('address_city')
                  ->comment('Rufname');
            $table->string('clothing_size', 20)->nullable()->after('nickname');
            $table->string('shoe_size', 10)->nullable()->after('clothing_size');
            $table->string('drivers_license_class', 20)->nullable()->after('shoe_size');
            $table->date('drivers_license_expiry')->nullable()->after('drivers_license_class');
            $table->text('notes_employee')->nullable()->after('drivers_license_expiry')
                  ->comment('Bemerkungen des Mitarbeiters beim Onboarding');

            $table->index('onboarding_status');
            $table->index('ninox_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['onboarding_status']);
            $table->dropIndex(['ninox_source_id']);
            $table->dropColumn([
                'ninox_source_id', 'ninox_source_table',
                'onboarding_status', 'onboarding_completed_at', 'privacy_accepted_at',
                'iban', 'emergency_contact_name', 'emergency_contact_phone',
                'address_street', 'address_zip', 'address_city',
                'nickname', 'clothing_size', 'shoe_size',
                'drivers_license_class', 'drivers_license_expiry', 'notes_employee',
            ]);
        });
    }
};
