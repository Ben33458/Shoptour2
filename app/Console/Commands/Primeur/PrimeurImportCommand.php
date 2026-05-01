<?php

declare(strict_types=1);

namespace App\Console\Commands\Primeur;

use App\Models\Primeur\PrimeurImportRun;
use App\Services\Primeur\PrimeurImportService;
use Illuminate\Console\Command;

class PrimeurImportCommand extends Command
{
    protected $signature = 'primeur:import
        {--phase=all : customers|articles|orders|cash_receipts|cash_daily|cash_sessions|all}
        {--year= : Nur ein bestimmtes Jahr importieren (z.B. 2021)}
        {--force : Bereits importierte Daten überschreiben}
        {--dry-run : Nur analysieren, nichts importieren}';

    protected $description = 'Importiert IT-Drink/Primeur-Altdaten aus /srv/shoptour2/primeur_raw/IT_Drink/';

    public function handle(PrimeurImportService $service): int
    {
        $phase  = $this->option('phase');
        $year   = $this->option('year') ? (int) $this->option('year') : null;
        $force  = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $service->setOutput($this->output);
        $service->setDryRun($dryRun);
        $service->setForce($force);

        $phases = $phase === 'all'
            ? ['customers', 'articles', 'orders', 'cash_daily', 'cash_receipts', 'cash_sessions']
            : [$phase];

        foreach ($phases as $p) {
            $this->info("");
            $this->info("=== Phase: {$p} ===");
            $run = PrimeurImportRun::create([
                'source' => 'IT_Drink',
                'phase'  => $p,
                'status' => 'running',
                'started_at' => now(),
            ]);

            try {
                $result = match ($p) {
                    'customers'     => $service->importCustomers($run),
                    'articles'      => $service->importArticles($run),
                    'orders'        => $service->importOrders($run),
                    'cash_daily'    => $service->importCashDaily($run, $year),
                    'cash_receipts' => $service->importCashReceipts($run, $year),
                    'cash_sessions' => $service->importCashSessions($run, $year),
                    default         => throw new \InvalidArgumentException("Unbekannte Phase: {$p}"),
                };

                $run->update([
                    'status' => 'completed',
                    'records_imported' => $result['imported'],
                    'records_skipped'  => $result['skipped'],
                    'notes'            => $result['notes'] ?? null,
                    'finished_at'      => now(),
                ]);

                $this->info("  Importiert: {$result['imported']} | Übersprungen: {$result['skipped']}");
            } catch (\Throwable $e) {
                $run->update([
                    'status' => 'failed',
                    'notes' => $e->getMessage(),
                    'finished_at' => now(),
                ]);
                $this->error("Fehler in Phase {$p}: " . $e->getMessage());
                $this->error($e->getTraceAsString());
            }
        }

        $this->info("");
        $this->info("Import abgeschlossen.");
        return self::SUCCESS;
    }
}
