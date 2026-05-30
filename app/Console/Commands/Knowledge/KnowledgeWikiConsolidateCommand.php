<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Models\Knowledge\KnowledgeDocument;
use App\Services\Knowledge\BookStackConnector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class KnowledgeWikiConsolidateCommand extends Command
{
    protected $signature = 'knowledge:wiki-consolidate
                            {--phase=all : delete|dedup|summarize|rebuild|all}
                            {--dry-run : Nur anzeigen, was gemacht würde}';

    protected $description = 'Bereinigt BookStack: löscht Junk, dedupliziert Seiten, fasst Inhalte per LLM zusammen';

    // ── Phase 1: Löschen ─────────────────────────────────────────────────────

    private const DELETE_TITLES = [
        // Autorseiten (kein Wissenswert)
        'Paula – Kolabri Getränke',
        'Paul Schmoranzer – Kolabri Getränke',
        'Benedikt Schneider – Kolabri Getränke',

        // Rechtliche/Meta-Seiten
        'Impressum – Kolabri Getränke',
        'Datenschutzerklärung – Kolabri Getränke',
        'Kein Zugriff – Kolabri Getränke',

        // Blogartikel ohne Produktbezug
        'Döner essen ohne Knoblauch-Fahne? Papa Türk hilft! – Kolabri Getränke',
        'Kein UPS mehr seit dem 31.07.2024 – Kolabri Getränke',

        // Reiner Junk / leere Seiten
        'karnevaaa2l',
        'bc9d9011-1a13-4ecb-a932-771dbcae71d8',
        'Unbenannt 1',
        '59x19_kolabri_Logo_fuer_aufkleber',
        'Kolabri_Banner3x2',
        'Kolabri_Banner3x2_V2',
        'JTL-Wawi-Bildexport-29032021',
        'JTL-Wawi-Bildexport-296666032021',
        'jtl_Vorlagen_abmessungen_kalkulation',
        'allgemeinerkaufvertrag_von_privat_ebay',
        'SEPA_Lastschriftmandat_Kolabri',
        'Muster Stundennachweis 2015(1)',
        'NICHT AKTUELL190429_Leih_und_Lieferbedingungen_Kuehlschraenke_tische_garnituren',
        'todo_prioritaetenmatrix',
        '250813_anzeigeKerb25',
        '161109_LimoLounge_Stellenausschreibung',
        'Abreisszettel (1)',
        'projekt_schaufensterregal',
        'kolabri_Quiz',
        'geldboersenprotokoll',
        'Zeitschaltuhr_aussenwerbung_digi_322_jf',

        // Alte Finanzdaten (veraltet + sensibel)
        '20_offene_rechnungen',
        '2122_offene_rechnungen',
        '21_offene_belege',
        '22_offene_rechnungen',
        'offene_auftraege_liste',

        // Veraltete eigene Preislisten / Inventuren
        '130603_preisliste_kolabri',
        '160125_inventur_liste_kolabri',
        '160128_kundk_bestellung',
        '161219DP Kolabri Monat (1)',
        '161219Wunschtermine',
        '180912_Preisvergleich_Toilettenpapier_Haushaltswaren',
        '220103_Lagerbewertung_ohne_Pfand',
        '20_Umsatz_Spirituosen_Dosen_Süssigkeiten_sonstiges',

        // MHD Sync-Konflikt-Kopien (alt)
        'MHD_Artikelliste (Com-PCs in Konflikt stehende Kopie 2016-06-20)',
        'MHD_Artikelliste (Fox Kolabris in Konflikt stehende Kopie 2016-06-11)',
        'MHD_Artikelliste (Mobilbar Darmstadts in Konflikt stehende Kopie 2016-06-10)',
        'MHD_Artikelliste (Mobilbar Darmstadts in Konflikt stehende Kopie 2017-01-04)',
        'MHD_Artikelliste (Mobilbar Darmstadts in Konflikt stehende Kopie 2017-01-04) (2)',

        // Alte Tourpläne (nur 2026 = "26_" behalten)
        '25_Heimdienst-Tourenplan',
        '25_kalender_heimdienst_komplett',
        '25_kalender_heimdienst_modau',
        '25_kalender_heimdienst_roßdorf',
        '241023_heimdienstliste20',
        '260119_heimdienstliste20',

        // Altes POS-Handbuch (nur 1.8.0.0 behalten)
        'LS-POS_1.7.0.2_Handbuch',

        // Alte Kommunikationsdaten
        '210316_FRITZ!Box_Anrufliste',
        '210316_anzahl_anrufe_unterdrueckt',

        // Niederwertiger sonstiger Content
        '200429_Stromkabel-Zuordnung_Markt',
        'LED_Plan_fuer_Spirituosenlager',
        '230327_Kolabri_getraenkekoenner',
        'Putzkram_Sollbestandsaufnahme',
        'Hochzeite_event_kalkulation_und_planung',
        'osthang_verfuegbarkeit',
        '210101_Mindestbestand_trinks',
        '180302_Craftbeer_Liste',
        'da gross',
        'modau',
        'OR 26 zusammengefügt',
    ];

    // ── Phase 3: LLM-Zusammenfassungen ───────────────────────────────────────

    private const SUMMARIZE_GROUPS = [
        [
            'title'    => 'Produktwissen: Limonaden, Mate & Erfrischungsgetränke',
            'book'     => 'Produkte & Sortiment',
            'match'    => ['Club Mate', 'Wostok', 'Thomas Henry', 'Mio Mio', 'Almdudler',
                          'BioZisch', 'Biozisch', 'Kräuterbraut', 'Flora Power', 'Bernadett',
                          'Cucumis', 'Proviant', 'Rhabarberschorle', 'Unsere Limonaden',
                          'Ginger Beer', 'Spezi', 'mineralwasser_mineralienvergleich',
                          'Unsere Mate-Getränke', 'Limonaden- und Getränkemarkt', 'Sortiment & Preise'],
            'prompt'   => 'Erstelle eine strukturierte Wiki-Seite „Produktwissen: Limonaden, Mate & Erfrischungsgetränke" für Mitarbeiter von Kolabri Getränke. Extrahiere aus den Quelltexten alle Marken und Produkte, die wir führen. Pro Marke: Was ist es? Welche Sorten gibt es bei uns? Halte es kompakt. Schreibe AUSSCHLIESSLICH auf Deutsch. Markdown-Format mit ## pro Marke.',
        ],
        [
            'title'    => 'Produktwissen: Bier & Craft Beer',
            'book'     => 'Produkte & Sortiment',
            'match'    => ['Braustübl', 'Grohe', 'Pfungstädter', 'Faust Bier', 'Brauerei Faust',
                          'Craft Beer', 'Ayinger', 'Bayrisches Bier', 'Local Heroes', 'Biber',
                          'fassbiere', 'bierkalku'],
            'prompt'   => 'Erstelle eine strukturierte Wiki-Seite „Produktwissen: Bier & Craft Beer" für Mitarbeiter von Kolabri Getränke. Extrahiere Biermarken und -sorten. Pro Brauerei: Woher kommt das Bier? Welche Sorten führen wir? Schreibe AUSSCHLIESSLICH auf Deutsch. Markdown-Format mit ## pro Marke.',
        ],
        [
            'title'    => 'Produktwissen: Cider, Wein & Apfelwein',
            'book'     => 'Produkte & Sortiment',
            'match'    => ['Bulmers', 'Apfelweine', 'Apfelwein', 'Dölp', 'Cider', 'Kelterei',
                          'Unsere Auswahl an Cider'],
            'prompt'   => 'Erstelle eine Wiki-Seite „Produktwissen: Cider, Wein & Apfelwein" für Mitarbeiter. Pro Produkt: Was ist es? Besonderheiten? Sorten? Schreibe AUSSCHLIESSLICH auf Deutsch. Markdown mit ## pro Produkt.',
        ],
        [
            'title'    => 'Produktwissen: Eistee',
            'book'     => 'Produkte & Sortiment',
            'match'    => ['Eistee', 'Burkhardt', 'Trade Islands', 'Elephant Bay', "Richard's SUN",
                          'Fairer Eistee'],
            'prompt'   => 'Erstelle eine Wiki-Seite „Produktwissen: Eistee" für Mitarbeiter. Pro Marke: Was macht sie besonders? Sorten? Schreibe AUSSCHLIESSLICH auf Deutsch. Markdown mit ## pro Marke.',
        ],
        [
            'title'    => 'Produktwissen: Spirituosen',
            'book'     => 'Produkte & Sortiment',
            'match'    => ['Gin', 'Birkenhof', 'Brände', 'Liköre', 'Spirituosen', 'August Gin'],
            'prompt'   => 'Erstelle eine Wiki-Seite „Produktwissen: Spirituosen" für Mitarbeiter. Pro Marke/Produkt: Was ist es? Besonderheiten? Sorten? Schreibe AUSSCHLIESSLICH auf Deutsch. Markdown mit ## pro Produkt.',
        ],
        [
            'title'    => 'MHD-gefährdete Produkte (Erfahrungsliste)',
            'book'     => 'Produkte & Sortiment',
            'match'    => ['MHD_Artikelliste', 'MHD-Liste', 'MHD_Artikelliste (2)', '250416_MHD'],
            'prompt'   => 'Fasse diese MHD-Artikellisten zusammen zu einer Seite „MHD-gefährdete Produkte". Welche Produktgruppen/Artikel sind besonders MHD-empfindlich? Hinweis: Die Daten sind nicht mehr aktuell, zeigen aber historische Probleme. Schreibe AUSSCHLIESSLICH auf Deutsch. Markdown-Format.',
        ],
        [
            'title'    => 'Betrieb: Checklisten & Abläufe',
            'book'     => 'Betrieb & Prozesse',
            'match'    => ['checkliste_Kolabri_Markt', '190401_17UhrCheckliste', '19_Protokolle'],
            'prompt'   => 'Fasse diese Checklisten zusammen. Extrahiere wichtige Aufgaben, ordne sie täglich/wöchentlich/monatlich. Hinweis: Möglicherweise veraltet. Schreibe AUSSCHLIESSLICH auf Deutsch. Markdown-Format.',
        ],
        [
            'title'    => 'Getränke, die wir nicht mehr führen',
            'book'     => 'Produkte & Sortiment',
            'match'    => ['Getränke, die es nicht mehr gibt', 'Genussvolle Winterwärme',
                          'Öffnungszeiten an Weihnachten'],
            'prompt'   => 'Extrahiere: welche Getränke hat Kolabri nicht mehr im Sortiment, welche sind saisonal? Erstelle „Getränke, die wir nicht mehr führen (+ Saisonartikel)". Schreibe AUSSCHLIESSLICH auf Deutsch. Markdown.',
        ],
    ];

    public function __construct(private readonly BookStackConnector $bookstack)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->bookstack->isConfigured()) {
            $this->error('BookStack ist nicht konfiguriert.');
            return self::FAILURE;
        }

        $phase  = (string) $this->option('phase');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Phase: {$phase}");

        $allPages = $this->bookstack->listAllPages();
        $this->info(count($allPages) . ' Seiten geladen.');

        $errors = 0;

        if (in_array($phase, ['all', 'delete'], true)) {
            $errors += $this->runDeletePhase($allPages, $dryRun);
            // Reload pages after deletions
            if (!$dryRun) {
                $allPages = $this->bookstack->listAllPages();
            }
        }

        if (in_array($phase, ['all', 'dedup'], true)) {
            $errors += $this->runDedupPhase($allPages, $dryRun);
            if (!$dryRun) {
                $allPages = $this->bookstack->listAllPages();
            }
        }

        if (in_array($phase, ['all', 'summarize'], true)) {
            $errors += $this->runSummarizePhase($allPages, $dryRun);
        }

        if (!$dryRun && in_array($phase, ['all', 'rebuild'], true)) {
            $this->runRebuildIndex();
        }

        $this->newLine();
        $this->info($errors === 0 ? 'Fertig ohne Fehler.' : "Fertig mit {$errors} Fehlern.");
        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    // ── Phase 1: Löschen ─────────────────────────────────────────────────────

    private function runDeletePhase(array $allPages, bool $dryRun): int
    {
        $this->newLine();
        $this->line('<comment>── Phase 1: Löschen ──────────────────────────────────</comment>');

        $deleteTitles = array_map('strtolower', self::DELETE_TITLES);
        $deleted = $errors = 0;

        foreach ($allPages as $page) {
            $title = (string) ($page['name'] ?? '');
            if (!in_array(strtolower($title), $deleteTitles, true)) {
                continue;
            }

            $pageId = (int) $page['id'];
            if ($dryRun) {
                $this->line("  [DEL] #{$pageId} \"{$title}\"");
                $deleted++;
                continue;
            }

            if ($this->bookstack->deletePage($pageId)) {
                $this->line("  <info>OK</> #{$pageId} \"{$title}\"");
                $deleted++;
            } else {
                $this->line("  <error>FEHLER</> #{$pageId} \"{$title}\"");
                $errors++;
            }
        }

        $this->info("  → {$deleted} gelöscht, {$errors} Fehler.");
        return $errors;
    }

    // ── Phase 2: Deduplizieren ───────────────────────────────────────────────

    private function runDedupPhase(array $allPages, bool $dryRun): int
    {
        $this->newLine();
        $this->line('<comment>── Phase 2: Deduplizieren ────────────────────────────</comment>');

        // Group by normalised title
        $groups = [];
        foreach ($allPages as $page) {
            $key = strtolower(trim((string) ($page['name'] ?? '')));
            $groups[$key][] = $page;
        }

        $deleted = $errors = 0;
        foreach ($groups as $title => $pages) {
            if (count($pages) <= 1) {
                continue;
            }
            // Keep the one with the highest ID (most recent import)
            usort($pages, fn($a, $b) => (int) $b['id'] <=> (int) $a['id']);
            $keep = array_shift($pages);

            foreach ($pages as $page) {
                $pageId = (int) $page['id'];
                if ($dryRun) {
                    $this->line("  [DEDUP] #{$pageId} \"{$page['name']}\" (behalte #{$keep['id']})");
                    $deleted++;
                    continue;
                }

                if ($this->bookstack->deletePage($pageId)) {
                    $this->line("  <info>OK</> #{$pageId} \"{$page['name']}\" (Duplikat)");
                    $deleted++;
                } else {
                    $this->line("  <error>FEHLER</> #{$pageId} Dedup");
                    $errors++;
                }
            }
        }

        $this->info("  → {$deleted} Duplikate entfernt, {$errors} Fehler.");
        return $errors;
    }

    // ── Phase 3: LLM-Zusammenfassungen ───────────────────────────────────────

    private function runSummarizePhase(array $allPages, bool $dryRun): int
    {
        $this->newLine();
        $this->line('<comment>── Phase 3: LLM-Zusammenfassungen ───────────────────</comment>');

        $errors = 0;

        foreach (self::SUMMARIZE_GROUPS as $group) {
            $groupTitle = $group['title'];
            $this->output->write("  {$groupTitle} ... ");

            // Find matching pages by title keywords
            $matchingPages = $this->findMatchingPages($allPages, $group['match']);
            if (empty($matchingPages)) {
                $this->line('<fg=yellow>keine passenden Seiten gefunden — übersprungen</>');
                continue;
            }

            $this->output->write(count($matchingPages) . ' Seiten');

            // Fetch content from KnowledgeDocument
            $content = $this->fetchContent($matchingPages);
            if (empty(trim($content))) {
                $this->line(' <fg=yellow>kein Inhalt — übersprungen</>');
                continue;
            }

            if ($dryRun) {
                $this->line(' <fg=cyan>[DRY-RUN] würde erstellt</>');
                foreach ($matchingPages as $p) {
                    $this->line("    - #{$p['id']} \"{$p['name']}\"");
                }
                continue;
            }

            // Call LLM
            $this->output->write(' → LLM ... ');
            $summary = $this->summarize($group['prompt'], $content);
            if (!$summary) {
                $this->line('<error>LLM-Fehler</>');
                $errors++;
                continue;
            }

            // Create consolidated page in BookStack
            $bookId = $this->bookstack->findOrCreateBook($group['book']);
            if (!$bookId) {
                $this->line('<error>Buch nicht gefunden</>');
                $errors++;
                continue;
            }

            $pageId = $this->bookstack->createMarkdownPage($bookId, $groupTitle, $summary);
            if (!$pageId) {
                $this->line('<error>Seite nicht erstellt</>');
                $errors++;
                continue;
            }

            $this->line("<info>OK</> (Seite #{$pageId})");

            // Delete the original individual pages
            foreach ($matchingPages as $p) {
                $this->bookstack->deletePage((int) $p['id']);
            }
        }

        return $errors;
    }

    private function findMatchingPages(array $allPages, array $keywords): array
    {
        $matches = [];
        foreach ($allPages as $page) {
            $title = strtolower((string) ($page['name'] ?? ''));
            foreach ($keywords as $kw) {
                if (str_contains($title, strtolower($kw))) {
                    $matches[] = $page;
                    break;
                }
            }
        }
        return $matches;
    }

    private function fetchContent(array $pages): string
    {
        $parts     = [];
        $wordCount = 0;
        $maxWords  = 2500;

        foreach ($pages as $page) {
            $text = $this->bookstack->getPageText((int) $page['id']);
            if (empty($text)) {
                continue;
            }

            $words = str_word_count($text);
            if ($wordCount + $words > $maxWords) {
                $text = implode(' ', array_slice(explode(' ', $text), 0, max(50, $maxWords - $wordCount)));
            }

            $parts[]    = "## {$page['name']}\n{$text}";
            $wordCount += $words;

            if ($wordCount >= $maxWords) {
                break;
            }
        }

        return implode("\n\n---\n\n", $parts);
    }

    private function summarize(string $prompt, string $content): ?string
    {
        $ollamaUrl = rtrim((string) config('knowledge.ollama_url', 'http://ollama:11434'), '/');
        $model     = (string) config('knowledge.ollama_chat_model', 'llama3');

        try {
            $response = Http::timeout(180)
                ->post("{$ollamaUrl}/v1/chat/completions", [
                    'model'       => $model,
                    'max_tokens'  => 1200,
                    'temperature' => 0.3,
                    'messages'    => [
                        ['role' => 'system', 'content' => 'Du bist Wissens-Redakteur für Kolabri Getränke. WICHTIG: Antworte AUSSCHLIESSLICH auf Deutsch. Kein Englisch. Schreibe strukturiert, klar und kompakt.'],
                        ['role' => 'user',   'content' => "ANTWORTE AUF DEUTSCH.\n\n" . $prompt . "\n\nQuelltexte:\n\n" . $content],
                    ],
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content', null);
            }
        } catch (\Throwable $e) {
            $this->line(' LLM-Exception: ' . $e->getMessage());
        }

        return null;
    }

    // ── Phase 4: Qdrant + DB neu aufbauen ────────────────────────────────────

    private function runRebuildIndex(): void
    {
        $this->newLine();
        $this->line('<comment>── Phase 4: Index neu aufbauen ──────────────────────</comment>');

        $count = KnowledgeDocument::where('source_type', 'bookstack')->count();
        $this->info("  Lösche {$count} BookStack-Einträge aus DB ...");
        KnowledgeDocument::where('source_type', 'bookstack')->delete();

        $this->info('  Starte knowledge:sync --source=bookstack ...');
        $this->call('knowledge:sync', ['--source' => 'bookstack']);
    }
}
