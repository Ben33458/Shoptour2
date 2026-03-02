<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content');
            $table->timestamps();
        });

        // Standard-Seiten für rechtliche Pflichtangaben
        $now = now();
        DB::table('pages')->insert([
            [
                'slug'       => 'impressum',
                'title'      => 'Impressum',
                'content'    => '<h2>Impressum</h2><p><strong>Angaben gemäß § 5 TMG</strong></p><p>[Firmenname]<br>[Straße Hausnummer]<br>[PLZ Ort]</p><p><strong>Kontakt</strong><br>Telefon: [Telefonnummer]<br>E-Mail: [E-Mail-Adresse]</p><p><strong>Umsatzsteuer-ID</strong><br>Umsatzsteuer-Identifikationsnummer gemäß § 27a Umsatzsteuergesetz: [USt-IdNr.]</p><p><em>Bitte Platzhalter in eckigen Klammern durch echte Angaben ersetzen.</em></p>',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug'       => 'datenschutz',
                'title'      => 'Datenschutzerklärung',
                'content'    => '<h2>Datenschutzerklärung</h2><p><strong>1. Verantwortlicher</strong></p><p>[Firmenname], [Adresse], [E-Mail]</p><p><strong>2. Erhebung und Verarbeitung personenbezogener Daten</strong></p><p>Wir erheben und verarbeiten personenbezogene Daten nur, soweit dies zur Erbringung unserer Leistungen erforderlich ist.</p><p><strong>3. Ihre Rechte</strong></p><p>Sie haben das Recht auf Auskunft, Berichtigung, Löschung und Einschränkung der Verarbeitung Ihrer personenbezogenen Daten.</p><p><em>Bitte Platzhalter in eckigen Klammern durch echte Angaben ersetzen und vollständige Datenschutzerklärung ergänzen.</em></p>',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug'       => 'agb',
                'title'      => 'Allgemeine Geschäftsbedingungen',
                'content'    => '<h2>Allgemeine Geschäftsbedingungen</h2><p><strong>§ 1 Geltungsbereich</strong></p><p>Diese Allgemeinen Geschäftsbedingungen gelten für alle Bestellungen, die über unseren Online-Shop abgeschlossen werden.</p><p><strong>§ 2 Vertragsschluss</strong></p><p>Die Darstellung der Produkte im Online-Shop stellt kein rechtlich bindendes Angebot dar.</p><p><em>Bitte vollständige AGB durch einen Rechtsanwalt erstellen lassen.</em></p>',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug'       => 'widerruf',
                'title'      => 'Widerrufsrecht',
                'content'    => '<h2>Widerrufsrecht</h2><p><strong>Widerrufsbelehrung</strong></p><p>Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.</p><p>Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat.</p><p><em>Bitte vollständige Widerrufsbelehrung durch einen Rechtsanwalt prüfen lassen.</em></p>',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
