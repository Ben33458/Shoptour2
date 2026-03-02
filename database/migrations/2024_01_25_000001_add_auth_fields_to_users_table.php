<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-1: Extend users table for full auth support.
 *
 * - Split `name` into `first_name` + `last_name`
 * - Add `active` flag (for account deactivation)
 * - Add `company_id` (multi-tenant preparation)
 * - Make `password` nullable (Google OAuth users have no password)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            // Add new columns
            $table->string('first_name', 100)->after('id')->default('');
            $table->string('last_name', 100)->after('first_name')->default('');
            $table->boolean('active')->default(true)->after('avatar_url');
            $table->unsignedBigInteger('company_id')->nullable()->after('active');

            // Make password nullable for OAuth-only users
            $table->string('password')->nullable()->change();
        });

        // Migrate existing data: split `name` into first_name + last_name
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE users
                SET first_name = SUBSTRING_INDEX(name, ' ', 1),
                    last_name  = CASE
                        WHEN LOCATE(' ', name) > 0
                        THEN TRIM(SUBSTRING(name, LOCATE(' ', name) + 1))
                        ELSE ''
                    END
            ");
        } else {
            // SQLite / Testing: PHP-based iteration (no SUBSTRING_INDEX)
            foreach (DB::table('users')->whereNotNull('name')->get() as $row) {
                $parts = explode(' ', (string) $row->name, 2);
                DB::table('users')->where('id', $row->id)->update([
                    'first_name' => $parts[0],
                    'last_name'  => $parts[1] ?? '',
                ]);
            }
        }

        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn('name');
            $table->index('company_id');
            $table->index('active');
        });

        // Add company_name to customers table (optional field for business customers)
        if (! Schema::hasColumn('customers', 'company_name')) {
            Schema::table('customers', static function (Blueprint $table): void {
                $table->string('company_name', 255)->nullable()->after('customer_number');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->string('name')->after('id')->default('');
        });

        // Merge first_name + last_name back into name
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE users
                SET name = CONCAT(first_name, ' ', last_name)
            ");
        } else {
            // SQLite / Testing: PHP-based iteration
            foreach (DB::table('users')->get() as $row) {
                DB::table('users')->where('id', $row->id)->update([
                    'name' => trim($row->first_name . ' ' . $row->last_name),
                ]);
            }
        }

        Schema::table('users', static function (Blueprint $table): void {
            $table->dropIndex(['company_id']);
            $table->dropIndex(['active']);
            $table->dropColumn(['first_name', 'last_name', 'active', 'company_id']);
            $table->string('password')->nullable(false)->change();
        });

        if (Schema::hasColumn('customers', 'company_name')) {
            Schema::table('customers', static function (Blueprint $table): void {
                $table->dropColumn('company_name');
            });
        }
    }
};
