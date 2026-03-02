<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * PROJ-2: Add slug, warengruppe_id, show_in_shop to products table.
 *
 * - slug: SEO-friendly URL key, generated from Brand + ProductLine + Gebinde names
 * - warengruppe_id: FK to the new warengruppen table (nullable)
 * - show_in_shop: controls visibility in the public storefront (default true)
 *
 * The migration automatically generates slugs for all existing products.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug', 255)->nullable()->after('artikelnummer');
            $table->unsignedBigInteger('warengruppe_id')->nullable()->after('category_id');
            $table->boolean('show_in_shop')->default(true)->after('active');

            $table->foreign('warengruppe_id')
                ->references('id')
                ->on('warengruppen')
                ->nullOnDelete();

            $table->index('warengruppe_id', 'products_warengruppe_id_idx');
            $table->index('show_in_shop', 'products_show_in_shop_idx');
        });

        // ── Generate slugs for all existing products ─────────────────────────
        $this->generateSlugsForExistingProducts();

        // Now make slug unique and not-null
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug', 255)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['warengruppe_id']);
            $table->dropIndex('products_warengruppe_id_idx');
            $table->dropIndex('products_show_in_shop_idx');
            // slug unique index is auto-named by Laravel
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'warengruppe_id', 'show_in_shop']);
        });
    }

    /**
     * Generate slugs for all existing products based on Brand + ProductLine + Gebinde names.
     * On duplicate slugs, appends -2, -3, etc.
     */
    private function generateSlugsForExistingProducts(): void
    {
        $products = DB::table('products')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->join('product_lines', 'products.product_line_id', '=', 'product_lines.id')
            ->join('gebinde', 'products.gebinde_id', '=', 'gebinde.id')
            ->select([
                'products.id',
                'brands.name as brand_name',
                'product_lines.name as product_line_name',
                'gebinde.name as gebinde_name',
            ])
            ->orderBy('products.id')
            ->get();

        $usedSlugs = [];

        foreach ($products as $product) {
            $baseSlug = Str::slug(
                $product->brand_name . ' ' . $product->product_line_name . ' ' . $product->gebinde_name
            );

            // Ensure non-empty slug
            if ($baseSlug === '') {
                $baseSlug = 'produkt';
            }

            $slug    = $baseSlug;
            $counter = 2;

            while (in_array($slug, $usedSlugs, true)) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $usedSlugs[] = $slug;

            DB::table('products')
                ->where('id', $product->id)
                ->update(['slug' => $slug]);
        }
    }
};
