<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Services\Knowledge\BookStackConnector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class KnowledgeCrawlWebCommand extends Command
{
    protected $signature = 'knowledge:crawl-web
                            {url : Start-URL (z.B. https://www.kolabri.de)}
                            {--depth=2 : Maximale Linktiefe}
                            {--book= : BookStack-Buchname (Standard = Domain)}
                            {--skip= : Kommagetrennte URL-Pfad-Muster überspringen (z.B. /tag/,/category/)}
                            {--dry-run : Nur anzeigen, was gecrawlt würde}';

    protected $description = 'Crawlt eine Website und importiert Seiten als BookStack-Pages';

    private const MAX_PAGES     = 150;
    private const CRAWL_DELAY_MS = 300;

    public function __construct(private readonly BookStackConnector $bookstack)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $startUrl = rtrim($this->argument('url'), '/');
        $maxDepth = max(0, (int) $this->option('depth'));
        $dryRun   = $this->option('dry-run');

        $parsed = parse_url($startUrl);
        if (!$parsed || empty($parsed['host'])) {
            $this->error("Ungültige URL: {$startUrl}");
            return self::FAILURE;
        }

        $scheme   = $parsed['scheme'] ?? 'https';
        $domain   = $parsed['host'];
        $baseUrl  = "{$scheme}://{$domain}";
        $bookName = $this->option('book') ?: $domain;

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Crawle {$startUrl} (Tiefe: {$maxDepth}) → Buch \"{$bookName}\"");

        $bookId = null;
        if (!$dryRun) {
            $bookId = $this->bookstack->findOrCreateBook($bookName);
            if (!$bookId) {
                $this->error('BookStack-Buch konnte nicht gefunden oder erstellt werden.');
                return self::FAILURE;
            }
        }

        $skipPatterns = array_filter(
            array_map('trim', explode(',', (string) $this->option('skip'))),
            fn($p) => $p !== '',
        );

        $visited = [];
        $queue   = [[$startUrl, 0]];
        $ok = $fail = $skip = 0;

        while (!empty($queue) && ($ok + $fail + $skip) < self::MAX_PAGES) {
            [$url, $depth] = array_shift($queue);

            $normalised = $this->normalise($url);
            if (isset($visited[$normalised])) {
                continue;
            }
            $visited[$normalised] = true;

            if ($this->shouldSkip($url, $skipPatterns)) {
                $this->output->write("  {$url} ... ");
                $this->line('<fg=gray>übersprungen (skip-Muster)</>');
                $skip++;
                continue;
            }

            $this->output->write("  {$url} ... ");

            try {
                $response = Http::timeout(15)
                    ->withHeaders(['User-Agent' => 'Kolabri-KnowledgeBot/1.0'])
                    ->get($url);

                if (!$response->successful()) {
                    $this->line("<fg=yellow>HTTP {$response->status()}</>");
                    $skip++;
                    continue;
                }

                $contentType = $response->header('Content-Type', '');
                if (!str_contains($contentType, 'text/html')) {
                    $this->line('<fg=gray>kein HTML — übersprungen</>');
                    $skip++;
                    continue;
                }

                $html  = $response->body();
                $title = $this->extractTitle($html) ?: $url;
                $text  = $this->extractText($html);

                if (empty(trim($text))) {
                    $this->line('<fg=yellow>leer — übersprungen</>');
                    $skip++;
                } elseif ($dryRun) {
                    $this->line("<fg=cyan>würde importiert</> [{$title}]");
                    $ok++;
                } else {
                    $pageId = $this->bookstack->createPage($bookId, $title, $text);
                    if ($pageId) {
                        $this->line("<info>OK</> (Seite #{$pageId}) [{$title}]");
                        $ok++;
                    } else {
                        $this->line('<error>FEHLER</> (BookStack API)');
                        $fail++;
                    }
                }

                // Enqueue child links within depth limit
                if ($depth < $maxDepth) {
                    foreach ($this->extractLinks($html, $baseUrl, $url) as $link) {
                        $normLink = $this->normalise($link);
                        if (!isset($visited[$normLink]) && !$this->shouldSkip($link, $skipPatterns)) {
                            $queue[] = [$link, $depth + 1];
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->line('<error>FEHLER</> ' . $e->getMessage());
                $fail++;
            }

            if (!$dryRun) {
                usleep(self::CRAWL_DELAY_MS * 1000);
            }
        }

        $this->newLine();
        $this->info("Fertig: {$ok} importiert, {$skip} übersprungen, {$fail} Fehler.");

        if ($ok > 0 && !$dryRun) {
            $this->line("Nächster Schritt: <info>php artisan knowledge:sync --source=bookstack</info>");
        }

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            return html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }

    private function extractText(string $html): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);

        $xpath = new \DOMXPath($dom);

        // Remove non-content elements
        foreach ($xpath->query('//script|//style|//noscript|//iframe|//nav|//header|//footer|//aside|//form') as $node) {
            $node->parentNode?->removeChild($node);
        }

        // Prefer <main> or <article>, fall back to <body>
        $main = $xpath->query('//main') ?: null;
        if ($main && $main->length > 0) {
            $root = $main->item(0);
        } else {
            $article = $xpath->query('//article');
            $root = ($article && $article->length > 0)
                ? $article->item(0)
                : $dom->getElementsByTagName('body')->item(0);
        }

        if (!$root) {
            return '';
        }

        $text = $root->textContent ?? '';
        // Collapse whitespace
        $text = preg_replace('/\h+/', ' ', $text);
        $text = preg_replace('/(\s*\n\s*){3,}/', "\n\n", $text);
        return trim($text);
    }

    private function extractLinks(string $html, string $baseUrl, string $currentUrl): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);

        $links = [];
        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = trim((string) $a->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $absolute = $this->toAbsolute($href, $baseUrl, $currentUrl);
            if ($absolute && str_starts_with($absolute, $baseUrl)) {
                // Strip fragment and trailing slash
                $clean = strtok($absolute, '#');
                $clean = rtrim($clean, '/');
                if ($clean && $clean !== rtrim($currentUrl, '/')) {
                    $links[] = $clean;
                }
            }
        }

        return array_unique($links);
    }

    private function toAbsolute(string $href, string $baseUrl, string $currentUrl): ?string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?? 'https';
            return $scheme . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $baseUrl . $href;
        }
        // Relative path
        $base = preg_replace('/\/[^\/]*$/', '/', $currentUrl);
        return $base . $href;
    }

    private function shouldSkip(string $url, array $patterns): bool
    {
        if (empty($patterns)) {
            return false;
        }
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        foreach ($patterns as $pattern) {
            if (str_contains($path, $pattern) || str_contains($url, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function normalise(string $url): string
    {
        return strtolower(rtrim(strtok($url, '#'), '/'));
    }
}
