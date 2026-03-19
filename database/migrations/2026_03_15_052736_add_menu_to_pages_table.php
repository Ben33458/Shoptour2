<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            // menu: footer | main | none
            $table->string('menu')->default('none')->after('slug');
            // Sortierungsreihenfolge innerhalb des Menüs
            $table->unsignedSmallInteger('sort_order')->default(0)->after('menu');
            // Sichtbarkeit
            $table->boolean('active')->default(true)->after('sort_order');
        });

        // Bestehende rechtliche Seiten → Footer
        DB::table('pages')
            ->whereIn('slug', ['impressum', 'datenschutz', 'agb', 'widerruf'])
            ->update(['menu' => 'footer']);

        // Neue Hauptmenü-Seiten
        $now = now();
        DB::table('pages')->insert([
            [
                'slug'       => 'heimdienst',
                'title'      => 'Heimdienst',
                'menu'       => 'main',
                'sort_order' => 10,
                'active'     => true,
                'content'    => '<h2>Unser Heimdienst</h2><p>Bequem von zu Hause bestellen – wir liefern Ihre Lieblingsgetränke direkt an die Haustür.</p><p>Ergänzen Sie hier Ihre Inhalte: Liefergebiete, Lieferzeiten, Mindestbestellwert etc.</p>',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug'       => 'festservice',
                'title'      => 'Festservice',
                'menu'       => 'main',
                'sort_order' => 20,
                'active'     => true,
                'content'    => '<h2>Festservice &amp; Veranstaltungen</h2><p>Für Ihr Fest, Ihre Feier oder Ihr Event – wir sorgen für die passende Getränkeauswahl.</p><p>Ergänzen Sie hier Ihre Inhalte: Leihgeräte, Ausstattung, Kontaktformular etc.</p>',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('pages')->whereIn('slug', ['heimdienst', 'festservice'])->delete();

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn(['menu', 'sort_order', 'active']);
        });
    }
};
