<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Services\Knowledge\BookStackConnector;
use Illuminate\Console\Command;

class KnowledgeRestructureWikiCommand extends Command
{
    protected $signature = 'knowledge:restructure-wiki
                            {--dry-run : Nur anzeigen, was verschoben würde}
                            {--skip-book= : Buch-Namen überspringen (Komma-getrennt)}';

    protected $description = 'Verschiebt BookStack-Seiten automatisch in thematische Bücher';

    private const BOOKS = [
        'Preislisten & Angebote',
        'Produkte & Sortiment',
        'Kunden & Lieferung',
        'Technik & Geräte',
        'Personal & Schichten',
        'Betrieb & Prozesse',
        'Kolabri Website',
        'Getraenke-Kehr',
        'Allgemein',
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

        $dryRun   = $this->option('dry-run');
        $skipBooks = array_filter(
            array_map('trim', explode(',', (string) $this->option('skip-book'))),
            fn($b) => $b !== '',
        );

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . 'Lade alle BookStack-Seiten...');
        $pages = $this->bookstack->listAllPages();
        $this->info(count($pages) . ' Seiten gefunden.');

        // Pre-create all target books (unless dry-run)
        $bookIds = [];
        if (!$dryRun) {
            $this->info('Erstelle Zielbücher...');
            foreach (self::BOOKS as $bookName) {
                $id = $this->bookstack->findOrCreateBook($bookName);
                if (!$id) {
                    $this->error("Konnte Buch '{$bookName}' nicht anlegen.");
                    return self::FAILURE;
                }
                $bookIds[$bookName] = $id;
            }
        }

        $moved = $skipped = $errors = 0;
        $stats = array_fill_keys(self::BOOKS, 0);

        foreach ($pages as $page) {
            $pageId      = (int) $page['id'];
            $title       = (string) ($page['name'] ?? '');
            $currentBook = (string) ($page['book_name'] ?? '');

            if (in_array($currentBook, $skipBooks, true)) {
                $skipped++;
                continue;
            }

            $targetBook = $this->categorize($title, $currentBook);

            if ($currentBook === $targetBook) {
                $skipped++;
                continue;
            }

            $stats[$targetBook] = ($stats[$targetBook] ?? 0) + 1;

            if ($dryRun) {
                $this->line("  <fg=cyan>{$title}</> → <info>{$targetBook}</info> (war: {$currentBook})");
                $moved++;
                continue;
            }

            $targetId = $bookIds[$targetBook] ?? null;
            if (!$targetId) {
                $this->line("  <error>FEHLER</> Kein ID für Buch '{$targetBook}'");
                $errors++;
                continue;
            }

            if ($this->bookstack->movePage($pageId, $targetId)) {
                $this->line("  <info>OK</> #{$pageId} \"{$title}\" → {$targetBook}");
                $moved++;
            } else {
                $this->line("  <error>FEHLER</> #{$pageId} \"{$title}\"");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Fertig: {$moved} verschoben, {$skipped} übersprungen, {$errors} Fehler.");

        if ($moved > 0) {
            $this->newLine();
            $this->line('Verteilung:');
            foreach ($stats as $book => $count) {
                if ($count > 0) {
                    $this->line("  {$book}: {$count}");
                }
            }
        }

        if ($moved > 0 && !$dryRun) {
            $this->newLine();
            $this->line('Nächster Schritt: <info>php artisan knowledge:sync --source=bookstack</info>');
        }

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function categorize(string $title, string $bookName): string
    {
        $lowerBook  = strtolower($bookName);
        $lowerTitle = strtolower($title);

        // Web-crawl: Buchname enthält kolabri.de
        if (str_contains($lowerBook, 'kolabri.de')) {
            return 'Kolabri Website';
        }
        // Web-crawl: Seitentitel endet auf "– Kolabri Getränke" (gescrapte Website-Seiten)
        if (str_ends_with($title, '– Kolabri Getränke') || str_ends_with($title, '- Kolabri Getränke')) {
            return 'Kolabri Website';
        }

        // Getraenke-Kehr: Buchname oder Titel enthält kehr
        if (str_contains($lowerBook, 'kehr') || str_contains($lowerTitle, 'kehr') || str_contains($lowerBook, 'getraenke-kehr')) {
            return 'Getraenke-Kehr';
        }

        // Bestellzettel / Bestellformulare → Kunden & Lieferung
        if (preg_match('/bestellzettel|bestellformul|bestellliste|bestell.?vorlage/i', $lowerTitle)) {
            return 'Kunden & Lieferung';
        }

        // Preislisten
        if (preg_match('/preis|vk[- _]|ek[- _]|\bpreisliste\b|\bangebot\b|rabatt|kondition|verkaufspreise|einkaufspreise/i', $lowerTitle)) {
            return 'Preislisten & Angebote';
        }

        // Betrieb & Prozesse — vor Produkte prüfen, damit "Arbeitsanweisung" korrekt landet
        if (preg_match('/ablauf|prozess|anleitung|arbeitsanweisung|vorgehen|was tun|schritt|checkliste|handbuch|einweisung|öffnungszeit|lager\b|fifo|fefo|kommission|verräum|inventur/i', $lowerTitle)) {
            return 'Betrieb & Prozesse';
        }

        // Produkte & Sortiment
        if (preg_match('/club.?mate|wostok|limonad|bier|wein|saft|cider|eistee|mate[- ]|gin\b|vodka|rum|whisky|schnaps|korn\b|sortiment|getränk|produkt|marke\b|hersteller|artikel|pfand|gebinde/i', $lowerTitle)) {
            return 'Produkte & Sortiment';
        }

        // Kunden & Lieferung
        if (preg_match('/lieferung|lieferschein|liefertour|tourplan|tour[- _]|fahrer|kunden|bestellung|abhol|vollgut|leergut|rückgabe|reklamation|heimdienst/i', $lowerTitle)) {
            return 'Kunden & Lieferung';
        }

        // Technik & Geräte
        if (preg_match('/ec.?gerät|kasse\b|kassensystem|scanner|drucker|technik|\bit\b|software|hardware|wlan|internet|netzwerk|server|backup|terminal|pos\b|ls-pos/i', $lowerTitle)) {
            return 'Technik & Geräte';
        }

        // Personal & Schichten
        if (preg_match('/mitarbeiter|personal|urlaub|schicht|stempel|krank|arbeitszeit|lohn|gehalt|onboarding|einarbeitung/i', $lowerTitle)) {
            return 'Personal & Schichten';
        }

        return 'Allgemein';
    }
}
