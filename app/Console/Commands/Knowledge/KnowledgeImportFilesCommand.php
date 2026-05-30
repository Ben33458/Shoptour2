<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Services\Knowledge\BookStackConnector;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetFactory;
use PhpOffice\PhpWord\IOFactory as WordFactory;
use Smalot\PdfParser\Parser as PdfParser;

class KnowledgeImportFilesCommand extends Command
{
    protected $signature = 'knowledge:import-files
                            {path : Pfad zum Ordner mit Dokumenten}
                            {--book= : BookStack-Buchname (Standard = Ordnername)}
                            {--dry-run : Nur anzeigen, was importiert würde}';

    protected $description = 'Importiert DOCX/DOC/ODT/XLSX/XLS/ODS/PDF-Dateien als BookStack-Seiten';

    private const SUPPORTED = ['docx', 'doc', 'odt', 'xlsx', 'xls', 'ods', 'pdf'];
    private const SKIP_EXT  = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',
                                'mp4', 'mp3', 'zip', 'rar', '7z', 'vlg', 'filepart', 'exe'];

    public function __construct(private readonly BookStackConnector $bookstack)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $basePath = rtrim($this->argument('path'), '/');
        $dryRun   = $this->option('dry-run');

        if (!is_dir($basePath)) {
            $this->error("Verzeichnis nicht gefunden: {$basePath}");
            return self::FAILURE;
        }

        // Determine book name: option → folder name → fallback
        $bookName = $this->option('book') ?: basename($basePath);

        $this->info($dryRun ? "[DRY-RUN] " : "" . "Importiere nach Buch: \"{$bookName}\"");

        $bookId = null;
        if (!$dryRun) {
            $bookId = $this->bookstack->findOrCreateBook($bookName);
            if (!$bookId) {
                $this->error('BookStack-Buch konnte nicht gefunden oder erstellt werden.');
                return self::FAILURE;
            }
        }

        $ok = $fail = $skipped = 0;

        // Collect files: root files go directly into the book, subdirs become chapters
        $rootFiles = $this->listFiles($basePath, shallow: true);
        $subDirs   = $this->listSubDirs($basePath);

        // Root-level files
        if (!empty($rootFiles)) {
            foreach ($rootFiles as $file) {
                $this->importFile($file, $bookId, chapterName: null, dryRun: $dryRun,
                    ok: $ok, fail: $fail, skipped: $skipped);
            }
        }

        // Subdirectory files → each subdir becomes a chapter
        foreach ($subDirs as $subDir) {
            $chapterName = basename($subDir);
            $files       = $this->listFiles($subDir, shallow: false);

            if (empty($files)) {
                continue;
            }

            $chapterId = null;
            if (!$dryRun && $bookId) {
                $chapterId = $this->bookstack->findOrCreateChapter($bookId, $chapterName);
            }

            $this->line("  📁 <comment>{$chapterName}</comment>");

            foreach ($files as $file) {
                $this->importFile($file, $bookId, $chapterName, $chapterId, $dryRun,
                    $ok, $fail, $skipped);
            }
        }

        $this->newLine();
        $this->info("Fertig: {$ok} importiert, {$skipped} übersprungen, {$fail} Fehler.");

        if ($ok > 0 && !$dryRun) {
            $this->line("Nächster Schritt: <info>php artisan knowledge:sync --source=bookstack</info>");
        }

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function importFile(
        string  $file,
        ?int    $bookId,
        ?string $chapterName,
        ?int    $chapterId = null,
        bool    $dryRun    = false,
        int     &$ok       = 0,
        int     &$fail     = 0,
        int     &$skipped  = 0,
    ): void {
        $name = pathinfo($file, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $pad  = $chapterName ? '    ' : '  ';

        if (in_array($ext, self::SKIP_EXT, true)) {
            return; // silently skip images/binaries
        }

        if (!in_array($ext, self::SUPPORTED, true)) {
            $this->line("{$pad}<fg=gray>skip</> {$name}.{$ext} (Format nicht unterstützt)");
            $skipped++;
            return;
        }

        $this->output->write("{$pad}{$name}.{$ext} ... ");

        if ($dryRun) {
            $this->line("<fg=cyan>würde importiert</>");
            $ok++;
            return;
        }

        try {
            $text = $this->extract($file, $ext);

            if (!$text || trim($text) === '') {
                $this->line("<fg=yellow>leer — übersprungen</>");
                $skipped++;
                return;
            }

            $pageId = $this->bookstack->createPage($bookId, $name, $text, $chapterId);

            if ($pageId) {
                $this->line("<info>OK</> (Seite #{$pageId})");
                $ok++;
            } else {
                $this->line("<error>FEHLER</> (BookStack API)");
                $fail++;
            }
        } catch (\Throwable $e) {
            $this->line("<error>FEHLER</> " . $e->getMessage());
            $fail++;
        }
    }

    private function extract(string $file, string $ext): string
    {
        $maxSpreadsheetBytes = match ($ext) {
            'xls'  => 600 * 1024,           // old binary XLS expands ~1000× in memory
            'xlsx' => 20 * 1024 * 1024,
            'ods'  => 5 * 1024 * 1024,
            default => 20 * 1024 * 1024,
        };
        if (in_array($ext, ['xlsx', 'xls', 'ods']) && filesize($file) > $maxSpreadsheetBytes) {
            throw new \RuntimeException('Tabelle zu groß (' . round(filesize($file) / 1024) . ' KB) — übersprungen');
        }
        if (in_array($ext, ['docx', 'doc', 'odt']) && filesize($file) > 50 * 1024 * 1024) {
            throw new \RuntimeException('Dokument zu groß (>' . round(filesize($file) / 1024 / 1024) . ' MB) — übersprungen');
        }
        return match (true) {
            in_array($ext, ['docx', 'doc', 'odt']) => $this->extractWord($file),
            in_array($ext, ['xlsx', 'xls', 'ods']) => $this->extractSpreadsheet($file),
            $ext === 'pdf'                          => $this->extractPdf($file),
            default                                 => '',
        };
    }

    private function extractWord(string $file): string
    {
        $phpWord  = WordFactory::load($file);
        $lines    = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $this->collectText($element, $lines);
            }
        }

        return implode("\n", array_filter($lines, fn($l) => trim($l) !== ''));
    }

    private function collectText(mixed $element, array &$lines): void
    {
        if (method_exists($element, 'getText')) {
            $t = trim((string) $element->getText());
            if ($t !== '') {
                $lines[] = $t;
            }
        }
        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $this->collectText($child, $lines);
            }
        }
    }

    private function extractSpreadsheet(string $file): string
    {
        // ODS files padded to millions of empty rows by LibreOffice will exhaust memory
        if (str_ends_with(strtolower($file), '.ods')) {
            $xml = shell_exec("unzip -p " . escapeshellarg($file) . " content.xml 2>/dev/null");
            if ($xml && preg_match('/number-rows-repeated="(\d+)"/', $xml, $m) && (int)$m[1] > 100000) {
                throw new \RuntimeException("ODS enthält {$m[1]} wiederholte Leerzeilen — übersprungen");
            }
        }

        $spreadsheet = SpreadsheetFactory::load($file);
        $lines       = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $title = $sheet->getTitle();
            if ($title !== '') {
                $lines[] = "## {$title}";
            }

            foreach ($sheet->getRowIterator() as $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $val = trim((string) $cell->getFormattedValue());
                    if ($val !== '') {
                        $cells[] = $val;
                    }
                }
                if (!empty($cells)) {
                    $lines[] = implode(' | ', $cells);
                }
            }
        }

        return implode("\n", $lines);
    }

    private function extractPdf(string $file): string
    {
        if (filesize($file) > 30 * 1024 * 1024) {
            throw new \RuntimeException('PDF zu groß (>' . round(filesize($file) / 1024 / 1024) . ' MB) — übersprungen');
        }

        $parser = new PdfParser();
        $pdf    = $parser->parseFile($file);
        return $pdf->getText();
    }

    /** List direct files in a directory. */
    private function listFiles(string $dir, bool $shallow): array
    {
        $files = [];
        $items = $shallow
            ? glob("{$dir}/*")
            : $this->globRecursive("{$dir}/*");

        foreach ($items ?? [] as $item) {
            if (is_file($item)) {
                $files[] = $item;
            }
        }

        return $files;
    }

    /** List immediate subdirectories. */
    private function listSubDirs(string $dir): array
    {
        return array_filter(glob("{$dir}/*") ?: [], 'is_dir');
    }

    private function globRecursive(string $pattern): array
    {
        $files = glob($pattern) ?: [];
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $files = array_merge($files, $this->globRecursive($dir . '/' . basename($pattern)));
        }
        return $files;
    }
}
