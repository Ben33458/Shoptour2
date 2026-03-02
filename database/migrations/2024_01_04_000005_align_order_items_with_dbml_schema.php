<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns order_items with the DBML schema.
 *
 * REMOVES (added by 2024_01_04_000004, not in DBML):
 *   - tax_rate_id_snapshot         (non-standard column name)
 *   - pfand_set_id_snapshot         (non-standard column name)
 *   - tax_rate_basis_points_snapshot (non-standard column name — was in original create)
 *   - pfand_brutto_milli_per_unit    (non-standard column name — was in original create)
 *
 * ADDS (canonical DBML names):
 *   - tax_rate_id          unsignedBigInteger nullable — FK ref to tax_rates.id (no constraint)
 *   - tax_rate_basis_points unsignedInteger   not null — effective rate in bp at order time
 *   - pfand_set_id         unsignedBigInteger nullable — FK ref to pfand_sets.id (no constraint)
 *   - unit_deposit_milli   bigInteger         not null default 0 — deposit per unit (milli-cents)
 *
 * The *_snapshot suffix is intentionally dropped: the DBML design uses the plain
 * column names because the entire order_items row IS the snapshot by definition.
 * No suffix is needed to convey immutability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // ── Remove previously-added non-DBML columns ──────────────────────
            // These were added in migration 000004; some were also in the original
            // create migration under different names.
            $columnsToRemove = [];

            if (Schema::hasColumn('order_items', 'tax_rate_id_snapshot')) {
                $columnsToRemove[] = 'tax_rate_id_snapshot';
            }
            if (Schema::hasColumn('order_items', 'pfand_set_id_snapshot')) {
                $columnsToRemove[] = 'pfand_set_id_snapshot';
            }
            if (Schema::hasColumn('order_items', 'tax_rate_basis_points_snapshot')) {
                $columnsToRemove[] = 'tax_rate_basis_points_snapshot';
            }
            if (Schema::hasColumn('order_items', 'pfand_brutto_milli_per_unit')) {
                $columnsToRemove[] = 'pfand_brutto_milli_per_unit';
            }

            if ($columnsToRemove !== []) {
                $table->dropColumn($columnsToRemove);
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            // ── Add canonical DBML columns ─────────────────────────────────────

            // Frozen FK reference to the tax_rates record that was active at order
            // creation time. Stored without a DB-level FK constraint so that
            // tax_rates rows can be archived without breaking historic order data.
            // NULL = tax module inactive or product had no tax_rate_id.
            $table->unsignedBigInteger('tax_rate_id')->nullable()->after('price_source');

            // Effective tax rate in basis-points frozen at order time.
            // E.g. 190_000 = 19 % German standard VAT, 70_000 = 7 % reduced rate.
            // NOT NULL — every order line must carry a definitive rate.
            // No default: the application MUST supply this; an omitted value is a
            // programming error and must be caught before the INSERT.
            $table->unsignedInteger('tax_rate_basis_points')->after('tax_rate_id');

            // Frozen FK reference to the PfandSet that was active on the product's
            // Gebinde at order time.  NULL when the product has no Gebinde, or the
            // customer is deposit-exempt.  No DB-level FK — PfandSets may be archived.
            $table->unsignedBigInteger('pfand_set_id')->nullable()->after('tax_rate_basis_points');

            // Total deposit per ordered unit (milli-cents, brutto).
            // 0 when the customer is deposit-exempt or no PfandSet components exist.
            $table->bigInteger('unit_deposit_milli')->default(0)->after('pfand_set_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Remove DBML columns
            $table->dropColumn([
                'tax_rate_id',
                'tax_rate_basis_points',
                'pfand_set_id',
                'unit_deposit_milli',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Restore non-DBML columns
            $table->unsignedInteger('tax_rate_basis_points_snapshot')->default(190_000)->after('price_source');
            $table->bigInteger('pfand_brutto_milli_per_unit')->default(0)->after('tax_rate_basis_points_snapshot');
            $table->unsignedInteger('tax_rate_id_snapshot')->default(0)->after('tax_rate_basis_points_snapshot');
            $table->unsignedBigInteger('pfand_set_id_snapshot')->nullable()->after('tax_rate_id_snapshot');
        });
    }
};
